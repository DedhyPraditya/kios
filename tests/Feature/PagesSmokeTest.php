<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_all_main_pages_render_for_admin(): void
    {
        $this->seed();
        $admin = $this->admin();

        $map = [
            'pos.index' => 'Pos/Index',
            'dashboard' => 'Dashboard',
            'products.index' => 'Products/Index',
            'categories.index' => 'Categories/Index',
            'customers.index' => 'Customers/Index',
            'piutang.index' => 'Piutang/Index',
            'reports.index' => 'Reports/Index',
            'users.index' => 'Users/Index',
            'sales.index' => 'Sales/Index',
            'stock.index' => 'Stock/Index',
            'shift.index' => 'Shift/Index',
            'settings.edit' => 'Settings/Index',
        ];

        foreach ($map as $name => $component) {
            $this->actingAs($admin)
                ->get(route($name))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    public function test_customer_detail_page_renders(): void
    {
        $this->seed();
        $admin = $this->admin();
        $customer = \App\Models\Customer::first();

        $this->actingAs($admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Customers/Show'));
    }

    public function test_sale_detail_page_renders(): void
    {
        $this->seed();
        $admin = $this->admin();
        $product = \App\Models\Product::first();

        $this->actingAs($admin)->post(route('pos.store'), [
            'items' => [['id' => $product->id, 'qty' => 2]],
            'paid' => 100000,
        ]);

        $sale = \App\Models\Sale::latest('id')->first();

        $this->actingAs($admin)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Sales/Show')
                ->has('sale.items', 1)
                ->where('sale.items.0.returnable', 2));
    }

    public function test_receipt_page_renders(): void
    {
        $this->seed();
        $kasir = User::factory()->create(['role' => 'kasir']);
        $product = \App\Models\Product::first();

        $this->actingAs($kasir)->post(route('pos.store'), [
            'items' => [['id' => $product->id, 'qty' => 1]],
            'paid' => 50000,
        ]);

        $sale = \App\Models\Sale::latest('id')->first();

        $this->actingAs($kasir)
            ->get(route('pos.receipt', $sale))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Pos/Receipt')
                ->has('sale.items', 1));
    }
}
