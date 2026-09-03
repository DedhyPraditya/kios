<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleCorrectionTest extends TestCase
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
            'name' => 'Minyak 1L',
            'price' => 18000,
            'cost' => 15000,
            'stock' => 10,
            'low_stock' => 2,
            'is_active' => true,
        ]);
    }

    private function sell(int $qty = 2, array $override = []): Sale
    {
        $this->actingAs($this->kasir)->post(route('pos.store'), array_merge([
            'items' => [['id' => $this->product->id, 'qty' => $qty]],
            'paid' => 100000,
        ], $override));

        return Sale::latest('id')->firstOrFail();
    }

    public function test_penjualan_mencatat_pergerakan_stok(): void
    {
        $sale = $this->sell(3);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'sale_id' => $sale->id,
            'type' => 'penjualan',
            'qty' => -3,
            'stock_after' => 7,
        ]);
    }

    public function test_batal_mengembalikan_stok_dan_mengeluarkan_nota_dari_laporan(): void
    {
        $sale = $this->sell(2);
        $this->assertSame(8, $this->product->fresh()->stock);

        $this->actingAs($this->admin)
            ->post(route('sales.void', $sale), ['reason' => 'salah input'])
            ->assertSessionHasNoErrors();

        $sale->refresh();

        $this->assertNotNull($sale->voided_at);
        $this->assertSame($this->admin->id, $sale->voided_by);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(0, Sale::valid()->count());
        $this->assertDatabaseHas('stock_movements', [
            'sale_id' => $sale->id,
            'type' => 'batal',
            'qty' => 2,
        ]);
    }

    public function test_nota_batal_tidak_bisa_dibatalkan_dua_kali(): void
    {
        $sale = $this->sell();
        $this->actingAs($this->admin)->post(route('sales.void', $sale), ['reason' => 'x']);

        $this->actingAs($this->admin)
            ->post(route('sales.void', $sale), ['reason' => 'lagi'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_retur_sebagian_mengembalikan_stok_dan_mengurangi_nilai_nota(): void
    {
        $sale = $this->sell(3);
        $item = $sale->items()->first();

        $this->actingAs($this->admin)->post(route('sales.refund', $sale), [
            'items' => [['sale_item_id' => $item->id, 'qty' => 1]],
            'reason' => 'barang rusak',
        ])->assertSessionHasNoErrors();

        $sale->refresh();

        $this->assertSame(18000, $sale->refunded);
        $this->assertSame(36000, $sale->netTotal());
        $this->assertSame(1, $item->fresh()->returned_qty);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'sale_id' => $sale->id,
            'type' => 'retur',
            'qty' => 1,
        ]);
    }

    public function test_retur_melebihi_jumlah_beli_ditolak(): void
    {
        $sale = $this->sell(2);
        $item = $sale->items()->first();

        $this->actingAs($this->admin)->post(route('sales.refund', $sale), [
            'items' => [['sale_item_id' => $item->id, 'qty' => 5]],
            'reason' => 'coba curang',
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, $item->fresh()->returned_qty);
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_retur_nota_kasbon_mengurangi_sisa_hutang(): void
    {
        $customer = Customer::create(['name' => 'Pak Budi']);
        $sale = $this->sell(2, [
            'paid' => 0,
            'payment_type' => 'kasbon',
            'customer_id' => $customer->id,
        ]);

        $this->assertSame(36000, $sale->outstanding());

        $this->actingAs($this->admin)->post(route('sales.refund', $sale), [
            'items' => [['sale_item_id' => $sale->items()->first()->id, 'qty' => 1]],
            'reason' => 'ditukar',
        ])->assertSessionHasNoErrors();

        $this->assertSame(18000, $sale->fresh()->outstanding());
        $this->assertSame(18000, $customer->outstanding());
    }

    public function test_nota_yang_sudah_dicicil_tidak_boleh_dibatalkan(): void
    {
        $customer = Customer::create(['name' => 'Bu Ani']);
        $sale = $this->sell(1, [
            'paid' => 0,
            'payment_type' => 'kasbon',
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($this->admin)->post(route('credit-payments.store'), [
            'customer_id' => $customer->id,
            'amount' => 5000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('sales.void', $sale), ['reason' => 'iseng'])
            ->assertSessionHasErrors('reason');

        $this->assertNull($sale->fresh()->voided_at);
    }

    public function test_ubah_keterangan_nota_tidak_mengubah_nilai_uang(): void
    {
        $sale = $this->sell(1);

        $this->actingAs($this->admin)
            ->patch(route('sales.update', $sale), ['note' => 'diambil sore'])
            ->assertSessionHasNoErrors();

        $sale->refresh();

        $this->assertSame('diambil sore', $sale->note);
        $this->assertSame(18000, $sale->total);
    }

    public function test_kasir_tidak_boleh_membatalkan_nota(): void
    {
        $sale = $this->sell();

        $this->actingAs($this->kasir)
            ->post(route('sales.void', $sale), ['reason' => 'coba'])
            ->assertForbidden();
    }

    public function test_riwayat_transaksi_bisa_dicari_per_no_nota(): void
    {
        $sale = $this->sell();

        $this->actingAs($this->admin)
            ->get(route('sales.index', ['q' => $sale->invoice_no]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/Index')
                ->has('sales.data', 1)
                ->where('sales.data.0.invoice_no', $sale->invoice_no));

        $this->actingAs($this->admin)
            ->get(route('sales.index', ['q' => 'INVTIDAKADA']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('sales.data', 0));
    }

    public function test_batal_mencatat_kas_keluar_pada_shift_yang_terbuka(): void
    {
        $this->actingAs($this->kasir)->post(route('shift.store'), ['opening_cash' => 100000]);
        $sale = $this->sell(1);

        $this->actingAs($this->kasir)
            ->post(route('sales.void', $sale), ['reason' => 'salah'])
            ->assertForbidden();

        // Admin membatalkan; kas keluar tercatat di shift milik admin (belum ada),
        // sehingga laci kasir tidak ikut berkurang.
        $this->actingAs($this->admin)->post(route('sales.void', $sale), ['reason' => 'salah']);

        $session = CashSession::openFor($this->kasir);

        $this->assertSame(0, $session->movements()->count());
        $this->assertSame(1, StockMovement::where('type', 'batal')->count());
    }
}
