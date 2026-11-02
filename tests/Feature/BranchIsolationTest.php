<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockAdjustment;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One branch's staff must never read or change another branch's documents.
 * Everything below is created at HYD (and SUK for the transfer); KHI staff try to reach it.
 */
class BranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    private array $hyd = [];

    protected function setUp(): void
    {
        parent::setUp();

        $hyd = Branch::factory()->create(['code' => 'HYD']);
        $suk = Branch::factory()->create(['code' => 'SUK']);
        Branch::factory()->create(['code' => 'KHI', 'name' => 'Karachi']);
        $manager = $this->makeUser('manager', $hyd);
        $cashier = $this->makeUser('cashier', $hyd);
        $unit = Unit::factory()->create();
        $tea = app(ProductService::class)->create(['name' => 'HydOnlyTea', 'unit_id' => $unit->id, 'purchase_price' => 100, 'sale_price' => 150, 'is_active' => true], null, [$hyd->id => 100]);
        $customer = Customer::factory()->create();
        $supplier = Supplier::factory()->create();
        $cat = ExpenseCategory::create(['name' => 'Rent', 'color' => '#000000']);

        app(CashRegisterService::class)->open($cashier, $hyd->id, 0);
        app(CashRegisterService::class)->open($manager, $hyd->id, 0);
        $this->actingAs($cashier)->postJson('/pos/checkout', ['customer_id' => $customer->id, 'items' => [['product_id' => $tea->id, 'quantity' => 2]], 'payments' => []])->assertOk();
        $sale = Sale::first();
        $this->actingAs($cashier)->postJson('/pos/hold', ['items' => [['product_id' => $tea->id, 'quantity' => 1]]])->assertOk();
        $this->actingAs($manager)->post('/sale-returns', ['sale_id' => $sale->id, 'items' => [$sale->items->first()->id => 1], 'refund_method' => 'adjust']);
        $this->actingAs($manager)->post('/purchases', ['supplier_id' => $supplier->id, 'purchase_date' => today()->toDateString(), 'status' => 'received',
            'items' => [['product_id' => $tea->id, 'quantity' => 5, 'unit_cost' => 100]], 'paid_amount' => 100, 'payment_method' => 'cash']);
        $purchase = Purchase::first();
        $this->actingAs($manager)->post('/purchase-returns', ['purchase_id' => $purchase->id, 'items' => [$purchase->items->first()->id => 1], 'settle_method' => 'adjust']);
        $this->actingAs($manager)->post('/payments', ['type' => 'receive', 'party_id' => $customer->id, 'amount' => 50, 'method' => 'cash', 'paid_at' => today()->toDateString(), 'allocation_mode' => 'auto']);
        $this->actingAs($manager)->post('/transfers', ['to_branch_id' => $suk->id, 'transfer_date' => today()->toDateString(), 'action' => 'draft', 'items' => [['product_id' => $tea->id, 'quantity' => 1]]]);
        $this->actingAs($manager)->post('/adjustments', ['adjustment_date' => today()->toDateString(), 'reason' => 'damaged', 'items' => [['product_id' => $tea->id, 'quantity' => -1]]]);
        $this->actingAs($manager)->post('/stock-counts', []);
        $this->actingAs($manager)->post('/expenses', ['expense_category_id' => $cat->id, 'amount' => 500, 'expense_date' => today()->toDateString(), 'method' => 'bank']);

        $this->hyd = [
            'sale' => $sale, 'held' => Sale::held()->first(), 'saleReturn' => SaleReturn::first(), 'purchase' => $purchase,
            'purchaseReturn' => PurchaseReturn::first(), 'payment' => Payment::whereNotNull('payment_no')->where('type', 'customer_receipt')->first(),
            'transfer' => StockTransfer::first(), 'adjustment' => StockAdjustment::first(), 'count' => StockCount::first(),
            'expense' => Expense::first(), 'register' => CashRegister::where('user_id', $cashier->id)->first(),
        ];
        foreach ($this->hyd as $key => $model) {
            $this->assertNotNull($model, "setup failed for {$key}");
        }
    }

    private function khi(string $role): User
    {
        $user = $this->makeUser($role, Branch::where('code', 'KHI')->first());
        app(CashRegisterService::class)->open($user, $user->branch_id, 0);

        return $user;
    }

    public function test_other_branch_manager_cannot_open_any_document(): void
    {
        $m = $this->khi('manager');
        $h = $this->hyd;

        foreach ([
            "/sales/{$h['sale']->id}", "/sales/{$h['sale']->id}/receipt", "/sales/{$h['sale']->id}/pdf",
            "/sale-returns/{$h['saleReturn']->id}", "/sale-returns/{$h['saleReturn']->id}/receipt",
            "/purchases/{$h['purchase']->id}", "/purchases/{$h['purchase']->id}/pdf", "/purchase-returns/{$h['purchaseReturn']->id}",
            "/payments/{$h['payment']->id}", "/payments/{$h['payment']->id}/receipt",
            "/transfers/{$h['transfer']->id}", "/transfers/{$h['transfer']->id}/challan",
            "/adjustments/{$h['adjustment']->id}", "/stock-counts/{$h['count']->id}", "/stock-counts/{$h['count']->id}/sheet",
            "/cash-register/{$h['register']->id}/report",
        ] as $uri) {
            $this->assertContains($this->actingAs($m)->get($uri)->status(), [403, 404], "KHI manager could open {$uri}");
        }
    }

    public function test_other_branch_manager_cannot_change_any_document(): void
    {
        $m = $this->khi('manager');
        $h = $this->hyd;

        $attempts = [
            ['post', "/sales/{$h['sale']->id}/payments", ['amount' => 10, 'method' => 'cash']],
            ['post', "/sales/{$h['sale']->id}/cancel", ['cancel_reason' => 'hack']],
            ['post', '/sale-returns', ['sale_id' => $h['sale']->id, 'items' => [$h['sale']->items->first()->id => 1], 'refund_method' => 'cash']],
            ['post', "/purchases/{$h['purchase']->id}/receive", []],
            ['delete', "/purchases/{$h['purchase']->id}", []],
            ['post', '/purchase-returns', ['purchase_id' => $h['purchase']->id, 'items' => [$h['purchase']->items->first()->id => 1], 'settle_method' => 'adjust']],
            ['post', "/transfers/{$h['transfer']->id}/send", []],
            ['post', "/transfers/{$h['transfer']->id}/cancel", []],
            ['put', "/stock-counts/{$h['count']->id}", ['counted' => [], 'then' => 'apply']],
            ['post', "/stock-counts/{$h['count']->id}/cancel", []],
            ['put', "/expenses/{$h['expense']->id}", ['expense_category_id' => $h['expense']->expense_category_id, 'amount' => 1, 'expense_date' => today()->toDateString(), 'method' => 'cash']],
            ['delete', "/expenses/{$h['expense']->id}", []],
            ['post', "/cash-register/{$h['register']->id}/close", ['counted_cash' => 0]],
        ];

        foreach ($attempts as [$method, $uri, $data]) {
            $status = $this->actingAs($m)->{$method}($uri, $data)->status();
            $this->assertContains($status, [403, 404], "KHI manager could {$method} {$uri} (status {$status})");
        }

        // Nothing at HYD changed
        $this->assertSame('completed', $h['sale']->fresh()->status);
        $this->assertSame('draft', $h['transfer']->fresh()->status);
        $this->assertSame('in_progress', $h['count']->fresh()->status);
        $this->assertNull($h['expense']->fresh()->deleted_at);
        $this->assertSame('open', $h['register']->fresh()->status);
        $this->assertSame(1, SaleReturn::count());
        $this->assertSame(1, PurchaseReturn::count());
    }

    public function test_other_branch_cashier_cannot_touch_held_bills_or_sales(): void
    {
        $c = $this->khi('cashier');

        $this->actingAs($c)->getJson("/pos/held/{$this->hyd['held']->id}")->assertNotFound();
        $this->actingAs($c)->deleteJson("/pos/held/{$this->hyd['held']->id}")->assertNotFound();
        $this->actingAs($c)->getJson('/pos/held')->assertOk()->assertJsonCount(0);
        $this->actingAs($c)->get("/sales/{$this->hyd['sale']->id}")->assertForbidden();
        $this->assertNotNull($this->hyd['held']->fresh());
    }

    public function test_lists_and_reports_hide_other_branch_data(): void
    {
        $m = $this->khi('manager');
        $h = $this->hyd;

        foreach ([
            '/sales' => $h['sale']->invoice_no, '/sale-returns' => $h['saleReturn']->return_no, '/purchases' => $h['purchase']->purchase_no,
            '/purchase-returns' => $h['purchaseReturn']->return_no, '/payments' => $h['payment']->payment_no, '/transfers' => $h['transfer']->transfer_no,
            '/adjustments' => $h['adjustment']->adjustment_no, '/stock-counts' => $h['count']->count_no, '/expenses' => $h['expense']->expense_no,
            '/cash-register' => 'HYD', '/stock/movements' => 'HydOnlyTea', '/reports/sales-by-product?preset=this_month' => 'HydOnlyTea',
        ] as $uri => $hidden) {
            $this->actingAs($m)->get($uri)->assertOk()->assertDontSee($hidden);
        }

        // ?branch= is ignored for non-owners
        $hydId = $h['sale']->branch_id;
        $this->actingAs($m)->get("/reports/sales?branch={$hydId}&preset=this_month")->assertOk()->assertDontSee($h['sale']->invoice_no);
        $this->actingAs($m)->getJson("/products/search?q=HydOnly&branch={$hydId}")->assertJsonPath('0.stock', 0);

        // Ctrl+K search: products are shared, invoices are not
        $this->actingAs($m)->getJson('/search?q='.substr($h['sale']->invoice_no, 0, 6))->assertOk()->assertJsonMissing(['label' => $h['sale']->invoice_no]);
        $owner = $this->owner();
        $this->actingAs($owner)->getJson('/search?q='.$h['sale']->invoice_no)->assertJsonFragment(['label' => $h['sale']->invoice_no]);
        $this->actingAs($owner)->get('/notifications')->assertOk();
    }

    public function test_switching_branch_session_does_nothing_for_non_owners(): void
    {
        $m = $this->khi('manager');

        $this->actingAs($m)->withSession(['current_branch_id' => $this->hyd['sale']->branch_id])
            ->get('/sales')->assertOk()->assertDontSee($this->hyd['sale']->invoice_no);
        $this->actingAs($m)->post('/branch/switch', ['branch' => $this->hyd['sale']->branch_id])->assertForbidden();
    }
}
