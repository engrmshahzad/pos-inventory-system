<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\LedgerService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingAndKhataTest extends TestCase
{
    use RefreshDatabase;

    private Branch $hyd;
    private Branch $khi;
    private User $manager;
    private Product $tea;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hyd = Branch::factory()->create(['code' => 'HYD']);
        $this->khi = Branch::factory()->create(['code' => 'KHI']);
        $this->manager = $this->makeUser('manager', $this->hyd);
        $pcs = Unit::factory()->create(['short_name' => 'pcs']);
        $this->tea = app(ProductService::class)->create(['name' => 'Tapal 950g', 'unit_id' => $pcs->id, 'purchase_price' => 1400, 'sale_price' => 1650, 'is_active' => true], null, [$this->hyd->id => 5]);
        $this->supplier = Supplier::factory()->create(['opening_balance' => 1000, 'balance' => 1000]);
    }

    private function qty(Branch $branch): float
    {
        return (float) $this->tea->stocks()->where('branch_id', $branch->id)->value('quantity');
    }

    private function purchase(array $overrides = [])
    {
        return $this->actingAs($this->manager)->post('/purchases', $overrides + [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => today()->toDateString(),
            'status' => 'received',
            'items' => [['product_id' => $this->tea->id, 'quantity' => 10, 'unit_cost' => 1480, 'update_price' => 1, 'new_sale_price' => 1700]],
            'paid_amount' => 4800,
            'payment_method' => 'cash',
        ]);
    }

    public function test_received_purchase_adds_stock_payable_and_updates_prices(): void
    {
        $this->purchase()->assertSessionHasNoErrors();

        $purchase = Purchase::first();
        $this->assertSame('HYD-P-000001', $purchase->purchase_no);
        $this->assertSame(15.0, $this->qty($this->hyd));
        $this->assertSame(0.0, $this->qty($this->khi));
        $this->assertEquals(1000 + 14800 - 4800, $this->supplier->fresh()->balance);
        $this->assertEquals(10000, $purchase->due_amount);
        $this->assertSame('partial', $purchase->payment_status);
        $this->assertEquals(1480, $this->tea->fresh()->purchase_price); // latest purchase cost
        $this->assertEquals(1700, $this->tea->fresh()->sale_price);
        $this->assertDatabaseHas('stock_movements', ['type' => 'purchase', 'reference_no' => 'HYD-P-000001', 'quantity' => 10]);
    }

    public function test_ordered_purchase_waits_for_receive(): void
    {
        $this->purchase(['status' => 'ordered', 'paid_amount' => 0])->assertSessionHasNoErrors();
        $purchase = Purchase::first();

        $this->assertSame(5.0, $this->qty($this->hyd));
        $this->assertEquals(1000, $this->supplier->fresh()->balance);

        $this->actingAs($this->manager)->post("/purchases/{$purchase->id}/receive")->assertSessionHasNoErrors();
        $this->assertSame(15.0, $this->qty($this->hyd));
        $this->assertEquals(15800, $this->supplier->fresh()->balance);

        $this->actingAs($this->manager)->post("/purchases/{$purchase->id}/receive")->assertSessionHasErrors('status');
    }

    public function test_manager_cannot_buy_into_another_branch_or_see_its_purchases(): void
    {
        $this->purchase(['branch_id' => $this->khi->id])->assertSessionHasNoErrors();
        $this->assertSame($this->hyd->id, Purchase::first()->branch_id); // forced to own branch

        $khiManager = $this->makeUser('manager', $this->khi);
        $this->actingAs($khiManager)->get('/purchases/'.Purchase::first()->id)->assertForbidden();
    }

    public function test_supplier_payment_is_allocated_oldest_first(): void
    {
        $this->purchase(['paid_amount' => 0, 'purchase_date' => today()->subDays(2)->toDateString()]);
        $this->purchase(['paid_amount' => 0]);
        [$older, $newer] = Purchase::orderBy('id')->get();

        $this->actingAs($this->manager)->post('/payments', [
            'type' => 'pay', 'party_id' => $this->supplier->id, 'amount' => 20000, 'method' => 'bank',
            'paid_at' => today()->toDateString(), 'allocation_mode' => 'auto',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(0, $older->fresh()->due_amount);
        $this->assertSame('paid', $older->fresh()->payment_status);
        $this->assertEquals(14800 - 5200, $newer->fresh()->due_amount);
        $this->assertEquals(1000 + 29600 - 20000, $this->supplier->fresh()->balance);
    }

    public function test_customer_receipt_manual_allocation_and_ledger(): void
    {
        $customer = Customer::factory()->create(['opening_balance' => 500, 'balance' => 500]);
        $sales = collect([3000, 2000])->map(fn ($total, $i) => Sale::create([
            'branch_id' => $this->hyd->id, 'invoice_no' => "HYD-S-00000{$i}", 'status' => 'completed', 'customer_id' => $customer->id,
            'user_id' => $this->manager->id, 'subtotal' => $total, 'grand_total' => $total, 'due_amount' => $total, 'payment_status' => 'due', 'sold_at' => now()->subDays(5 - $i),
        ]));
        $customer->increment('balance', 5000);

        $this->actingAs($this->manager)->post('/payments', [
            'type' => 'receive', 'party_id' => $customer->id, 'amount' => 2500, 'method' => 'cash',
            'paid_at' => today()->toDateString(), 'allocation_mode' => 'manual', 'allocations' => [$sales[1]->id => 2000],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(3000, $sales[0]->fresh()->due_amount); // untouched
        $this->assertEquals(0, $sales[1]->fresh()->due_amount);
        $this->assertEquals(3000, $customer->fresh()->balance);   // 500 + 5000 - 2500
        $this->assertEquals(3000, app(LedgerService::class)->customer($customer->fresh())['closing']);

        // Allocating more than the payment is rejected.
        $this->actingAs($this->manager)->post('/payments', [
            'type' => 'receive', 'party_id' => $customer->id, 'amount' => 100, 'method' => 'cash',
            'paid_at' => today()->toDateString(), 'allocation_mode' => 'manual', 'allocations' => [$sales[0]->id => 500],
        ])->assertSessionHasErrors('allocations');
    }

    public function test_sale_return_limits_restock_and_refunds(): void
    {
        app(CashRegisterService::class)->open($this->manager, $this->hyd->id, 1000);
        $customer = Customer::factory()->create();
        $this->actingAs($this->manager)->postJson('/pos/checkout', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $this->tea->id, 'quantity' => 3]],
            'payments' => [['method' => 'cash', 'amount' => 1650]],
        ])->assertOk();
        $sale = Sale::first();
        $itemId = $sale->items->first()->id;
        $this->assertSame(2.0, $this->qty($this->hyd));
        $this->assertEquals(3300, $customer->fresh()->balance);

        // cannot return more than sold
        $this->actingAs($this->manager)->post('/sale-returns', ['sale_id' => $sale->id, 'items' => [$itemId => 4], 'refund_method' => 'cash'])
            ->assertSessionHasErrors('items');

        // 1 back as khata adjustment
        $this->actingAs($this->manager)->post('/sale-returns', ['sale_id' => $sale->id, 'items' => [$itemId => 1], 'refund_method' => 'adjust'])
            ->assertSessionHasNoErrors();
        $this->assertSame(3.0, $this->qty($this->hyd));
        $this->assertEquals(1650, $customer->fresh()->balance);
        $this->assertEquals(1650, $sale->fresh()->due_amount);

        // 1 back as cash refund: shift expected cash goes down
        $this->actingAs($this->manager)->post('/sale-returns', ['sale_id' => $sale->id, 'items' => [$itemId => 1], 'refund_method' => 'cash'])
            ->assertSessionHasNoErrors();
        $register = CashRegister::first();
        $this->assertEquals(1000 + 1650 - 1650, app(CashRegisterService::class)->summary($register)['expected_cash']);
        $this->assertEquals(1650, $customer->fresh()->balance); // cash refund does not touch the khata

        // only 1 left to return
        $this->actingAs($this->manager)->post('/sale-returns', ['sale_id' => $sale->id, 'items' => [$itemId => 2], 'refund_method' => 'cash'])
            ->assertSessionHasErrors('items');
        $this->assertSame('HYD-SR-000002', SaleReturn::latest('id')->value('return_no'));
        $this->assertEquals(1650, app(LedgerService::class)->customer($customer->fresh())['closing']);
    }

    public function test_walk_in_return_cannot_be_adjusted(): void
    {
        app(CashRegisterService::class)->open($this->manager, $this->hyd->id, 0);
        $this->actingAs($this->manager)->postJson('/pos/checkout', ['items' => [['product_id' => $this->tea->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 1650]]]);
        $sale = Sale::first();

        $this->actingAs($this->manager)->post('/sale-returns', ['sale_id' => $sale->id, 'items' => [$sale->items->first()->id => 1], 'refund_method' => 'adjust'])
            ->assertSessionHasErrors('refund_method');
    }

    public function test_purchase_return_blocked_when_stock_is_short(): void
    {
        $this->purchase(['paid_amount' => 0]);
        $purchase = Purchase::first();
        $itemId = $purchase->items->first()->id;

        // sell 14 of 15 so only 1 remains on the shelf
        $this->tea->stocks()->where('branch_id', $this->hyd->id)->update(['quantity' => 1]);

        $this->actingAs($this->manager)->post('/purchase-returns', ['purchase_id' => $purchase->id, 'items' => [$itemId => 3], 'settle_method' => 'adjust'])
            ->assertSessionHasErrors('items');
        $this->assertEquals(15800, $this->supplier->fresh()->balance);

        $this->actingAs($this->manager)->post('/purchase-returns', ['purchase_id' => $purchase->id, 'items' => [$itemId => 1], 'settle_method' => 'adjust'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->qty($this->hyd));
        $this->assertEquals(15800 - 1480, $this->supplier->fresh()->balance);
        $this->assertEquals(15800 - 1480, app(LedgerService::class)->supplier($this->supplier->fresh())['closing']);
    }

    public function test_cashier_access(): void
    {
        $cashier = $this->makeUser('cashier', $this->hyd);

        $this->actingAs($cashier)->get('/customers')->assertOk();
        $this->actingAs($cashier)->get('/sale-returns/create')->assertOk();
        $this->actingAs($cashier)->get('/purchases')->assertForbidden();
        $this->actingAs($cashier)->get('/suppliers')->assertForbidden();
        $this->actingAs($cashier)->get('/payments')->assertForbidden();
        $this->actingAs($cashier)->get('/payments/create')->assertForbidden();
    }

    public function test_customer_and_supplier_crud(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/customers', ['name' => 'Ali Traders', 'phone' => '0300-1112223', 'opening_balance' => 750, 'credit_limit' => 5000, 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $customer = Customer::firstWhere('phone', '0300-1112223');
        $this->assertEquals(750, $customer->balance);

        $this->actingAs($owner)->put("/customers/{$customer->id}", ['name' => 'Ali Traders', 'phone' => '0300-1112223', 'opening_balance' => 99])
            ->assertSessionHasErrors('opening_balance');
        $this->actingAs($owner)->delete("/customers/{$customer->id}")->assertSessionHasErrors('action');

        $this->actingAs($owner)->post('/suppliers', ['name' => 'Kamran', 'company' => 'Tapal Dist', 'opening_balance' => 2000, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertEquals(2000, Supplier::firstWhere('company', 'Tapal Dist')->balance);
    }

    public function test_pages_render(): void
    {
        $owner = $this->owner();
        $this->purchase();
        $purchase = Purchase::first();
        $customer = Customer::factory()->create(['balance' => 100, 'opening_balance' => 100]);
        $this->actingAs($this->manager)->post('/payments', ['type' => 'pay', 'party_id' => $this->supplier->id, 'amount' => 500, 'method' => 'cash', 'paid_at' => today()->toDateString(), 'allocation_mode' => 'auto']);
        $payment = \App\Models\Payment::where('type', 'supplier_payment')->first();
        $this->actingAs($this->manager)->post('/purchase-returns', ['purchase_id' => $purchase->id, 'items' => [$purchase->items->first()->id => 1], 'settle_method' => 'cash']);

        foreach (['/customers', "/customers/{$customer->id}", "/customers/{$customer->id}?tab=ledger&from=2020-01-01", '/suppliers', "/suppliers/{$this->supplier->id}",
            '/purchases', '/purchases/create', "/purchases/{$purchase->id}", '/purchase-returns', '/purchase-returns/create', "/purchase-returns/create?purchase={$purchase->purchase_no}",
            '/purchase-returns/1', '/sale-returns', '/sale-returns/create', '/payments', '/payments/create', '/payments/create?type=pay', "/payments/{$payment->id}",
            "/payments/{$payment->id}/receipt", "/customers/{$customer->id}/invoices", "/suppliers/{$this->supplier->id}/invoices", '/customers/search?q=a', '/suppliers/search'] as $uri) {
            $this->actingAs($owner)->get($uri)->assertOk();
        }

        $this->actingAs($owner)->get("/purchases/{$purchase->id}/pdf")->assertHeader('content-type', 'application/pdf');
        $this->actingAs($owner)->get("/customers/{$customer->id}/statement")->assertHeader('content-type', 'application/pdf');
        $this->actingAs($owner)->get("/suppliers/{$this->supplier->id}/statement")->assertHeader('content-type', 'application/pdf');
        $this->actingAs($owner)->get('/sale-returns/create?invoice=NOPE')->assertRedirect()->assertSessionHasErrors('invoice');
    }
}
