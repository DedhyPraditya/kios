<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AktivitasLoncengTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Pemilik']);
        $this->kasir = User::factory()->create(['role' => 'kasir', 'name' => 'Budi', 'email' => 'budi@kios.test']);
    }

    private function lonceng(): array
    {
        $alerts = null;
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertInertia(function ($page) use (&$alerts) {
                $alerts = $page->toArray()['props']['alerts'];
            });

        return $alerts;
    }

    public function test_login_berhasil_dan_keluar_tercatat(): void
    {
        $this->post('/login', ['email' => 'budi@kios.test', 'password' => 'password']);
        $this->post('/logout');

        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login', 'user_id' => $this->kasir->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.logout', 'user_id' => $this->kasir->id]);
    }

    public function test_login_gagal_tercatat_sebagai_kejadian_keamanan(): void
    {
        $this->post('/login', ['email' => 'budi@kios.test', 'password' => 'salah']);
        $this->post('/login', ['email' => 'penyusup@luar.test', 'password' => 'tebak']);

        $gagal = ActivityLog::where('action', 'auth.failed')->get();
        $this->assertCount(2, $gagal);
        $this->assertNull($gagal[0]->user_id);
        $this->assertStringContainsString('kata sandi salah', $gagal[0]->description);
        $this->assertStringContainsString('penyusup@luar.test', $gagal[1]->description);
        $this->assertNotNull($gagal[1]->ip_address);

        $alerts = $this->lonceng();
        $this->assertSame(2, $alerts['securityCount']);
        $this->assertTrue($alerts['activityItems'][0]['security']);
    }

    public function test_login_dikunci_tercatat(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'budi@kios.test', 'password' => 'salah']);
        }

        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.lockout']);
    }

    public function test_kasir_membuka_halaman_admin_tercatat(): void
    {
        $this->actingAs($this->kasir)->get(route('reports.index'))->assertForbidden();

        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.forbidden', 'user_id' => $this->kasir->id]);
    }

    public function test_penjualan_kasir_muncul_di_lonceng_admin(): void
    {
        $produk = Product::create([
            'category_id' => Category::create(['name' => 'Umum'])->id,
            'name' => 'Teh', 'price' => 5000, 'cost' => 3000, 'stock' => 10, 'low_stock' => 1,
        ]);
        $this->actingAs($this->kasir)->post(route('pos.store'), [
            'items' => [['id' => $produk->id, 'qty' => 2]], 'paid' => 10000,
        ]);

        $alerts = $this->lonceng();
        $this->assertSame(1, $alerts['activityCount']);
        $this->assertSame('sale.create', $alerts['activityItems'][0]['action']);
        $this->assertSame('Budi', $alerts['activityItems'][0]['user']);
        $this->assertTrue($alerts['activityItems'][0]['new']);
    }

    public function test_kegiatan_admin_sendiri_tidak_dihitung_baru(): void
    {
        $this->actingAs($this->admin)->post(route('categories.store'), ['name' => 'Minuman']);

        $alerts = $this->lonceng();
        $this->assertSame(0, $alerts['activityCount']);
        $this->assertSame('category.create', $alerts['activityItems'][0]['action']);
    }

    public function test_tandai_aktivitas_dibaca(): void
    {
        $this->actingAs($this->kasir)->post(route('shift.store'), ['opening_cash' => 100000]);
        $this->assertSame(1, $this->lonceng()['activityCount']);

        $this->travel(1)->seconds();
        $this->actingAs($this->admin)->post(route('alerts.activity.seen'))->assertRedirect();

        $this->assertSame(0, $this->lonceng()['activityCount']);
    }

    public function test_kasir_tidak_menerima_dan_tidak_bisa_menandai_aktivitas(): void
    {
        $this->actingAs($this->kasir)->post(route('alerts.activity.seen'))->assertForbidden();
    }

    public function test_aktivitas_penting_lain_tercatat(): void
    {
        $this->actingAs($this->admin)->post(route('customers.store'), ['name' => 'Bu Siti']);
        $this->actingAs($this->admin)->get(route('reports.export.csv'))->streamedContent();
        $this->actingAs($this->kasir)->post(route('shift.store'), ['opening_cash' => 50000]);

        foreach (['customer.create', 'report.export', 'shift.open'] as $aksi) {
            $this->assertDatabaseHas('activity_logs', ['action' => $aksi]);
        }
    }

    public function test_pesan_lonceng_diringkas_supaya_mudah_dibaca(): void
    {
        $produk = Product::create([
            'category_id' => Category::create(['name' => 'Umum'])->id,
            'name' => 'Teh', 'price' => 5000, 'cost' => 3000, 'stock' => 10, 'low_stock' => 1,
        ]);
        $this->actingAs($this->kasir)->post(route('pos.store'), [
            'items' => [['id' => $produk->id, 'qty' => 2]], 'paid' => 10000,
        ]);
        $this->actingAs($this->kasir)->get(route('reports.index'));
        $this->post('/logout');
        $this->post('/login', ['email' => 'budi@kios.test', 'password' => 'salah']);
        $this->post('/login', ['email' => 'maling@luar.test', 'password' => 'x']);

        $items = collect($this->lonceng()['activityItems'])->keyBy('action');

        $jual = $items['sale.create'];
        $this->assertSame('Penjualan', $jual['title']);
        $this->assertSame(10000, $jual['amount']);
        $this->assertSame('in', $jual['flow']);
        $this->assertStringContainsString('Tunai', $jual['detail']);
        $this->assertStringContainsString('/sales/', $jual['url']);

        $this->assertSame('Akses ditolak', $items['auth.forbidden']['title']);
        $this->assertSame('mencoba membuka halaman Laporan', $items['auth.forbidden']['detail']);

        $gagal = collect($this->lonceng()['activityItems'])->where('action', 'auth.failed')->pluck('title')->all();
        $this->assertEqualsCanonicalizing(['Salah kata sandi', 'Email tak terdaftar'], $gagal);
    }
}
