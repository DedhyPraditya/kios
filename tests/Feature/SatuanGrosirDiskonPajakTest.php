<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SatuanGrosirDiskonPajakTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $kasir;
    private Product $mie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->kasir = User::factory()->create(['role' => 'kasir']);

        // Mie: Rp3.500/pcs (modal 3.000), dus isi 40 Rp130.000, grosir >= 10 pcs Rp3.300.
        $this->mie = Product::create([
            'category_id' => Category::create(['name' => 'Mie'])->id,
            'name' => 'Mie Goreng', 'barcode' => '8990001', 'price' => 3500, 'cost' => 3000,
            'stock' => 100, 'low_stock' => 5,
        ]);
        $this->mie->units()->create(['name' => 'dus', 'isi' => 40, 'price' => 130000, 'barcode' => '8990040']);
        $this->mie->wholesalePrices()->create(['min_qty' => 10, 'price' => 3300]);
    }

    private function jual(array $payload): Sale
    {
        $this->actingAs($this->kasir)->post(route('pos.store'), $payload)->assertSessionHasNoErrors();

        return Sale::with('items')->latest('id')->firstOrFail();
    }

    public function test_jual_per_dus_mengurangi_stok_dalam_pcs(): void
    {
        $unit = $this->mie->units->first();
        $sale = $this->jual(['items' => [['id' => $this->mie->id, 'unit_id' => $unit->id, 'qty' => 2]], 'paid' => 260000]);

        $item = $sale->items->first();
        $this->assertSame('dus', $item->unit_name);
        $this->assertSame(40, $item->unit_isi);
        $this->assertSame(130000, $item->price);
        $this->assertSame(120000, $item->cost); // modal 3.000 x 40
        $this->assertSame(260000, $sale->total);
        $this->assertSame(20, $this->mie->fresh()->stock);
    }

    public function test_stok_tidak_cukup_untuk_dus_ditolak(): void
    {
        $unit = $this->mie->units->first();

        $this->actingAs($this->kasir)->post(route('pos.store'), [
            'items' => [['id' => $this->mie->id, 'unit_id' => $unit->id, 'qty' => 3]], 'paid' => 390000,
        ])->assertSessionHasErrors('items');

        $this->assertSame(100, $this->mie->fresh()->stock);
    }

    public function test_harga_grosir_otomatis_saat_jumlah_tercapai(): void
    {
        $eceran = $this->jual(['items' => [['id' => $this->mie->id, 'qty' => 9]], 'paid' => 31500]);
        $grosir = $this->jual(['items' => [['id' => $this->mie->id, 'qty' => 10]], 'paid' => 33000]);

        $this->assertSame(3500, $eceran->items->first()->price);
        $this->assertSame(3300, $grosir->items->first()->price);
        $this->assertSame(33000, $grosir->total);
    }

    public function test_diskon_baris_dan_diskon_nota_persen(): void
    {
        // 4 pcs x 3.500 = 14.000, diskon baris 2.000 -> 12.000; diskon nota 10% -> 1.200.
        $sale = $this->jual([
            'items' => [['id' => $this->mie->id, 'qty' => 4, 'discount' => 2000]],
            'discount_percent' => 10,
            'paid' => 20000,
        ]);

        $this->assertSame(2000, $sale->items->first()->discount);
        $this->assertSame(12000, $sale->items->first()->subtotal);
        $this->assertSame(12000, $sale->subtotal);
        $this->assertSame(1200, $sale->discount);
        $this->assertEquals(10, $sale->discount_percent);
        $this->assertSame(10800, $sale->total);
    }

    public function test_diskon_baris_tidak_melebihi_harga_baris(): void
    {
        $sale = $this->jual(['items' => [['id' => $this->mie->id, 'qty' => 1, 'discount' => 99999]], 'paid' => 0]);

        $this->assertSame(3500, $sale->items->first()->discount);
        $this->assertSame(0, $sale->total);
    }

    public function test_ppn_ditambahkan_bila_aktif(): void
    {
        Setting::put(['tax_enabled' => '1', 'tax_rate' => '11']);

        // 10 pcs grosir = 33.000, diskon nota 3.000 -> 30.000, PPN 11% = 3.300.
        $sale = $this->jual(['items' => [['id' => $this->mie->id, 'qty' => 10]], 'discount' => 3000, 'paid' => 40000]);

        $this->assertEquals(11, $sale->tax_rate);
        $this->assertSame(3300, $sale->tax);
        $this->assertSame(33300, $sale->total);
        $this->assertSame(6700, $sale->change);
    }

    public function test_ppn_tidak_ditarik_bila_nonaktif(): void
    {
        $sale = $this->jual(['items' => [['id' => $this->mie->id, 'qty' => 1]], 'paid' => 3500]);

        $this->assertSame(0, $sale->tax);
        $this->assertSame(3500, $sale->total);
    }

    public function test_retur_dus_mengembalikan_stok_pcs_dan_nilai_bersih(): void
    {
        Setting::put(['tax_enabled' => '1', 'tax_rate' => '10']);
        $unit = $this->mie->units->first();
        // 2 dus = 260.000, diskon baris 10.000 -> 250.000, PPN 10% -> total 275.000.
        $sale = $this->jual([
            'items' => [['id' => $this->mie->id, 'unit_id' => $unit->id, 'qty' => 2, 'discount' => 10000]],
            'paid' => 275000,
        ]);
        $this->assertSame(275000, $sale->total);

        $this->actingAs($this->admin)->post(route('sales.refund', $sale), [
            'items' => [['sale_item_id' => $sale->items->first()->id, 'qty' => 1]],
            'reason' => 'Rusak',
        ])->assertSessionHasNoErrors();

        // Separuh nota: 125.000 + PPN 12.500.
        $this->assertSame(137500, $sale->fresh()->refunded);
        $this->assertSame(60, $this->mie->fresh()->stock);
    }

    public function test_batal_nota_dus_mengembalikan_stok_pcs(): void
    {
        $unit = $this->mie->units->first();
        $sale = $this->jual(['items' => [['id' => $this->mie->id, 'unit_id' => $unit->id, 'qty' => 1]], 'paid' => 130000]);

        $this->actingAs($this->admin)->post(route('sales.void', $sale), ['reason' => 'Salah'])->assertSessionHasNoErrors();

        $this->assertSame(100, $this->mie->fresh()->stock);
    }

    public function test_laporan_menghitung_laba_dari_harga_bersih_dan_ppn(): void
    {
        Setting::put(['tax_enabled' => '1', 'tax_rate' => '10']);
        // 4 pcs x 3.500 - diskon 2.000 = 12.000; modal 12.000 -> laba 0. PPN 1.200.
        $this->jual(['items' => [['id' => $this->mie->id, 'qty' => 4, 'discount' => 2000]], 'paid' => 13200]);

        $this->actingAs($this->admin)->get(route('reports.index'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.omzet', 13200)
                ->where('summary.profit', 0)
                ->where('summary.tax', 1200)
                ->where('summary.discount', 2000)
                ->where('topProducts.0.unit', 'pcs'));
    }

    public function test_admin_mengatur_satuan_dan_grosir_di_form_produk(): void
    {
        $unit = $this->mie->units->first();

        $this->actingAs($this->admin)->put(route('products.update', $this->mie), [
            'name' => 'Mie Goreng', 'barcode' => '8990001', 'price' => 3500, 'cost' => 3000,
            'stock' => 100, 'low_stock' => 5, 'is_active' => true,
            'units' => [
                ['id' => $unit->id, 'name' => 'dus', 'isi' => 40, 'price' => 128000, 'barcode' => '8990040'],
                ['name' => 'pak', 'isi' => 5, 'price' => 17000, 'barcode' => ''],
            ],
            'wholesale_prices' => [['min_qty' => 20, 'price' => 3200]],
        ])->assertSessionHasNoErrors();

        $mie = $this->mie->fresh(['units', 'wholesalePrices']);
        $this->assertSame(['pak', 'dus'], $mie->units->pluck('name')->all());
        $this->assertSame($unit->id, $mie->units->firstWhere('name', 'dus')->id);
        $this->assertSame(128000, $mie->units->firstWhere('name', 'dus')->price);
        $this->assertSame([20], $mie->wholesalePrices->pluck('min_qty')->all());
    }

    public function test_harga_grosir_harus_lebih_murah_dan_barcode_satuan_unik(): void
    {
        $lain = Product::create(['name' => 'Kopi', 'barcode' => '777', 'price' => 2000, 'cost' => 1500, 'stock' => 5, 'low_stock' => 1]);

        $kirim = fn (array $units, array $tiers) => $this->actingAs($this->admin)->put(route('products.update', $lain), [
            'name' => 'Kopi', 'barcode' => '777', 'price' => 2000, 'cost' => 1500, 'stock' => 5, 'low_stock' => 1,
            'units' => $units, 'wholesale_prices' => $tiers,
        ]);

        $kirim([], [['min_qty' => 10, 'price' => 2500]])->assertSessionHasErrors('wholesale_prices.0.price');
        // Barcode dus milik Mie tidak boleh dipakai satuan produk lain.
        $kirim([['name' => 'renteng', 'isi' => 10, 'price' => 19000, 'barcode' => '8990040']], [])
            ->assertSessionHasErrors('units.0.barcode');
        // Barcode produk tidak boleh sama dengan barcode satuan mana pun.
        $this->actingAs($this->admin)->put(route('products.update', $lain), [
            'name' => 'Kopi', 'barcode' => '8990040', 'price' => 2000, 'cost' => 1500, 'stock' => 5, 'low_stock' => 1,
        ])->assertSessionHasErrors('barcode');
    }

    public function test_scan_barcode_dus_dikirim_sebagai_satuan_dus(): void
    {
        // Katalog kasir membawa satuan & grosir untuk pencocokan barcode di layar.
        $this->actingAs($this->kasir)->get(route('pos.index'))
            ->assertInertia(fn ($page) => $page
                ->where('products.0.units.0.barcode', '8990040')
                ->where('products.0.wholesale_prices.0.min_qty', 10));
    }

    public function test_pengaturan_ppn_tersimpan(): void
    {
        $this->actingAs($this->admin)->post(route('settings.update'), [
            'store_name' => 'Kios', 'tax_enabled' => '1', 'tax_rate' => '11',
        ])->assertSessionHasNoErrors();

        $this->assertSame('1', Setting::values()['tax_enabled']);
        $this->assertSame('11', Setting::values()['tax_rate']);
    }
}
