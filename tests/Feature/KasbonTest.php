<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasbonTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private User $admin;
    private Customer $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::factory()->create(['role' => 'kasir']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = Customer::create(['name' => 'Bu Sari', 'credit_limit' => 100000]);
        $this->product = Product::create([
            'name' => 'Beras 5kg',
            'price' => 60000,
            'cost' => 52000,
            'stock' => 20,
            'low_stock' => 3,
            'is_active' => true,
        ]);
    }

    private function sell(array $override = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->kasir)->post(route('pos.store'), array_merge([
            'items' => [['id' => $this->product->id, 'qty' => 1]],
            'paid' => 0,
            'payment_type' => 'kasbon',
            'customer_id' => $this->customer->id,
        ], $override));
    }

    public function test_kasbon_sale_creates_unpaid_debt_and_decrements_stock(): void
    {
        $this->sell()->assertRedirect();

        $sale = Sale::first();
        $this->assertSame('kasbon', $sale->payment_type);
        $this->assertSame('belum_lunas', $sale->status);
        $this->assertSame(60000, $sale->outstanding());
        $this->assertSame(19, $this->product->fresh()->stock);
        $this->assertSame(60000, $this->customer->fresh()->outstanding());
    }

    public function test_kasbon_with_dp_leaves_remaining_debt(): void
    {
        $this->sell(['paid' => 20000])->assertRedirect();

        $sale = Sale::first();
        $this->assertSame(20000, $sale->paid);
        $this->assertSame(40000, $sale->outstanding());
    }

    public function test_kasbon_requires_customer(): void
    {
        $this->sell(['customer_id' => null])->assertSessionHasErrors('customer_id');
        $this->assertSame(0, Sale::count());
    }

    public function test_kasbon_rejected_over_credit_limit(): void
    {
        // limit 100000, harga 60000 x 2 = 120000
        $this->sell(['items' => [['id' => $this->product->id, 'qty' => 2]]])
            ->assertSessionHasErrors('customer_id');
        $this->assertSame(0, Sale::count());
        $this->assertSame(20, $this->product->fresh()->stock);
    }

    public function test_kasbon_rejected_for_blocked_customer(): void
    {
        $this->customer->update(['is_blocked' => true]);
        $this->sell()->assertSessionHasErrors('customer_id');
    }

    public function test_credit_payment_settles_debt_fifo_and_marks_lunas(): void
    {
        $this->customer->update(['credit_limit' => null]); // tanpa batas untuk skenario ini
        $this->sell();                  // nota 1: hutang 60000
        $this->sell(['paid' => 10000]); // nota 2: hutang 50000

        $this->assertSame(110000, $this->customer->fresh()->outstanding());

        // Bayar 60000: melunasi nota pertama (60000), sisa 0 ke nota kedua.
        $this->actingAs($this->admin)->post(route('credit-payments.store'), [
            'customer_id' => $this->customer->id,
            'amount' => 60000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(50000, $this->customer->fresh()->outstanding());

        $first = Sale::orderBy('id')->first();
        $this->assertSame('lunas', $first->fresh()->status);

        // Lunasi sisanya.
        $this->actingAs($this->admin)->post(route('credit-payments.store'), [
            'customer_id' => $this->customer->id,
            'amount' => 50000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->customer->fresh()->outstanding());
        $this->assertSame(0, Sale::unpaid()->count());
    }

    public function test_credit_payment_cannot_exceed_outstanding(): void
    {
        $this->sell(); // hutang 60000

        $this->actingAs($this->admin)->post(route('credit-payments.store'), [
            'customer_id' => $this->customer->id,
            'amount' => 999999,
        ])->assertSessionHasErrors('amount');

        $this->assertSame(60000, $this->customer->fresh()->outstanding());
    }
}
