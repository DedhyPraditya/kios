<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QrisTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private User $admin;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::factory()->create(['role' => 'kasir']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        $cat = Category::create(['name' => 'Minuman']);
        $this->product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Kopi Susu',
            'barcode' => 'KOPI01',
            'price' => 15000,
            'cost' => 10000,
            'stock' => 20,
            'low_stock' => 5,
            'is_active' => true,
        ]);
    }

    public function test_qris_image_upload_and_removal_in_settings(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('qris_toko.png', 400, 400);

        $response = $this->actingAs($this->admin)
            ->post(route('settings.update'), [
                'store_name' => 'Kios BERKAH',
                'store_address' => 'Jl. Test No. 1',
                'store_phone' => '08123456789',
                'receipt_footer' => 'Terima kasih',
                'qris_image' => $file,
            ]);

        $response->assertRedirect();
        $storedImage = Setting::get('qris_image');
        $this->assertNotEmpty($storedImage);
        Storage::disk('public')->assertExists($storedImage);

        // Test remove QRIS
        $responseRemove = $this->actingAs($this->admin)
            ->post(route('settings.update'), [
                'store_name' => 'Kios BERKAH',
                'remove_qris' => 1,
            ]);

        $responseRemove->assertRedirect();
        $this->assertEmpty(Setting::get('qris_image'));
        Storage::disk('public')->assertMissing($storedImage);
    }

    public function test_rejects_qris_sale_when_qris_not_uploaded(): void
    {
        // Pastikan QRIS belum diunggah
        Setting::put(['qris_image' => null]);

        $response = $this->actingAs($this->kasir)
            ->post(route('pos.store'), [
                'items' => [['id' => $this->product->id, 'qty' => 1]],
                'paid' => 15000,
                'payment_type' => 'qris',
            ]);

        $response->assertSessionHasErrors('payment_type');
        $this->assertSame(20, $this->product->fresh()->stock);
    }

    public function test_successful_qris_sale_when_qris_uploaded(): void
    {
        Setting::put(['qris_image' => 'qris/dummy.png']);

        $response = $this->actingAs($this->kasir)
            ->post(route('pos.store'), [
                'items' => [['id' => $this->product->id, 'qty' => 2]],
                'discount' => 5000,
                'paid' => 25000,
                'payment_type' => 'qris',
            ]);

        $response->assertRedirect();

        $this->assertSame(18, $this->product->fresh()->stock);

        $sale = Sale::latest()->first();
        $this->assertSame('qris', $sale->payment_type);
        $this->assertSame('lunas', $sale->status);
        $this->assertSame(30000, $sale->subtotal);
        $this->assertSame(5000, $sale->discount);
        $this->assertSame(25000, $sale->total);
        $this->assertSame(25000, $sale->paid);
        $this->assertSame(0, $sale->change);
    }

    public function test_qris_sale_in_shift_summary(): void
    {
        Setting::put(['qris_image' => 'qris/dummy.png']);

        // Buka shift dengan modal 100.000
        $shiftResponse = $this->actingAs($this->kasir)->post(route('shift.store'), [
            'opening_cash' => 100000,
        ]);
        $shiftResponse->assertRedirect();

        // Lakukan penjualan QRIS 25.000
        $this->actingAs($this->kasir)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 1]],
            'paid' => 15000,
            'payment_type' => 'qris',
        ]);

        $shift = \App\Models\CashSession::openFor($this->kasir);
        $summary = $shift->summary();

        $this->assertSame(15000, $summary['sales_qris']);
        $this->assertSame(0, $summary['sales_tunai']);
        // Uang fisik di laci tetap 100.000 (modal awal) karena QRIS adalah non-tunai
        $this->assertSame(100000, $summary['expected_cash']);
    }

    public function test_filter_sales_by_qris(): void
    {
        Setting::put(['qris_image' => 'qris/dummy.png']);

        // Transaksi tunai
        $this->actingAs($this->kasir)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 1]],
            'paid' => 15000,
            'payment_type' => 'tunai',
        ]);

        // Transaksi QRIS
        $this->actingAs($this->kasir)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 1]],
            'paid' => 15000,
            'payment_type' => 'qris',
        ]);

        $response = $this->actingAs($this->admin)->get(route('sales.index', ['status' => 'qris']));
        $response->assertOk();
    }
}
