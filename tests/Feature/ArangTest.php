<?php

namespace Tests\Feature;

use App\Models\ArangJenis;
use App\Models\ArangPembelian;
use App\Models\ArangPenjualan;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArangTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $kasir;
    private ArangJenis $jenisBatok;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->kasir = User::factory()->create(['role' => 'kasir']);

        $this->jenisBatok = ArangJenis::create([
            'nama' => 'Arang Batok Kelapa',
            'harga_beli_default' => 3000,
            'harga_jual_default' => 5000,
            'aktif' => true,
        ]);
    }

    public function test_dashboard_arang_accessible_by_authenticated_user(): void
    {
        $response = $this->actingAs($this->kasir)->get(route('arang.index'));
        $response->assertOk();

        $responseAdmin = $this->actingAs($this->admin)->get(route('arang.index'));
        $responseAdmin->assertOk();
    }

    public function test_admin_can_manage_arang_jenis(): void
    {
        $response = $this->actingAs($this->admin)->post(route('arang.jenis.store'), [
            'nama' => 'Arang Briket Hexagonal',
            'harga_beli_default' => 4500,
            'harga_jual_default' => 7000,
            'catatan' => 'Briket ekspor',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('arang_jenis', [
            'nama' => 'Arang Briket Hexagonal',
            'harga_beli_default' => 4500,
            'harga_jual_default' => 7000,
        ]);

        $created = ArangJenis::where('nama', 'Arang Briket Hexagonal')->first();
        $this->actingAs($this->admin)->put(route('arang.jenis.update', $created->id), [
            'nama' => 'Arang Briket Super',
            'harga_beli_default' => 4800,
            'harga_jual_default' => 7500,
            'aktif' => true,
            'catatan' => 'Updated',
        ]);

        $this->assertDatabaseHas('arang_jenis', [
            'id' => $created->id,
            'nama' => 'Arang Briket Super',
        ]);
    }

    public function test_kasir_forbidden_from_managing_arang_jenis(): void
    {
        $response = $this->actingAs($this->kasir)->post(route('arang.jenis.store'), [
            'nama' => 'Arang Ilegal',
            'harga_beli_default' => 1000,
            'harga_jual_default' => 2000,
        ]);

        $response->assertForbidden();
    }

    public function test_pembelian_arang_menambah_stok(): void
    {
        $this->assertEquals(0, $this->jenisBatok->stok_kg);

        $response = $this->actingAs($this->kasir)->post(route('arang.beli.store'), [
            'tanggal' => now()->toDateString(),
            'arang_jenis_id' => $this->jenisBatok->id,
            'nama_pemasok' => 'Pak Joko Pengrajin',
            'berat_kg' => 25.5,
            'harga_beli_per_kg' => 3000,
            'catatan' => 'Barang bagus kering',
        ]);

        $response->assertRedirect(route('arang.index'));
        $this->assertDatabaseHas('arang_pembelian', [
            'arang_jenis_id' => $this->jenisBatok->id,
            'nama_pemasok' => 'Pak Joko Pengrajin',
            'berat_kg' => 25.5,
            'harga_beli_per_kg' => 3000,
            'total_harga' => 76500, // 25.5 * 3000
        ]);

        $this->assertEquals(25.5, $this->jenisBatok->fresh()->stok_kg);
    }

    public function test_penjualan_arang_mengurangi_stok(): void
    {
        // Berikan stok awal 20 kg
        ArangPembelian::create([
            'tanggal' => now()->toDateString(),
            'arang_jenis_id' => $this->jenisBatok->id,
            'nama_pemasok' => 'Pengrajin',
            'berat_kg' => 20.0,
            'harga_beli_per_kg' => 3000,
            'total_harga' => 60000,
            'user_id' => $this->kasir->id,
        ]);

        $this->assertEquals(20.0, $this->jenisBatok->fresh()->stok_kg);

        $response = $this->actingAs($this->kasir)->post(route('arang.jual.store'), [
            'tanggal' => now()->toDateString(),
            'arang_jenis_id' => $this->jenisBatok->id,
            'nama_pembeli' => 'Warung Sate Barokah',
            'berat_kg' => 7.5,
            'harga_jual_per_kg' => 5000,
            'diskon' => 0,
            'payment_type' => 'tunai',
            'paid' => 40000,
        ]);

        $response->assertRedirect(route('arang.index'));
        $this->assertDatabaseHas('arang_penjualan', [
            'arang_jenis_id' => $this->jenisBatok->id,
            'nama_pembeli' => 'Warung Sate Barokah',
            'berat_kg' => 7.5,
            'harga_jual_per_kg' => 5000,
            'total_harga' => 37500,
            'grand_total' => 37500,
            'paid' => 40000,
            'change' => 2500,
            'status' => 'lunas',
        ]);

        // Sisa stok harus 20 - 7.5 = 12.5 kg
        $this->assertEquals(12.5, $this->jenisBatok->fresh()->stok_kg);
    }

    public function test_penjualan_gagal_jika_stok_tidak_cukup(): void
    {
        // Stok saat ini 0
        $response = $this->actingAs($this->kasir)->post(route('arang.jual.store'), [
            'tanggal' => now()->toDateString(),
            'arang_jenis_id' => $this->jenisBatok->id,
            'nama_pembeli' => 'Pembeli',
            'berat_kg' => 5.0,
            'harga_jual_per_kg' => 5000,
            'payment_type' => 'tunai',
            'paid' => 25000,
        ]);

        $response->assertSessionHasErrors('berat_kg');
        $this->assertDatabaseCount('arang_penjualan', 0);
    }

    public function test_penjualan_dan_pembelian_terintegrasi_ke_shift_laci_kasir(): void
    {
        $session = CashSession::create([
            'user_id' => $this->kasir->id,
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        // Beli arang tunai 10 kg @ 3000 = 30000
        $this->actingAs($this->kasir)->post(route('arang.beli.store'), [
            'tanggal' => now()->toDateString(),
            'arang_jenis_id' => $this->jenisBatok->id,
            'nama_pemasok' => 'Pemasok',
            'berat_kg' => 10.0,
            'harga_beli_per_kg' => 3000,
        ]);

        // Jual arang tunai 4 kg @ 5000 = 20000
        $this->actingAs($this->kasir)->post(route('arang.jual.store'), [
            'tanggal' => now()->toDateString(),
            'arang_jenis_id' => $this->jenisBatok->id,
            'berat_kg' => 4.0,
            'harga_jual_per_kg' => 5000,
            'payment_type' => 'tunai',
            'paid' => 20000,
        ]);

        $summary = $session->fresh()->summary();

        $this->assertEquals(30000, $summary['beli_arang_tunai']);
        $this->assertEquals(20000, $summary['sales_arang_tunai']);
        // Modal awal 100.000 - beli 30.000 + jual 20.000 = 90.000
        $this->assertEquals(90000, $summary['expected_cash']);
    }

    public function test_penjualan_kasbon_arang_memerlukan_pelanggan(): void
    {
        // Isi stok 10 kg
        ArangPembelian::create([
            'tanggal' => now()->toDateString(),
            'arang_jenis_id' => $this->jenisBatok->id,
            'nama_pemasok' => 'Pemasok',
            'berat_kg' => 10.0,
            'harga_beli_per_kg' => 3000,
            'total_harga' => 30000,
            'user_id' => $this->kasir->id,
        ]);

        // Gagal tanpa customer_id
        $response = $this->actingAs($this->kasir)->post(route('arang.jual.store'), [
            'tanggal' => now()->toDateString(),
            'arang_jenis_id' => $this->jenisBatok->id,
            'berat_kg' => 2.0,
            'harga_jual_per_kg' => 5000,
            'payment_type' => 'kasbon',
            'paid' => 0,
        ]);
        $response->assertSessionHasErrors('customer_id');

        // Berhasil dengan customer_id
        $customer = Customer::create(['name' => 'Pak Budi', 'phone' => '08123456789']);
        $responseSuccess = $this->actingAs($this->kasir)->post(route('arang.jual.store'), [
            'tanggal' => now()->toDateString(),
            'arang_jenis_id' => $this->jenisBatok->id,
            'customer_id' => $customer->id,
            'berat_kg' => 2.0,
            'harga_jual_per_kg' => 5000,
            'payment_type' => 'kasbon',
            'paid' => 0,
        ]);
        $responseSuccess->assertRedirect(route('arang.index'));
        $this->assertDatabaseHas('arang_penjualan', [
            'customer_id' => $customer->id,
            'status' => 'belum_lunas',
        ]);
    }

    public function test_riwayat_arang_bisa_difilter(): void
    {
        $response = $this->actingAs($this->admin)->get(route('arang.riwayat', ['tab' => 'beli']));
        $response->assertOk();

        $responseJual = $this->actingAs($this->admin)->get(route('arang.riwayat', ['tab' => 'jual']));
        $responseJual->assertOk();
    }
}
