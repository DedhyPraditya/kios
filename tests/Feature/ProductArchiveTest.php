<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function produk(array $attrs = []): Product
    {
        return Product::create([
            'category_id' => Category::create(['name' => 'Umum'])->id,
            'name' => 'Teh Botol', 'barcode' => '899001', 'price' => 5000, 'cost' => 3500,
            'stock' => 10, 'low_stock' => 2, 'is_active' => true,
            ...$attrs,
        ]);
    }

    public function test_hapus_produk_hanya_mengarsipkan_dan_riwayat_tetap_utuh(): void
    {
        $product = $this->produk();
        $kasir = User::factory()->create(['role' => 'kasir']);
        $this->actingAs($kasir)->post(route('pos.store'), [
            'items' => [['id' => $product->id, 'qty' => 1]], 'paid' => 5000,
        ]);

        $this->actingAs($this->admin)->delete(route('products.destroy', $product))->assertRedirect();

        $this->assertSoftDeleted($product);
        $this->assertSame(1, StockMovement::where('product_id', $product->id)->count());
        $this->assertSame('Teh Botol', Sale::first()->items->first()->product->name);
    }

    public function test_produk_arsip_tidak_bisa_dijual(): void
    {
        $product = $this->produk();
        $product->delete();

        $this->actingAs(User::factory()->create(['role' => 'kasir']))->post(route('pos.store'), [
            'items' => [['id' => $product->id, 'qty' => 1]], 'paid' => 5000,
        ])->assertSessionHasErrors('items');
    }

    public function test_produk_arsip_bisa_dipulihkan(): void
    {
        $product = $this->produk();
        $product->delete();

        $this->actingAs($this->admin)->get(route('products.index', ['status' => 'terhapus']))
            ->assertInertia(fn ($page) => $page->where('products.data.0.id', $product->id));

        $this->actingAs($this->admin)->post(route('products.restore', $product->id))->assertRedirect();

        $this->assertNotSoftDeleted($product);
        $this->assertSame('899001', $product->fresh()->barcode);
    }

    public function test_barcode_produk_arsip_bisa_dipakai_produk_baru(): void
    {
        $lama = $this->produk();
        $lama->delete();

        $this->actingAs($this->admin)->post(route('products.store'), [
            'name' => 'Teh Botol Baru', 'barcode' => '899001', 'price' => 5500, 'cost' => 4000,
            'stock' => 5, 'low_stock' => 1, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Product::withTrashed()->find($lama->id)->barcode);

        // Dipulihkan tetap bisa, hanya tanpa barcode.
        $this->actingAs($this->admin)->post(route('products.restore', $lama->id));
        $this->assertNotSoftDeleted($lama);
    }

    public function test_batal_nota_mengembalikan_stok_ke_produk_arsip(): void
    {
        $product = $this->produk();
        $this->actingAs($this->admin)->post(route('pos.store'), [
            'items' => [['id' => $product->id, 'qty' => 2]], 'paid' => 10000,
        ]);
        $product->delete();

        $this->actingAs($this->admin)->post(route('sales.void', Sale::first()), ['reason' => 'Salah input'])
            ->assertSessionHasNoErrors();

        $this->assertSame(10, Product::withTrashed()->find($product->id)->stock);
    }
}
