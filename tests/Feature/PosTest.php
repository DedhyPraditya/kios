<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
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

        $cat = Category::create(['name' => 'Test']);
        $this->product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Kopi',
            'barcode' => '123',
            'price' => 5000,
            'cost' => 3000,
            'stock' => 10,
            'low_stock' => 2,
            'is_active' => true,
        ]);
    }

    public function test_records_a_sale_and_decrements_stock(): void
    {
        $this->actingAs($this->kasir)
            ->post(route('pos.store'), [
                'items' => [['id' => $this->product->id, 'qty' => 3]],
                'discount' => 1000,
                'paid' => 20000,
            ])
            ->assertRedirect();

        $this->assertSame(7, $this->product->fresh()->stock);

        $sale = $this->kasir->sales()->with('items')->first();
        $this->assertSame(15000, $sale->subtotal);
        $this->assertSame(14000, $sale->total);
        $this->assertSame(6000, $sale->change);
        $this->assertCount(1, $sale->items);
    }

    public function test_rejects_sale_when_stock_insufficient(): void
    {
        $this->actingAs($this->kasir)
            ->post(route('pos.store'), [
                'items' => [['id' => $this->product->id, 'qty' => 99]],
                'paid' => 999999,
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_rejects_sale_when_payment_is_short(): void
    {
        $this->actingAs($this->kasir)
            ->post(route('pos.store'), [
                'items' => [['id' => $this->product->id, 'qty' => 1]],
                'paid' => 100,
            ])
            ->assertSessionHasErrors('paid');
    }

    public function test_blocks_kasir_from_admin_pages(): void
    {
        $this->actingAs($this->kasir)->get(route('products.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('products.index'))->assertOk();
    }
}
