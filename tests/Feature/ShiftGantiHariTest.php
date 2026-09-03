<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShiftGantiHariTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::factory()->create(['role' => 'kasir']);
        $this->product = Product::create([
            'name' => 'Teh Botol 350ml',
            'price' => 4500,
            'cost' => 3500,
            'stock' => 40,
            'low_stock' => 5,
            'is_active' => true,
        ]);
    }

    private function bukaShiftDanJual(int $modal, int $qty): CashSession
    {
        $this->actingAs($this->kasir)->post(route('shift.store'), ['opening_cash' => $modal]);

        $this->actingAs($this->kasir)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => $qty]],
            'paid' => 50000,
        ]);

        return CashSession::where('user_id', $this->kasir->id)->latest('id')->firstOrFail();
    }

    public function test_shift_yang_menginap_terkunci_sendiri_saat_ganti_hari(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 21:00'));
        $shift = $this->bukaShiftDanJual(100000, 2); // tunai 9.000

        Carbon::setTestNow(Carbon::parse('2026-09-11 07:30'));

        $this->assertNull(CashSession::openFor($this->kasir));

        $shift->refresh();

        $this->assertTrue($shift->auto_closed);
        $this->assertNull($shift->closed_by);
        $this->assertSame('2026-09-10 23:59:59', $shift->closed_at->format('Y-m-d H:i:s'));
        $this->assertSame(109000, $shift->expected_cash); // modal + penjualan tunai

        // Uang fisik tak pernah dihitung, jadi jangan mengaku pas.
        $this->assertNull($shift->counted_cash);
        $this->assertNull($shift->difference);
        $this->assertStringContainsString('Ditutup otomatis', $shift->note);
    }

    public function test_hari_baru_mulai_dari_nol_dan_shift_baru_bisa_dibuka(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 20:00'));
        $kemarin = $this->bukaShiftDanJual(100000, 4);

        Carbon::setTestNow(Carbon::parse('2026-09-11 08:00'));

        $this->actingAs($this->kasir)
            ->post(route('shift.store'), ['opening_cash' => 50000])
            ->assertSessionHasNoErrors();

        $baru = CashSession::openFor($this->kasir);

        $this->assertNotNull($baru);
        $this->assertNotSame($kemarin->id, $baru->id);

        $rekap = $baru->summary();

        $this->assertSame(0, $rekap['trx_count']);
        $this->assertSame(0, $rekap['sales_tunai']);
        $this->assertSame(50000, $rekap['expected_cash']); // hanya modal awal
    }

    public function test_penjualan_setelah_tengah_malam_tidak_masuk_rekap_kemarin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 22:00'));
        $kemarin = $this->bukaShiftDanJual(0, 2); // tunai 9.000

        Carbon::setTestNow(Carbon::parse('2026-09-11 06:10'));

        // Shift opsional: jualan tetap jalan walau belum membuka shift baru.
        $this->actingAs($this->kasir)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 1]],
            'paid' => 5000,
        ])->assertSessionHasNoErrors();

        $notaBaru = Sale::latest('id')->firstOrFail();

        $this->assertNull($notaBaru->cash_session_id);
        $this->assertSame(1, $kemarin->fresh()->sales()->count());
        $this->assertSame(9000, $kemarin->fresh()->expected_cash);
    }

    public function test_nota_menempel_ke_shift_yang_kebetulan_terbuka(): void
    {
        $this->actingAs($this->kasir)->post(route('shift.store'), ['opening_cash' => 20000]);

        $this->actingAs($this->kasir)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 2]],
            'paid' => 10000,
        ])->assertSessionHasNoErrors();

        $shift = CashSession::openFor($this->kasir);

        $this->assertSame($shift->id, Sale::latest('id')->firstOrFail()->cash_session_id);
        $this->assertSame(29000, $shift->summary()['expected_cash']); // 20.000 + 9.000
    }

    public function test_perintah_terjadwal_menutup_shift_semua_kasir(): void
    {
        $kasirLain = User::factory()->create(['role' => 'kasir']);

        Carbon::setTestNow(Carbon::parse('2026-09-10 19:00'));
        $this->bukaShiftDanJual(75000, 1);
        $this->actingAs($kasirLain)->post(route('shift.store'), ['opening_cash' => 25000]);

        Carbon::setTestNow(Carbon::parse('2026-09-11 00:01'));

        $this->artisan('shift:tutup-otomatis')
            ->expectsOutputToContain('2 shift ditutup otomatis')
            ->assertSuccessful();

        $this->assertSame(0, CashSession::open()->count());
        $this->assertSame(2, CashSession::where('auto_closed', true)->count());
    }

    public function test_shift_hari_ini_tidak_ikut_ditutup(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 08:00'));
        $shift = $this->bukaShiftDanJual(50000, 1);

        Carbon::setTestNow(Carbon::parse('2026-09-10 23:58'));

        $this->artisan('shift:tutup-otomatis')
            ->expectsOutputToContain('Tidak ada shift yang menginap')
            ->assertSuccessful();

        $this->assertTrue($shift->fresh()->isOpen());
        $this->assertNotNull(CashSession::openFor($this->kasir));
    }

    public function test_halaman_tutup_kasir_memberi_tahu_shift_yang_dikunci_sistem(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 20:00'));
        $this->bukaShiftDanJual(100000, 2);

        Carbon::setTestNow(Carbon::parse('2026-09-11 08:00'));

        $this->actingAs($this->kasir)
            ->get(route('shift.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Shift/Index')
                ->where('current', null)
                ->where('autoClosed.expected_cash', 109000)
                ->has('history.data.0', fn ($row) => $row
                    ->where('auto_closed', true)
                    ->where('counted_cash', null)
                    ->where('difference', null)
                    ->etc()));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
