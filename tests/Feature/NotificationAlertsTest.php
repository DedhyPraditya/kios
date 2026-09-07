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
}
