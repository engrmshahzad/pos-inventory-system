<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Reports\ReportRegistry;
use App\Services\CashRegisterService;
use App\Services\DashboardService;
use App\Services\ProductService;
use App\Services\ProfitLossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportsAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Branch $hyd;
    private Branch $khi;
    private User $hydManager;
    private Product $tea;
    private Product $milk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hyd = Branch::factory()->create(['code' => 'HYD', 'name' => 'Hyderabad']);
        $this->khi = Branch::factory()->create(['code' => 'KHI', 'name' => 'Karachi']);
        $this->hydManager = $this->makeUser('manager', $this->hyd);
        $pcs = Unit::factory()->create(['short_name' => 'pcs']);
        $ps = app(ProductService::class);
        $this->tea = $ps->create(['name' => 'Tapal', 'unit_id' => $pcs->id, 'purchase_price' => 1000, 'sale_price' => 1500, 'min_stock' => 5, 'is_active' => true], null, [$this->hyd->id => 50, $this->khi->id => 50]);
        $this->milk = $ps->create(['name' => 'Milk', 'unit_id' => $pcs->id, 'purchase_price' => 200, 'sale_price' => 300, 'min_stock' => 5, 'is_active' => true], null, [$this->hyd->id => 50, $this->khi->id => 3]);
    }

    private function sell(User $user, Branch $branch, array $items, array $payments, ?int $customerId = null): Sale
    {
        if (! \App\Models\CashRegister::openFor($user, $branch->id)) {
            app(CashRegisterService::class)->open($user, $branch->id, 0);
        }
        $this->actingAs($user)->postJson('/pos/checkout', ['customer_id' => $customerId, 'items' => $items, 'payments' => $payments])->assertOk();

        return Sale::latest('id')->first();
    }

    public function test_profit_and_loss_matches_a_hand_calculation(): void
    {
        $owner = $this->owner();
        // Sale: 2 tea (3000) + 5 milk (1500) = 4500 revenue, cost 2000 + 1000 = 3000
        $sale = $this->sell($this->hydManager, $this->hyd, [['product_id' => $this->tea->id, 'quantity' => 2], ['product_id' => $this->milk->id, 'quantity' => 5]], [['method' => 'cash', 'amount' => 4500]]);
        // Return 1 tea: −1500 revenue, −1000 cost
        $teaLine = $sale->items->firstWhere('product_id', $this->tea->id);
        $this->actingAs($this->hydManager)->post('/sale-returns', ['sale_id' => $sale->id, 'items' => [$teaLine->id => 1], 'refund_method' => 'cash'])->assertSessionHasNoErrors();
        // Expense 700
        $cat = ExpenseCategory::create(['name' => 'Tea / refreshment', 'color' => '#EC4899']);
        $this->actingAs($this->hydManager)->post('/expenses', ['expense_category_id' => $cat->id, 'amount' => 700, 'expense_date' => today()->toDateString(), 'method' => 'cash'])->assertSessionHasNoErrors();
        // Damaged 1 milk: −200 stock loss
        $this->actingAs($this->hydManager)->post('/adjustments', ['adjustment_date' => today()->toDateString(), 'reason' => 'damaged', 'items' => [['product_id' => $this->milk->id, 'quantity' => -1]]]);

        $pl = app(ProfitLossService::class)->compute($this->hyd->id, today(), today());

        $this->assertEquals(4500, $pl['gross_sales']);
        $this->assertEquals(1500, $pl['returns']);
        $this->assertEquals(3000, $pl['net_sales']);
        $this->assertEquals(2000, $pl['cogs']);          // 3000 − 1000 returned cost
        $this->assertEquals(1000, $pl['gross_profit']);
        $this->assertEquals(700, $pl['total_expenses']);
        $this->assertEquals(-200, $pl['adjustments']);
        $this->assertEquals(100, $pl['net_profit']);     // 1000 − 700 − 200

        // KHI had nothing; consolidated equals HYD
        $this->assertEquals(0, app(ProfitLossService::class)->compute($this->khi->id, today(), today())['net_profit']);
        $this->assertEquals(100, app(ProfitLossService::class)->compute(null, today(), today())['net_profit']);

        $this->actingAs($owner)->get('/reports/profit-loss?preset=today')->assertOk()->assertSee('Net profit')->assertSee('Rs 100');
    }

    public function test_dashboard_branch_numbers_add_up_to_all_branches(): void
    {
        $khiManager = $this->makeUser('manager', $this->khi);
        $this->sell($this->hydManager, $this->hyd, [['product_id' => $this->tea->id, 'quantity' => 2]], [['method' => 'cash', 'amount' => 3000]]);
        $this->sell($khiManager, $this->khi, [['product_id' => $this->milk->id, 'quantity' => 3]], [['method' => 'card', 'amount' => 900]]);

        $d = app(DashboardService::class);
        $hyd = $d->metrics($this->hyd->id);
        $khi = $d->metrics($this->khi->id);
        $all = $d->metrics(null);

        $this->assertEquals(3000, $hyd['kpis']['sales'][0]);
        $this->assertEquals(1000, $hyd['kpis']['profit'][0]);
        $this->assertEquals(900, $khi['kpis']['sales'][0]);
        $this->assertEquals(3900, $all['kpis']['sales'][0]);
        $this->assertEquals($hyd['kpis']['profit'][0] + $khi['kpis']['profit'][0], $all['kpis']['profit'][0]);
        $this->assertEquals($hyd['kpis']['orders'][0] + $khi['kpis']['orders'][0], $all['kpis']['orders'][0]);
        $this->assertEquals(1, $khi['balances']['low_stock']); // milk 0 left at KHI
        $this->assertEquals(3900, array_sum($all['daily']['sales']));
        $this->assertCount(2, $all['branches']);
    }

    public function test_customer_aging_buckets(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        foreach ([[5, 1000], [45, 2000], [100, 4000]] as $i => [$daysAgo, $due]) {
            Sale::create(['branch_id' => $this->hyd->id, 'invoice_no' => "T-{$i}", 'status' => 'completed', 'customer_id' => $customer->id, 'user_id' => $this->hydManager->id,
                'subtotal' => $due, 'grand_total' => $due, 'due_amount' => $due, 'payment_status' => 'due', 'sold_at' => now()->subDays($daysAgo)]);
        }
        $customer->update(['balance' => 7500]); // 7000 invoices + 500 opening

        $result = ReportRegistry::find('customer-aging')->run(new \App\Reports\ReportContext(today(), today(), null, $this->owner(), [], true, 'today'));
        $row = $result->rows->first();

        $this->assertEquals(1000, $row['b0']);
        $this->assertEquals(2000, $row['b30']);
        $this->assertEquals(0, $row['b60']);
        $this->assertEquals(4000, $row['b90']);
        $this->assertEquals(500, $row['other']);
        $this->assertEquals(7500, $row['total']);
    }

    public function test_every_report_loads_and_exports(): void
    {
        $owner = $this->owner();
        $this->sell($this->hydManager, $this->hyd, [['product_id' => $this->tea->id, 'quantity' => 1]], [['method' => 'cash', 'amount' => 1500]]);

        foreach (ReportRegistry::all() as $key => $report) {
            $this->actingAs($owner)->get("/reports/{$key}?preset=this_month")->assertOk();
            $this->actingAs($owner)->get("/reports/{$key}?branch={$this->hyd->id}&preset=last_30")->assertOk();
            $this->actingAs($owner)->get("/reports/{$key}/export/xlsx")->assertOk()->assertDownload();
            $this->actingAs($owner)->get("/reports/{$key}/export/print")->assertOk();
        }
        $this->actingAs($owner)->get('/reports/sales/export/pdf?preset=custom&from='.today()->subDays(3)->toDateString().'&to='.today()->toDateString())
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($owner)->get('/reports')->assertOk()->assertSee('Profit &amp; Loss', false);
        $this->actingAs($owner)->get('/activity-log')->assertOk();
    }

    public function test_manager_sees_only_own_branch_and_no_owner_reports(): void
    {
        $khiManager = $this->makeUser('manager', $this->khi);
        $this->sell($khiManager, $this->khi, [['product_id' => $this->tea->id, 'quantity' => 1]], [['method' => 'cash', 'amount' => 1500]]);

        // branch param is ignored for managers: HYD manager never sees KHI sales
        $this->actingAs($this->hydManager)->get("/reports/sales-by-product?branch={$this->khi->id}")->assertOk()->assertDontSee('Tapal');
        $this->actingAs($this->hydManager)->get('/reports/branch-comparison')->assertForbidden();
        $this->actingAs($this->hydManager)->get('/activity-log')->assertForbidden();
        $this->actingAs($this->makeUser('cashier', $this->hyd))->get('/reports')->assertForbidden();
    }

    public function test_settings_save_refresh_cache_and_change_prefixes(): void
    {
        $owner = $this->owner();
        Storage::fake('public');

        $this->actingAs($owner)->put('/settings/general', [
            'business_name' => 'Shahzad Mart', 'currency_symbol' => 'PKR', 'number_format' => 'indian', 'date_format' => 'd/m/Y', 'timezone' => 'Asia/Karachi', 'session_timeout' => 60,
            'business_logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Shahzad Mart', settings('business_name'));
        $this->assertSame('PKR 12,50,000', money(1250000));
        Storage::disk('public')->assertExists(settings('business_logo'));

        $this->actingAs($owner)->put('/settings/prefixes', array_merge(
            collect(\App\Support\SettingsDefinition::GROUPS['prefixes'])->all(), ['prefix_sale' => 'inv']
        ))->assertSessionHasNoErrors();
        $sale = $this->sell($this->hydManager, $this->hyd, [['product_id' => $this->milk->id, 'quantity' => 1]], [['method' => 'cash', 'amount' => 300]]);
        $this->assertSame('HYD-INV-000001', $sale->invoice_no);

        $this->actingAs($owner)->put('/settings/pos', ['receipt_size' => '58', 'cashier_max_discount' => 10])->assertSessionHasNoErrors();
        $this->assertSame('0', settings('allow_negative_stock')); // unticked checkbox saved as 0
        $this->assertSame('58', settings('receipt_size'));

        $this->actingAs($this->hydManager)->get('/settings')->assertForbidden();
        $this->actingAs($owner)->get('/settings?tab=backup')->assertOk();
    }

    public function test_cash_expense_reduces_shift_cash_and_expense_page_works(): void
    {
        $register = app(CashRegisterService::class)->open($this->hydManager, $this->hyd->id, 5000);
        $cat = ExpenseCategory::create(['name' => 'Transport', 'color' => '#0EA5E9']);

        $this->actingAs($this->hydManager)->post('/expenses', [
            'expense_category_id' => $cat->id, 'amount' => 1200, 'expense_date' => today()->toDateString(), 'method' => 'cash',
            'attachment' => UploadedFile::fake()->create('bill.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertEquals(3800, app(CashRegisterService::class)->summary($register)['expected_cash']);
        $this->actingAs($this->hydManager)->get('/expenses')->assertOk()->assertSee('HYD-EXP-000001')->assertSeeInOrder(['Biggest category', 'Transport']);
        $this->actingAs($this->makeUser('cashier', $this->hyd))->get('/expenses')->assertForbidden();
    }

    public function test_low_stock_command_notifies_managers(): void
    {
        $this->milk->stocks()->where('branch_id', $this->hyd->id)->update(['quantity' => 2]);

        Artisan::call('pos:low-stock-alerts');

        $this->assertSame(1, $this->hydManager->unreadNotifications()->count());
        $this->assertStringContainsString('Low stock at HYD', $this->hydManager->notifications()->first()->data['title']);
    }

    public function test_dashboards_render_for_each_role(): void
    {
        $this->sell($this->hydManager, $this->hyd, [['product_id' => $this->tea->id, 'quantity' => 1]], [['method' => 'cash', 'amount' => 1500]]);

        $this->actingAs($this->owner())->get('/dashboard')->assertOk()->assertSee('Branch comparison');
        $this->actingAs($this->hydManager)->get('/dashboard')->assertOk()->assertSee("Today's Profit");
        $this->actingAs($this->makeUser('cashier', $this->hyd))->get('/dashboard')->assertOk()->assertSee('Open POS')->assertDontSee("Today's Profit");
        $this->actingAs($this->hydManager)->post('/dashboard/refresh')->assertRedirect();
    }

    public function test_large_exports_are_queued_and_only_the_owner_can_download(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $owner = $this->owner();
        $rows = collect(range(1, \App\Http\Controllers\ReportController::QUEUE_THRESHOLD + 1))->map(fn ($i) => [
            'branch_id' => $this->hyd->id, 'product_id' => $this->tea->id, 'type' => 'adjustment', 'quantity' => 1, 'balance_after' => 1, 'unit_cost' => 1, 'created_at' => now(),
        ]);
        foreach ($rows->chunk(500) as $chunk) {
            \Illuminate\Support\Facades\DB::table('stock_movements')->insert($chunk->all());
        }

        $this->actingAs($owner)->get('/reports/stock-movements/export/xlsx?preset=today')->assertRedirect()->assertSessionHas('info');
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ExportReportJob::class);

        // Run the job for real and download the file
        \Illuminate\Support\Facades\Storage::fake('local');
        (new \App\Jobs\ExportReportJob('stock-movements', $owner->id, today()->toDateString(), today()->toDateString(), null, [], 'today', 'All'))->handle();
        $note = $owner->notifications()->first();
        $this->assertSame('Your export is ready', $note->data['title']);
        $file = basename($note->data['url']);
        $this->actingAs($owner)->get("/exports/{$file}")->assertOk()->assertDownload();
        $this->actingAs($this->hydManager)->get("/exports/{$file}")->assertNotFound();
    }
}
