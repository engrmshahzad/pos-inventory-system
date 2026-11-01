<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleFlowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $hyd;
    private Branch $khi;
    private User $cashier;
    private Product $tea;
    private Product $milk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hyd = Branch::factory()->create(['code' => 'HYD', 'name' => 'Hyderabad']);
        $this->khi = Branch::factory()->create(['code' => 'KHI', 'name' => 'Karachi']);
        $this->cashier = $this->makeUser('cashier', $this->hyd);

        $pcs = Unit::factory()->create(['short_name' => 'pcs']);
        $products = app(ProductService::class);
        $this->tea = $products->create(['name' => 'Tapal 950g', 'unit_id' => $pcs->id, 'purchase_price' => 1480, 'sale_price' => 1650, 'is_active' => true],
            null, [$this->hyd->id => 10, $this->khi->id => 10]);
        $this->milk = $products->create(['name' => 'Olpers 1L', 'unit_id' => $pcs->id, 'purchase_price' => 290, 'sale_price' => 330, 'is_active' => true],
            null, [$this->hyd->id => 5, $this->khi->id => 5]);
    }

    private function openShift(?User $user = null, ?Branch $branch = null): CashRegister
    {
        return app(CashRegisterService::class)->open($user ?? $this->cashier, ($branch ?? $this->hyd)->id, 1000);
    }

    private function checkout(array $data, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->cashier)->postJson('/pos/checkout', $data);
    }

    private function qty(Product $product, Branch $branch): float
    {
        return (float) $product->stocks()->where('branch_id', $branch->id)->value('quantity');
    }

    public function test_cash_sale_reduces_stock_in_the_right_branch_and_records_everything(): void
    {
        $this->openShift();

        $response = $this->checkout([
            'items' => [['product_id' => $this->tea->id, 'quantity' => 2], ['product_id' => $this->milk->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 5000]],
        ])->assertOk();

        $response->assertJsonPath('invoice_no', 'HYD-S-000001')
            ->assertJsonPath('grand_total', 3630)
            ->assertJsonPath('change', 1370);

        $this->assertSame(8.0, $this->qty($this->tea, $this->hyd));
        $this->assertSame(10.0, $this->qty($this->tea, $this->khi)); // other branch untouched
        $this->assertSame(4.0, $this->qty($this->milk, $this->hyd));

        $sale = Sale::first();
        $this->assertSame('paid', $sale->payment_status);
        $this->assertEquals(1480, $sale->items->firstWhere('product_id', $this->tea->id)->purchase_price);
        $this->assertEquals(3630, $sale->payments->sum('amount')); // cash stored net of change
        $this->assertDatabaseHas('stock_movements', ['type' => 'sale', 'reference_no' => 'HYD-S-000001', 'quantity' => -2]);
    }

    public function test_cost_snapshot_survives_price_change(): void
    {
        $this->openShift();
        $this->checkout(['items' => [['product_id' => $this->tea->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 1650]]])->assertOk();

        $this->tea->update(['purchase_price' => 2000]);

        $this->assertEquals(170, Sale::first()->load('items')->profit);
    }

    public function test_credit_sale_adds_due_to_customer(): void
    {
        $this->openShift();
        $customer = Customer::factory()->create(['balance' => 500]);

        $this->checkout([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $this->tea->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 1000]],
        ])->assertOk()->assertJsonPath('due', 2300);

        $this->assertEquals(2800, $customer->fresh()->balance);
        $this->assertSame('partial', Sale::first()->payment_status);
    }

    public function test_credit_for_walk_in_is_rejected(): void
    {
        $this->openShift();

        $this->checkout(['items' => [['product_id' => $this->tea->id, 'quantity' => 1]], 'payments' => []])
            ->assertStatus(422)->assertJsonValidationErrors('payments');

        $this->assertSame(10.0, $this->qty($this->tea, $this->hyd));
    }

    public function test_insufficient_stock_rolls_back_the_whole_sale(): void
    {
        $this->openShift();

        $this->checkout([
            'items' => [['product_id' => $this->tea->id, 'quantity' => 1], ['product_id' => $this->milk->id, 'quantity' => 6]],
            'payments' => [['method' => 'cash', 'amount' => 99999]],
        ])->assertStatus(422)->assertJsonFragment(['message' => 'Insufficient stock for "Olpers 1L" at Hyderabad: available 5, requested 6.']);

        $this->assertSame(10.0, $this->qty($this->tea, $this->hyd));
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cashier_cannot_override_price_or_discount(): void
    {
        $this->openShift();

        $this->checkout([
            'items' => [['product_id' => $this->tea->id, 'quantity' => 1, 'unit_price' => 1, 'discount' => 500]],
            'discount_value' => 1000,
            'payments' => [['method' => 'cash', 'amount' => 1650]],
        ])->assertOk()->assertJsonPath('grand_total', 1650);
    }

    public function test_manager_can_discount(): void
    {
        $manager = $this->makeUser('manager', $this->hyd);
        $this->openShift($manager);

        $this->checkout([
            'items' => [['product_id' => $this->tea->id, 'quantity' => 2]],
            'discount_type' => 'percent',
            'discount_value' => 10,
            'payments' => [['method' => 'card', 'amount' => 2970]],
        ], $manager)->assertOk()->assertJsonPath('grand_total', 2970);
    }

    public function test_non_cash_overpayment_is_rejected(): void
    {
        $this->openShift();

        $this->checkout(['items' => [['product_id' => $this->milk->id, 'quantity' => 1]], 'payments' => [['method' => 'card', 'amount' => 1000]]])
            ->assertStatus(422)->assertJsonValidationErrors('payments');
    }

    public function test_selling_requires_an_open_shift(): void
    {
        $this->checkout(['items' => [['product_id' => $this->milk->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 330]]])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Open a shift before selling.']);
    }

    public function test_invoice_numbers_are_sequential_per_branch(): void
    {
        $this->openShift();
        $khiCashier = $this->makeUser('cashier', $this->khi);
        $this->openShift($khiCashier, $this->khi);
        $sale = fn ($user) => $this->checkout(['items' => [['product_id' => $this->milk->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 330]]], $user);

        $sale($this->cashier)->assertJsonPath('invoice_no', 'HYD-S-000001');
        $sale($this->cashier)->assertJsonPath('invoice_no', 'HYD-S-000002');
        $sale($khiCashier)->assertJsonPath('invoice_no', 'KHI-S-000001');
    }

    public function test_hold_does_not_touch_stock_and_resume_completes_it(): void
    {
        $this->openShift();

        $hold = $this->actingAs($this->cashier)->postJson('/pos/hold', ['items' => [['product_id' => $this->tea->id, 'quantity' => 3]]])
            ->assertOk()->assertJsonPath('held_count', 1);
        $this->assertSame(10.0, $this->qty($this->tea, $this->hyd));
        $holdId = $hold->json('id');

        $this->actingAs($this->cashier)->getJson("/pos/held/{$holdId}")->assertOk()->assertJsonPath('items.0.qty', 3);

        $this->checkout(['hold_id' => $holdId, 'items' => [['product_id' => $this->tea->id, 'quantity' => 3]], 'payments' => [['method' => 'cash', 'amount' => 4950]]])
            ->assertOk();

        $this->assertSame(7.0, $this->qty($this->tea, $this->hyd));
        $this->assertSame(0, Sale::held()->count());
    }

    public function test_receive_payment_reduces_sale_and_customer_due(): void
    {
        $this->openShift();
        $customer = Customer::factory()->create();
        $this->checkout(['customer_id' => $customer->id, 'items' => [['product_id' => $this->tea->id, 'quantity' => 1]], 'payments' => []])->assertOk();
        $sale = Sale::first();

        $this->actingAs($this->cashier)->post("/sales/{$sale->id}/payments", ['amount' => 1000, 'method' => 'jazzcash'])->assertSessionHasNoErrors();
        $this->assertEquals(650, $sale->fresh()->due_amount);
        $this->assertEquals(650, $customer->fresh()->balance);

        $this->actingAs($this->cashier)->post("/sales/{$sale->id}/payments", ['amount' => 700, 'method' => 'cash'])->assertSessionHasErrors('amount');
    }

    public function test_shift_expected_cash(): void
    {
        $register = $this->openShift();
        $customer = Customer::factory()->create();
        $this->checkout(['items' => [['product_id' => $this->tea->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 2000]]]);
        $this->checkout(['items' => [['product_id' => $this->milk->id, 'quantity' => 1]], 'payments' => [['method' => 'jazzcash', 'amount' => 330]]]);
        $this->checkout(['customer_id' => $customer->id, 'items' => [['product_id' => $this->milk->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 100]]]);

        $summary = app(CashRegisterService::class)->summary($register);
        $this->assertEquals(1000 + 1650 + 100, $summary['expected_cash']);
        $this->assertEquals(330, $summary['methods']['jazzcash']['total']);

        $this->actingAs($this->cashier)->post("/cash-register/{$register->id}/close", ['counted_cash' => 2700])->assertRedirect();
        $this->assertEquals(-50, $register->fresh()->difference);
    }

    public function test_sales_visibility_by_role(): void
    {
        $this->openShift();
        $otherCashier = $this->makeUser('cashier', $this->hyd);
        $this->openShift($otherCashier);
        $this->checkout(['items' => [['product_id' => $this->milk->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 330]]]);
        $this->checkout(['items' => [['product_id' => $this->milk->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 330]]], $otherCashier);
        [$mine, $theirs] = Sale::orderBy('id')->get();

        $this->actingAs($this->cashier)->get('/sales')->assertOk()->assertSee($mine->invoice_no)->assertDontSee($theirs->invoice_no);
        $this->actingAs($this->cashier)->get("/sales/{$theirs->id}")->assertForbidden();

        $hydManager = $this->makeUser('manager', $this->hyd);
        $this->actingAs($hydManager)->get('/sales')->assertSee($mine->invoice_no)->assertSee($theirs->invoice_no);

        $khiManager = $this->makeUser('manager', $this->khi);
        $this->actingAs($khiManager)->get("/sales/{$mine->id}")->assertForbidden();
    }

    public function test_sale_pages_render(): void
    {
        $this->openShift();
        $this->checkout(['items' => [['product_id' => $this->tea->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 1650]]]);
        $sale = Sale::first();
        $owner = $this->owner();

        $this->actingAs($owner)->get('/sales')->assertOk();
        $this->actingAs($owner)->get("/sales/{$sale->id}")->assertOk()->assertSee('HYD-S-000001');
        $this->actingAs($owner)->get("/sales/{$sale->id}/receipt")->assertOk()->assertSee('Tapal 950g');
        $this->actingAs($owner)->get("/sales/{$sale->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->cashier)->get('/pos')->assertOk()->assertSee('posApp', false);
        $this->actingAs($this->cashier)->get('/cash-register')->assertOk();
        $this->actingAs($owner)->get('/pos')->assertOk()->assertSee('Choose a branch to start selling');
        $this->actingAs($this->cashier)->getJson('/pos/products')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($this->cashier)->getJson('/pos/lookup?code='.$this->tea->barcode)->assertOk()->assertJsonPath('stock', 9);
    }

    public function test_quick_add_customer(): void
    {
        $this->actingAs($this->cashier)->postJson('/pos/customers', ['name' => 'Ahsan', 'phone' => '0300-1112223'])->assertCreated();
        $this->actingAs($this->cashier)->postJson('/pos/customers', ['name' => 'Dup', 'phone' => '0300-1112223'])->assertJsonValidationErrors('phone');
    }
}
