<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_lifecycle_records_activity_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cat = Category::create(['name' => 'Minuman']);

        // 1. Create product
        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Teh Botol',
            'category_id' => $cat->id,
            'price' => 4000,
            'cost' => 3000,
            'stock' => 24,
            'low_stock' => 5,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'product.create',
            'user_id' => $admin->id,
        ]);

        $product = Product::where('name', 'Teh Botol')->first();

        // 2. Update price
        $this->actingAs($admin)->patch(route('products.update', $product), [
            'name' => 'Teh Botol 250ml',
            'category_id' => $cat->id,
            'price' => 4500,
            'cost' => 3200,
            'stock' => 24,
            'low_stock' => 5,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'product.update',
            'user_id' => $admin->id,
        ]);

        // 3. Delete product
        $this->actingAs($admin)->delete(route('products.destroy', $product))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'product.delete',
            'user_id' => $admin->id,
        ]);
    }

    public function test_sale_void_records_activity_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = Sale::create([
            'invoice_no' => 'INV-TEST-VOID',
            'user_id' => $admin->id,
            'payment_type' => 'tunai',
            'status' => 'lunas',
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'paid' => 50000,
            'change' => 0,
        ]);

        $this->actingAs($admin)->post(route('sales.void', $sale), [
            'reason' => 'Pelanggan membatalkan pesanan',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'sale.void',
            'user_id' => $admin->id,
        ]);
    }

    public function test_credit_payment_records_activity_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create(['name' => 'Ibu Siti', 'credit_limit' => 200000]);
        $sale = Sale::create([
            'invoice_no' => 'INV-TEST-DEBT',
            'user_id' => $admin->id,
            'customer_id' => $customer->id,
            'payment_type' => 'kasbon',
            'status' => 'belum_lunas',
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'paid' => 0,
            'change' => 0,
        ]);

        $this->actingAs($admin)->post(route('credit-payments.store'), [
            'customer_id' => $customer->id,
            'amount' => 50000,
            'note' => 'Pelunasan lunas',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'credit.payment',
            'user_id' => $admin->id,
        ]);
    }

    public function test_audit_logs_page_access_control(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kasir = User::factory()->create(['role' => 'kasir']);

        // Admin can access
        $this->actingAs($admin)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ActivityLog/Index'));

        // Kasir cannot access
        $this->actingAs($kasir)
            ->get(route('audit-logs.index'))
            ->assertForbidden();
    }
}
