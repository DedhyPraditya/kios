<?php

namespace Tests\Feature;

use App\Models\ArangJenis;
use App\Models\ArangPembelian;
use App\Models\ArangPenjualan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function kasir(): User
    {
        return User::factory()->create(['role' => 'kasir']);
    }

    public function test_admin_can_view_consolidated_reports(): void
    {
        $admin = $this->admin();

        $cat = \App\Models\Category::create(['name' => 'Sembako']);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Beras Pandan 5kg',
            'price' => 65000,
            'cost' => 55000,
            'stock' => 20,
            'low_stock' => 5,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'invoice_no' => 'INV-TEST-0001',
            'user_id' => $admin->id,
            'payment_type' => 'tunai',
            'status' => 'lunas',
            'subtotal' => 65000,
            'discount' => 5000,
            'total' => 60000,
            'paid' => 60000,
            'change' => 0,
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => 65000,
            'cost' => 55000,
            'qty' => 1,
            'subtotal' => 65000,
        ]);

        // Buat varian arang dan transaksi arang
        $arang = ArangJenis::create([
            'nama' => 'Batok Kelapa Premium',
            'harga_beli_default' => 6000,
            'harga_jual_default' => 9000,
            'aktif' => true,
        ]);

        ArangPembelian::create([
            'tanggal' => today(),
            'arang_jenis_id' => $arang->id,
            'nama_pemasok' => 'Pak Slamet',
            'berat_kg' => 50,
            'harga_beli_per_kg' => 6000,
            'total_harga' => 300000,
            'user_id' => $admin->id,
        ]);

        ArangPenjualan::create([
            'no_nota' => 'ARNG-TEST-0001',
            'tanggal' => today(),
            'arang_jenis_id' => $arang->id,
            'nama_pembeli' => 'Pak Joko Sate',
            'berat_kg' => 10,
            'harga_jual_per_kg' => 9000,
            'total_harga' => 90000,
            'diskon' => 0,
            'grand_total' => 90000,
            'paid' => 90000,
            'change' => 0,
            'payment_type' => 'tunai',
            'status' => 'lunas',
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('summary.count', 2) // 1 toko + 1 arang
                ->where('summary.omzet', 150000) // 60.000 + 90.000
                ->where('summary.discount', 5000)
                ->where('breakdown.toko.omzet', 60000)
                ->where('breakdown.arang.omzet', 90000)
                ->where('breakdown.arang.berat_kg', 10)
                ->has('topProducts')
                ->has('recent', 2)
            );
    }

    public function test_admin_can_export_excel_report(): void
    {
        $admin = $this->admin();

        $arang = ArangJenis::create([
            'nama' => 'Batok Kelapa',
            'harga_beli_default' => 6000,
            'harga_jual_default' => 9000,
            'aktif' => true,
        ]);

        ArangPenjualan::create([
            'no_nota' => 'ARNG-XLS-0001',
            'tanggal' => today(),
            'arang_jenis_id' => $arang->id,
            'nama_pembeli' => 'Pelanggan Arang',
            'berat_kg' => 5,
            'harga_jual_per_kg' => 9000,
            'total_harga' => 45000,
            'diskon' => 0,
            'grand_total' => 45000,
            'paid' => 45000,
            'change' => 0,
            'payment_type' => 'tunai',
            'status' => 'lunas',
            'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('reports.export.excel', [
                'from' => today()->toDateString(),
                'to' => today()->toDateString(),
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_admin_can_export_csv_report(): void
    {
        $admin = $this->admin();

        $arang = ArangJenis::create([
            'nama' => 'Batok Kelapa',
            'harga_beli_default' => 6000,
            'harga_jual_default' => 9000,
            'aktif' => true,
        ]);

        ArangPenjualan::create([
            'no_nota' => 'ARNG-CSV-0001',
            'tanggal' => today(),
            'arang_jenis_id' => $arang->id,
            'nama_pembeli' => 'Pelanggan Arang',
            'berat_kg' => 5,
            'harga_jual_per_kg' => 9000,
            'total_harga' => 45000,
            'diskon' => 0,
            'grand_total' => 45000,
            'paid' => 45000,
            'change' => 0,
            'payment_type' => 'tunai',
            'status' => 'lunas',
            'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('reports.export.csv', [
                'from' => today()->toDateString(),
                'to' => today()->toDateString(),
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // Capture streamed output
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('LAPORAN PENJUALAN & KEUANGAN TERPADU', $content);
        $this->assertStringContainsString('RINGKASAN EKSEKUTIF KEUANGAN', $content);
        $this->assertStringContainsString('RINCIAN PENJUALAN TOKO ECERAN', $content);
        $this->assertStringContainsString('RINCIAN PENJUALAN ARANG KILOAN', $content);
        $this->assertStringContainsString('ARNG-CSV-0001', $content);
    }

    public function test_cashier_cannot_access_reports_or_export(): void
    {
        $kasir = $this->kasir();

        $this->actingAs($kasir)
            ->get(route('reports.index'))
            ->assertForbidden();

        $this->actingAs($kasir)
            ->get(route('reports.export.excel'))
            ->assertForbidden();

        $this->actingAs($kasir)
            ->get(route('reports.export.csv'))
            ->assertForbidden();
    }
}
