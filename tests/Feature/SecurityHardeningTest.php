<?php

namespace Tests\Feature;

use App\Models\ArangJenis;
use App\Models\ArangPembelian;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const RUMUS_JAHAT = '=HYPERLINK("http://contoh.invalid","klik")';

    private function gagalLogin(User $user, string $ip, array $headers = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders($headers)
            ->post('/login', ['email' => $user->email, 'password' => 'salah-terus']);
    }

    public function test_header_x_forwarded_for_palsu_tidak_bisa_mengakali_batas_login(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->gagalLogin($user, '10.0.0.1', ['X-Forwarded-For' => "203.0.113.{$i}"]);
        }

        $this->gagalLogin($user, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.99'])
            ->assertSessionHasErrors('email');
        $this->assertNotSame(trans('auth.failed'), session('errors')->first('email'), 'Seharusnya pesan batas percobaan, bukan salah sandi.');
    }

    public function test_akun_terkunci_setelah_banyak_gagal_dari_banyak_ip(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 20; $i++) {
            $this->gagalLogin($user, "10.0.1.{$i}");
        }

        // Kata sandi benar dari IP baru pun ditahan sampai jeda habis.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.1'])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_terakhir_tidak_bisa_diturunkan_atau_dihapus(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'kasir',
        ])->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);

        // Admin kedua mencoba menghapus admin terakhir lain: setelah admin
        // pertama diturunkan jadi kasir oleh admin kedua, admin kedua tersisa.
        $admin2 = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin2)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'kasir',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin2)->delete(route('users.destroy', $admin2))
            ->assertSessionHasErrors('user');
        $this->assertModelExists($admin2);
    }

    public function test_produk_nonaktif_ditolak_walau_dikirim_manual(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $produk = Product::create([
            'category_id' => Category::create(['name' => 'Umum'])->id,
            'name' => 'Barang Ditarik',
            'price' => 5000,
            'cost' => 3000,
            'stock' => 10,
            'low_stock' => 1,
            'is_active' => false,
        ]);

        $this->actingAs($kasir)->post(route('pos.store'), [
            'items' => [['id' => $produk->id, 'qty' => 1]],
            'paid' => 5000,
        ])->assertSessionHasErrors('items');

        $this->assertSame(10, $produk->fresh()->stock);
    }

    public function test_jual_arang_menolak_pelanggan_diblokir_dan_jenis_nonaktif(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $jenis = ArangJenis::create([
            'nama' => 'Batok', 'harga_beli_default' => 3000, 'harga_jual_default' => 5000, 'aktif' => true,
        ]);
        ArangPembelian::create([
            'tanggal' => now()->toDateString(), 'arang_jenis_id' => $jenis->id, 'nama_pemasok' => 'Pak A',
            'berat_kg' => 10, 'harga_beli_per_kg' => 3000, 'total_harga' => 30000, 'user_id' => $kasir->id,
        ]);
        $diblokir = Customer::create(['name' => 'Pak Nakal', 'is_blocked' => true]);

        $jual = fn (array $extra) => $this->actingAs($kasir)->post(route('arang.jual.store'), [
            'tanggal' => now()->toDateString(), 'arang_jenis_id' => $jenis->id, 'berat_kg' => 1,
            'harga_jual_per_kg' => 5000, 'payment_type' => 'kasbon', 'paid' => 0, ...$extra,
        ]);

        $jual(['customer_id' => $diblokir->id])->assertSessionHasErrors('customer_id');

        $jenis->update(['aktif' => false]);
        $jual(['customer_id' => Customer::create(['name' => 'Bu B'])->id])->assertSessionHasErrors('arang_jenis_id');

        $this->assertDatabaseCount('arang_penjualan', 0);
    }

    public function test_dp_kasbon_arang_tidak_melebihi_total(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $jenis = ArangJenis::create([
            'nama' => 'Batok', 'harga_beli_default' => 3000, 'harga_jual_default' => 5000, 'aktif' => true,
        ]);
        ArangPembelian::create([
            'tanggal' => now()->toDateString(), 'arang_jenis_id' => $jenis->id, 'nama_pemasok' => 'Pak A',
            'berat_kg' => 10, 'harga_beli_per_kg' => 3000, 'total_harga' => 30000, 'user_id' => $kasir->id,
        ]);

        $this->actingAs($kasir)->post(route('arang.jual.store'), [
            'tanggal' => now()->toDateString(), 'arang_jenis_id' => $jenis->id, 'berat_kg' => 2,
            'harga_jual_per_kg' => 5000, 'payment_type' => 'kasbon', 'paid' => 50000,
            'customer_id' => Customer::create(['name' => 'Bu B'])->id,
        ]);

        $this->assertDatabaseHas('arang_penjualan', ['grand_total' => 10000, 'paid' => 10000, 'status' => 'lunas']);
    }

    private function pembelianBerumus(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $jenis = ArangJenis::create([
            'nama' => 'Batok', 'harga_beli_default' => 3000, 'harga_jual_default' => 5000, 'aktif' => true,
        ]);
        ArangPembelian::create([
            'tanggal' => now()->toDateString(), 'arang_jenis_id' => $jenis->id,
            'nama_pemasok' => self::RUMUS_JAHAT, 'berat_kg' => 1, 'harga_beli_per_kg' => 3000,
            'total_harga' => 3000, 'user_id' => $admin->id,
        ]);

        return $admin;
    }

    public function test_ekspor_csv_menetralkan_rumus_dari_input_pengguna(): void
    {
        $admin = $this->pembelianBerumus();

        $response = $this->actingAs($admin)->get(route('reports.export.csv'));
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK', $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_ekspor_excel_menulis_input_pengguna_sebagai_teks(): void
    {
        $admin = $this->pembelianBerumus();

        $response = $this->actingAs($admin)->get(route('reports.export.excel'));
        ob_start();
        $response->sendContent();
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, ob_get_clean());

        $found = false;
        foreach (IOFactory::load($path)->getAllSheets() as $sheet) {
            foreach ($sheet->getCellCollection()->getCoordinates() as $coord) {
                $cell = $sheet->getCell($coord);
                if ($cell->getValue() === self::RUMUS_JAHAT) {
                    $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
                    $found = true;
                }
            }
        }
        unlink($path);

        $this->assertTrue($found, 'Nama pemasok tidak ditemukan di berkas Excel.');
    }
}
