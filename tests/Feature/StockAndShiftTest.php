<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAndShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kasir;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->kasir = User::factory()->create(['role' => 'kasir']);
        $this->product = Product::create([
            'name' => 'Gula 1kg',
            'price' => 16000,
            'cost' => 13000,
            'stock' => 5,
            'low_stock' => 2,
            'is_active' => true,
        ]);
    }

    // ---------- Barang masuk ----------

    public function test_barang_masuk_menambah_stok_dan_memperbarui_harga_modal(): void
    {
        $this->actingAs($this->admin)->post(route('stock.store'), [
            'type' => 'masuk',
            'supplier' => 'Grosir Jaya',
            'items' => [['product_id' => $this->product->id, 'qty' => 12, 'cost' => 13500]],
        ])->assertSessionHasNoErrors();

        $product = $this->product->fresh();

        $this->assertSame(17, $product->stock);
        $this->assertSame(13500, $product->cost);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'masuk',
            'qty' => 12,
            'stock_after' => 17,
            'supplier' => 'Grosir Jaya',
        ]);
    }

    public function test_barang_masuk_menolak_jumlah_minus(): void
    {
        $this->actingAs($this->admin)->post(route('stock.store'), [
            'type' => 'masuk',
            'items' => [['product_id' => $this->product->id, 'qty' => -3]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(5, $this->product->fresh()->stock);
    }

    public function test_penyesuaian_bisa_mengurangi_tapi_tidak_sampai_minus(): void
    {
        $this->actingAs($this->admin)->post(route('stock.store'), [
            'type' => 'penyesuaian',
            'note' => 'rusak kena air',
            'items' => [['product_id' => $this->product->id, 'qty' => -2]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, $this->product->fresh()->stock);

        $this->actingAs($this->admin)->post(route('stock.store'), [
            'type' => 'penyesuaian',
            'items' => [['product_id' => $this->product->id, 'qty' => -99]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(3, $this->product->fresh()->stock);
    }

    public function test_kasir_tidak_boleh_membuka_halaman_barang_masuk(): void
    {
        $this->actingAs($this->kasir)->get(route('stock.index'))->assertForbidden();
    }

    // ---------- Tutup kasir ----------

    public function test_shift_menghitung_uang_laci_dari_penjualan_dan_kas_manual(): void
    {
        $this->actingAs($this->kasir)
            ->post(route('shift.store'), ['opening_cash' => 100000])
            ->assertSessionHasNoErrors();

        // Tunai 2 x 16.000 = 32.000 masuk laci.
        $this->actingAs($this->kasir)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 2]],
            'paid' => 50000,
        ]);

        // Kasbon dengan DP 5.000: hanya DP yang masuk laci.
        $customer = Customer::create(['name' => 'Bu Sari']);
        $this->actingAs($this->kasir)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 1]],
            'paid' => 5000,
            'payment_type' => 'kasbon',
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($this->kasir)->post(route('shift.movement'), [
            'direction' => 'keluar',
            'amount' => 7000,
            'note' => 'beli plastik',
        ]);

        $session = CashSession::openFor($this->kasir);
        $summary = $session->summary();

        $this->assertSame(32000, $summary['sales_tunai']);
        $this->assertSame(5000, $summary['dp_kasbon']);
        $this->assertSame(7000, $summary['cash_out']);
        $this->assertSame(130000, $summary['expected_cash']); // 100000 + 32000 + 5000 - 7000
    }

    public function test_tutup_shift_menyimpan_selisih(): void
    {
        $this->actingAs($this->kasir)->post(route('shift.store'), ['opening_cash' => 50000]);
        $session = CashSession::openFor($this->kasir);

        $this->actingAs($this->kasir)->post(route('shift.close', $session), [
            'counted_cash' => 48000,
            'deposit' => 40000,
        ])->assertSessionHasNoErrors();

        $session->refresh();

        $this->assertNotNull($session->closed_at);
        $this->assertSame(50000, $session->expected_cash);
        $this->assertSame(48000, $session->counted_cash);
        $this->assertSame(-2000, $session->difference);
        $this->assertNull(CashSession::openFor($this->kasir));
    }

    public function test_tidak_bisa_membuka_dua_shift_sekaligus(): void
    {
        $this->actingAs($this->kasir)->post(route('shift.store'), ['opening_cash' => 10000]);

        $this->actingAs($this->kasir)
            ->post(route('shift.store'), ['opening_cash' => 20000])
            ->assertSessionHasErrors('opening_cash');

        $this->assertSame(1, CashSession::where('user_id', $this->kasir->id)->count());
    }

    public function test_kasir_lain_tidak_boleh_menutup_shift_orang(): void
    {
        $this->actingAs($this->kasir)->post(route('shift.store'), ['opening_cash' => 10000]);
        $session = CashSession::openFor($this->kasir);

        $lain = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($lain)
            ->post(route('shift.close', $session), ['counted_cash' => 10000])
            ->assertForbidden();

        $this->assertNull($session->fresh()->closed_at);
    }

    public function test_batal_nota_di_shift_yang_sama_tidak_memotong_laci_dua_kali(): void
    {
        $admin = $this->admin;
        $this->actingAs($admin)->post(route('shift.store'), ['opening_cash' => 100000]);

        $this->actingAs($admin)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 2]],
            'paid' => 32000,
        ]);

        $session = CashSession::openFor($admin);
        $this->assertSame(132000, $session->summary()['expected_cash']);

        $sale = \App\Models\Sale::latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('sales.void', $sale), ['reason' => 'salah barang']);

        // Nota hilang dari penjualan shift ini, jadi tak ada kas keluar tambahan.
        $session->refresh();
        $this->assertSame(0, $session->movements()->count());
        $this->assertSame(100000, $session->summary()['expected_cash']);
    }

    public function test_batal_nota_shift_lama_dicatat_sebagai_kas_keluar_shift_berjalan(): void
    {
        $admin = $this->admin;

        $this->actingAs($admin)->post(route('shift.store'), ['opening_cash' => 100000]);
        $this->actingAs($admin)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 1]],
            'paid' => 16000,
        ]);
        $sale = \App\Models\Sale::latest('id')->firstOrFail();

        $lama = CashSession::openFor($admin);
        $this->actingAs($admin)->post(route('shift.close', $lama), ['counted_cash' => 116000]);

        $this->actingAs($admin)->post(route('shift.store'), ['opening_cash' => 50000]);
        $this->actingAs($admin)->post(route('sales.void', $sale), ['reason' => 'dikembalikan besoknya']);

        $baru = CashSession::openFor($admin);

        $this->assertSame(1, $baru->movements()->where('direction', 'keluar')->count());
        $this->assertSame(34000, $baru->summary()['expected_cash']); // 50.000 - 16.000
    }

    public function test_retur_di_shift_yang_sama_mengurangi_uang_laci_sekali_saja(): void
    {
        $admin = $this->admin;
        $this->actingAs($admin)->post(route('shift.store'), ['opening_cash' => 0]);

        $this->actingAs($admin)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 2]],
            'paid' => 32000,
        ]);
        $sale = \App\Models\Sale::latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('sales.refund', $sale), [
            'items' => [['sale_item_id' => $sale->items()->first()->id, 'qty' => 1]],
            'reason' => 'rusak',
        ]);

        $session = CashSession::openFor($admin);

        $this->assertSame(0, $session->movements()->count());
        $this->assertSame(16000, $session->summary()['expected_cash']);
    }

    // ---------- Pengaturan toko ----------

    public function test_pengaturan_toko_tersimpan_dan_dipakai_di_struk(): void
    {
        $this->actingAs($this->admin)->patch(route('settings.update'), [
            'store_name' => 'Kios Berkah Jaya',
            'store_address' => 'Jl. Melati 12',
            'store_phone' => '0812000111',
            'receipt_footer' => 'Barang tidak dapat ditukar.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Kios Berkah Jaya', Setting::get('store_name'));

        $this->actingAs($this->kasir)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 1]],
            'paid' => 20000,
        ]);

        $sale = \App\Models\Sale::latest('id')->firstOrFail();

        $this->actingAs($this->kasir)
            ->get(route('pos.receipt', $sale))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Pos/Receipt')
                ->where('store.store_name', 'Kios Berkah Jaya')
                ->where('store.receipt_footer', 'Barang tidak dapat ditukar.'));
    }

    public function test_kasir_tidak_boleh_mengubah_pengaturan_toko(): void
    {
        $this->actingAs($this->kasir)
            ->patch(route('settings.update'), ['store_name' => 'Toko Bajakan'])
            ->assertForbidden();

        $this->assertSame('Kios BERKAH', Setting::get('store_name'));
    }
}
