<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receives_low_stock_and_due_debts_alerts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cat = Category::create(['name' => 'Sembako']);

        // 1 normal product, 1 low stock product
        Product::create([
            'category_id' => $cat->id,
            'name' => 'Beras Normal',
            'price' => 15000,
            'cost' => 12000,
            'stock' => 50,
            'low_stock' => 10,
        ]);

        Product::create([
            'category_id' => $cat->id,
            'name' => 'Gula Menipis',
            'price' => 14000,
            'cost' => 11000,
            'stock' => 3,
            'low_stock' => 5,
        ]);

        // Customer with a due kasbon
        $customer = Customer::create(['name' => 'Pak Budi', 'credit_limit' => 500000]);
        Sale::create([
            'invoice_no' => 'INV-TEST-001',
            'user_id' => $admin->id,
            'customer_id' => $customer->id,
            'payment_type' => 'kasbon',
            'status' => 'belum_lunas',
            'subtotal' => 100000,
            'discount' => 0,
            'total' => 100000,
            'paid' => 20000,
            'change' => 0,
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('alerts.lowStockCount')
                ->where('alerts.lowStockCount', 1)
                ->has('alerts.dueDebtsCount')
                ->where('alerts.dueDebtsCount', 1)
                ->where('alerts.total', 2)
            );
    }

    public function test_kasir_does_not_receive_admin_alerts(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($kasir)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('alerts', null)
            );
    }

    public function test_admin_can_dismiss_single_product_alert_and_smart_realert_triggers_on_further_stock_drop(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cat = Category::create(['name' => 'Sembako']);

        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Minyak Goreng 1L',
            'price' => 18000,
            'cost' => 15000,
            'stock' => 3,
            'low_stock' => 5,
        ]);

        // Initially 1 alert
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('alerts.lowStockCount', 1));

        // Dismiss the alert at stock = 3
        $this->actingAs($admin)
            ->post(route('alerts.dismiss'), [
                'type' => 'product_stock',
                'id' => $product->id,
                'value' => 3,
            ])
            ->assertRedirect();

        // Alert is now dismissed (count 0)
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('alerts.lowStockCount', 0));

        // Stock drops further (e.g. sale occurs, stock becomes 2)
        $product->update(['stock' => 2]);

        // Alert should re-appear automatically because stock (2) < last_value (3)
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('alerts.lowStockCount', 1));
    }

    public function test_admin_can_dismiss_due_debt_alert(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create(['name' => 'Bu Siti', 'credit_limit' => 500000]);

        $sale = Sale::create([
            'invoice_no' => 'INV-TEST-002',
            'user_id' => $admin->id,
            'customer_id' => $customer->id,
            'payment_type' => 'kasbon',
            'status' => 'belum_lunas',
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'paid' => 0,
            'change' => 0,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        // Initially 1 due debt alert
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('alerts.dueDebtsCount', 1));

        // Dismiss this debt alert
        $this->actingAs($admin)
            ->post(route('alerts.dismiss'), [
                'type' => 'sale_due',
                'id' => $sale->id,
            ])
            ->assertRedirect();

        // Should now be 0
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('alerts.dueDebtsCount', 0));
    }

    public function test_admin_can_dismiss_all_alerts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cat = Category::create(['name' => 'Sembako']);

        Product::create([
            'category_id' => $cat->id,
            'name' => 'Gula Pasir',
            'price' => 14000,
            'cost' => 11000,
            'stock' => 2,
            'low_stock' => 5,
        ]);

        $customer = Customer::create(['name' => 'Pak Joko', 'credit_limit' => 500000]);
        Sale::create([
            'invoice_no' => 'INV-TEST-003',
            'user_id' => $admin->id,
            'customer_id' => $customer->id,
            'payment_type' => 'kasbon',
            'status' => 'belum_lunas',
            'subtotal' => 75000,
            'discount' => 0,
            'total' => 75000,
            'paid' => 0,
            'change' => 0,
            'due_date' => now()->toDateString(),
        ]);

        // Initially total alerts is 2
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('alerts.total', 2));

        // Dismiss all
        $this->actingAs($admin)
            ->post(route('alerts.dismiss'), [
                'dismiss_all' => true,
            ])
            ->assertRedirect();

        // Both should be cleared
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('alerts.lowStockCount', 0)
                ->where('alerts.dueDebtsCount', 0)
                ->where('alerts.total', 0)
            );
    }
}

