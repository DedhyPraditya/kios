<?php

namespace Tests\Feature;

use App\Models\ArangJenis;
use App\Models\ArangPembelian;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $budi;
    private User $sari;
    private Category $minuman;
    private Product $teh;
    private Product $beras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->budi = User::factory()->create(['role' => 'kasir', 'name' => 'Budi']);
        $this->sari = User::factory()->create(['role' => 'kasir', 'name' => 'Sari']);

        $this->minuman = Category::create(['name' => 'Minuman']);
        $sembako = Category::create(['name' => 'Sembako']);
        $this->teh = Product::create(['category_id' => $this->minuman->id, 'name' => 'Teh', 'price' => 5000, 'cost' => 3000, 'stock' => 50, 'low_stock' => 1]);
        $this->beras = Product::create(['category_id' => $sembako->id, 'name' => 'Beras', 'price' => 60000, 'cost' => 50000, 'stock' => 50, 'low_stock' => 1]);

        // Budi: 2 teh + 1 beras, diskon 1.000. Sari: 1 beras.
        $this->actingAs($this->budi)->post(route('pos.store'), [
            'items' => [['id' => $this->teh->id, 'qty' => 2], ['id' => $this->beras->id, 'qty' => 1]],
            'discount' => 1000, 'paid' => 69000,
        ]);
        $this->actingAs($this->sari)->post(route('pos.store'), [
            'items' => [['id' => $this->beras->id, 'qty' => 1]], 'paid' => 60000,
        ]);

        // Sari juga menjual arang 2 kg @5.000.
        $jenis = ArangJenis::create(['nama' => 'Batok', 'harga_beli_default' => 3000, 'harga_jual_default' => 5000, 'aktif' => true]);
        ArangPembelian::create(['tanggal' => today(), 'arang_jenis_id' => $jenis->id, 'nama_pemasok' => 'Pak A', 'berat_kg' => 10, 'harga_beli_per_kg' => 3000, 'total_harga' => 30000, 'user_id' => $this->admin->id]);
        $this->actingAs($this->sari)->post(route('arang.jual.store'), [
            'tanggal' => today()->toDateString(), 'arang_jenis_id' => $jenis->id, 'berat_kg' => 2,
            'harga_jual_per_kg' => 5000, 'payment_type' => 'tunai', 'paid' => 10000,
        ]);
    }

    private function laporan(array $query): array
    {
        $props = null;
        $this->actingAs($this->admin)->get(route('reports.index', $query))
            ->assertOk()
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return $props;
    }

    public function test_tanpa_filter_menghitung_semua(): void
    {
        $r = $this->laporan([]);

        $this->assertSame(69000 + 60000 + 10000, $r['summary']['omzet']);
        $this->assertSame(3, $r['summary']['count']);
    }

    public function test_filter_kasir_hanya_menghitung_transaksi_kasir_itu(): void
    {
        $r = $this->laporan(['kasir' => $this->sari->id]);

        $this->assertSame(60000, $r['breakdown']['toko']['omzet']);
        $this->assertSame(10000, $r['breakdown']['arang']['omzet']);
        $this->assertSame(2, $r['summary']['count']);
        $this->assertSame($this->sari->id, $r['filters']['kasir']);
    }

    public function test_filter_kategori_menghitung_barang_kategori_itu_tanpa_arang(): void
    {
        $r = $this->laporan(['kategori' => $this->minuman->id]);

        // 2 teh x 5.000, sebelum diskon nota; laba (5.000 - 3.000) x 2.
        $this->assertSame(10000, $r['summary']['omzet']);
        $this->assertSame(4000, $r['summary']['profit']);
        $this->assertSame(1, $r['summary']['count']);
        $this->assertSame(0, $r['breakdown']['arang']['omzet']);
        $this->assertSame(['Teh'], array_column($r['topProducts'], 'name'));
        $this->assertSame(10000, $r['daily'][0]['omzet']);
    }

    public function test_ekspor_mengikuti_filter(): void
    {
        $csv = $this->actingAs($this->admin)
            ->get(route('reports.export.csv', ['kasir' => $this->sari->id]))
            ->streamedContent();

        $this->assertStringContainsString('Kasir: Sari', $csv);
        $this->assertStringNotContainsString('Budi', $csv);

        $this->actingAs($this->admin)
            ->get(route('reports.export.excel', ['kategori' => $this->minuman->id]))
            ->assertOk();
    }
}
