<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Reports\ReportRegistry;
use App\Services\StockCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Module audit on the full demo data: every GET page for every role, and create / update / invalid-data
 * for every CRUD module.
 */
class ModuleAuditTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seedRoles = false;

    protected function setUp(): void
    {
        parent::setUp();
        // explicit: with in-memory SQLite, $seed only runs for the first test class that migrates
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /** Pages a manager may not open. */
    private const MANAGER_FORBIDDEN = ['activity-log', 'branches', 'roles', 'settings', 'ui-kit', 'transfers/suggestions', 'reports/branch-comparison'];

    /** The only pages a cashier may open. */
    private const CASHIER_ALLOWED = ['cash-register', 'cash-register/{register}/report', 'customers', 'customers/{customer}', 'customers/{customer}/statement',
        'customers/search', 'dashboard', 'notifications', 'pos', 'pos/customers', 'pos/held', 'pos/products', 'products/search', 'profile',
        'sale-returns', 'sale-returns/create', 'sales', 'sales/{sale}', 'sales/{sale}/pdf', 'sales/{sale}/receipt', 'search'];

    /** Not plain pages: need a token/file/code, or redirect by design. */
    private const SKIP = ['/', 'up', 'logout', 'login', 'register', 'forgot-password', 'confirm-password', 'verify-email', 'verify-email/{id}/{hash}',
        'reset-password/{token}', 'storage/{path}', 'notifications/{id}', 'exports/{file}', 'settings/backups/{file}', 'products/barcodes/print',
        'pos/lookup', 'pos/held/{sale}', 'products/import/template'];

    private function hyd(): Branch
    {
        return Branch::where('code', 'HYD')->firstOrFail();
    }

    /** One real HYD record for every route parameter. */
    private function params(): array
    {
        $hyd = $this->hyd()->id;
        $count = StockCount::where('branch_id', $hyd)->first()
            ?? app(StockCountService::class)->start(User::where('email', 'manager.hyd@pos.test')->first(), $this->hyd());

        return [
            'adjustment' => \App\Models\StockAdjustment::where('branch_id', $hyd)->value('id'),
            'customer' => Customer::value('id'),
            'supplier' => Supplier::value('id'),
            'product' => Product::value('id'),
            'payment' => Payment::whereNotNull('payment_no')->where('branch_id', $hyd)->value('id'),
            'purchase' => Purchase::where('branch_id', $hyd)->value('id'),
            'purchaseReturn' => \App\Models\PurchaseReturn::where('branch_id', $hyd)->value('id'),
            'register' => \App\Models\CashRegister::where('branch_id', $hyd)->whereHas('user', fn ($q) => $q->where('email', 'cashier.hyd@pos.test'))->value('id'),
            'sale' => Sale::where('branch_id', $hyd)->where('status', 'completed')->whereHas('user', fn ($q) => $q->where('email', 'cashier.hyd@pos.test'))->value('id'),
            'saleReturn' => \App\Models\SaleReturn::where('branch_id', $hyd)->value('id'),
            'stockCount' => $count->id,
            'transfer' => \App\Models\StockTransfer::where('from_branch_id', $hyd)->orWhere('to_branch_id', $hyd)->value('id'),
        ];
    }

    /** @return array<string, string> uri pattern => url */
    private function pages(): array
    {
        $params = $this->params();
        $pages = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods()) || in_array($uri, self::SKIP) || str_starts_with($uri, '_') || str_starts_with($uri, 'sanctum')) {
                continue;
            }
            if (str_contains($uri, '{key}')) {
                foreach (ReportRegistry::all()->keys() as $key) {
                    // PDFs are checked separately (dompdf memory adds up inside one test process)
                    foreach (str_contains($uri, '{format}') ? ['xlsx', 'print'] : [null] as $format) {
                        $pages[str_replace('{key}', 'x', $uri).' '.$key.$format] = '/'.str_replace(['{key}', '{format}'], [$key, (string) $format], $uri).'?preset=today';
                    }
                }

                continue;
            }
            $url = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($params, $uri) {
                $this->assertNotEmpty($params[$m[1]] ?? null, "No demo record for {{$m[1]}} in {$uri}");

                return $params[$m[1]];
            }, $uri);
            $pages[$uri] = '/'.$url;
        }

        return $pages;
    }

    private function expectedFor(string $role, string $uri): int
    {
        $base = explode(' ', $uri)[0];

        return match ($role) {
            'owner' => 200,
            'manager' => in_array($base, self::MANAGER_FORBIDDEN) || str_contains($uri, ' branch-comparison') ? 403 : 200,
            'cashier' => in_array($base, self::CASHIER_ALLOWED) ? 200 : 403,
        };
    }

    private function crawl(User $user, string $role, array $session = []): void
    {
        $failures = [];
        foreach ($this->pages() as $uri => $url) {
            $status = $this->actingAs($user)->withSession($session)->get($url)->getStatusCode();
            $expected = $this->expectedFor($role, $uri);
            if ($status !== $expected) {
                $failures[] = "{$url} → {$status} (expected {$expected})";
            }
        }
        $this->assertSame([], $failures, "{$role}: pages with the wrong status");
    }

    public function test_owner_opens_every_page_on_one_branch(): void
    {
        $this->crawl(User::where('email', 'owner@pos.test')->first(), 'owner', ['current_branch_id' => $this->hyd()->id]);
    }

    public function test_owner_opens_every_page_on_all_branches(): void
    {
        // An applied count with differences is linked to an adjustment (this broke the Stock Count list).
        $manager = User::where('email', 'manager.hyd@pos.test')->first();
        $count = app(StockCountService::class)->start($manager, $this->hyd());
        $item = $count->items()->first();
        app(StockCountService::class)->saveCounts($count, [$item->id => (float) $item->system_quantity + 2]);
        app(StockCountService::class)->apply($count->fresh(), $manager);
        $this->assertNotNull($count->fresh()->stock_adjustment_id);

        $owner = User::where('email', 'owner@pos.test')->first();
        $failures = [];
        foreach ($this->pages() as $uri => $url) {
            $status = $this->actingAs($owner)->withSession(['current_branch_id' => 'all'])->get($url)->getStatusCode();
            // the POS data endpoints need one branch chosen first
            $expected = str_starts_with($uri, 'pos/') && $uri !== 'pos/customers' ? 422 : 200;
            if ($status !== $expected) {
                $failures[] = "{$url} → {$status} (expected {$expected})";
            }
        }
        $this->assertSame([], $failures);
    }

    public function test_report_pdfs_render(): void
    {
        $owner = User::where('email', 'owner@pos.test')->first();
        foreach (['sales', 'profit-loss', 'stock-valuation', 'customer-aging'] as $key) {
            $this->actingAs($owner)->get("/reports/{$key}/export/pdf?preset=today")->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_large_pdf_is_refused_with_a_message_instead_of_crashing(): void
    {
        $owner = User::where('email', 'owner@pos.test')->first();
        $rows = \App\Models\StockMovement::count();
        $this->assertGreaterThan(\App\Http\Controllers\ReportController::PDF_MAX_ROWS, $rows, 'demo data should be big enough');

        $this->actingAs($owner)->from('/reports/stock-movements')->get('/reports/stock-movements/export/pdf?preset=custom&from=2000-01-01&to='.today()->toDateString())
            ->assertRedirect('/reports/stock-movements')->assertSessionHas('error');
        $this->actingAs($owner)->get('/reports/stock-movements/export/print?preset=custom&from=2000-01-01&to='.today()->toDateString())->assertOk();
    }

    public function test_customer_and_supplier_pages_have_a_delete_button_for_allowed_roles(): void
    {
        $customer = Customer::first();
        $supplier = Supplier::first();

        $this->actingAs(User::where('email', 'manager.hyd@pos.test')->first());
        $this->get("/customers/{$customer->id}")->assertSee('data-confirm="Delete '.e($customer->name).'?"', false);
        $this->get("/suppliers/{$supplier->id}")->assertSee('data-confirm="Delete '.e($supplier->name).'?"', false);

        $this->actingAs(User::where('email', 'cashier.hyd@pos.test')->first())
            ->get("/customers/{$customer->id}")->assertOk()->assertDontSee('data-confirm="Delete '.e($customer->name).'?"', false);
    }

    public function test_manager_page_access(): void
    {
        $this->crawl(User::where('email', 'manager.hyd@pos.test')->first(), 'manager');
    }

    public function test_cashier_page_access(): void
    {
        $this->crawl(User::where('email', 'cashier.hyd@pos.test')->first(), 'cashier');
    }

    /** Create, update and invalid data for every master-data module, by owner and manager. */
    public function test_crud_modules_create_update_and_validate(): void
    {
        $owner = User::where('email', 'owner@pos.test')->first();
        $manager = User::where('email', 'manager.hyd@pos.test')->first();
        $hyd = $this->hyd();
        $unit = Unit::first();
        $expCat = ExpenseCategory::first();

        $modules = [
            // [actor, url, valid create data, model, update data, required fields]
            'branches' => [$owner, '/branches', ['name' => 'Larkana', 'code' => 'LRK', 'city' => 'Larkana', 'is_active' => 1], Branch::class, ['name' => 'Larkana City'], ['name', 'code', 'city']],
            'users' => [$owner, '/users', ['name' => 'New Cashier', 'email' => 'nc@pos.test', 'role' => 'cashier', 'branch_id' => $hyd->id, 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'is_active' => 1], User::class, ['name' => 'Renamed Cashier', 'email' => 'nc@pos.test', 'role' => 'cashier', 'branch_id' => $hyd->id, 'is_active' => 1], ['name', 'email', 'role', 'password']],
            'users (manager)' => [$manager, '/users', ['name' => 'Branch Cashier', 'email' => 'bc@pos.test', 'role' => 'cashier', 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'is_active' => 1], User::class, ['name' => 'Branch Cashier 2', 'email' => 'bc@pos.test', 'role' => 'cashier', 'is_active' => 1], ['name', 'email']],
            'categories' => [$owner, '/categories', ['name' => 'QA Frozen', 'color' => '#4F46E5', 'is_active' => 1], Category::class, ['name' => 'Frozen Food', 'color' => '#10B981'], ['name', 'color']],
            'brands' => [$owner, '/brands', ['name' => 'QA Dalda'], Brand::class, ['name' => 'Dalda Foods'], ['name']],
            'units' => [$owner, '/units', ['name' => 'QA Carton', 'short_name' => 'qct', 'allow_decimal' => 0], Unit::class, ['name' => 'QA Cartons', 'short_name' => 'qct'], ['name', 'short_name']],
            'products' => [$manager, '/products', ['name' => 'Rooh Afza 800ml', 'unit_id' => $unit->id, 'purchase_price' => 400, 'sale_price' => 480, 'is_active' => 1], Product::class, ['name' => 'Rooh Afza 1.5L', 'unit_id' => $unit->id, 'purchase_price' => 700, 'sale_price' => 850], ['name', 'unit_id', 'purchase_price', 'sale_price']],
            'customers' => [$manager, '/customers', ['name' => 'Asif Kiryana', 'phone' => '0301-7654321', 'is_active' => 1], Customer::class, ['name' => 'Asif Kiryana Store', 'phone' => '0301-7654321'], ['name', 'phone']],
            'suppliers' => [$manager, '/suppliers', ['name' => 'Haris Traders', 'phone' => '0333-1112223', 'is_active' => 1], Supplier::class, ['name' => 'Haris Traders Pvt'], ['name']],
            'expense categories' => [$owner, '/expense-categories', ['name' => 'QA Internet', 'color' => '#0EA5E9', 'is_active' => 1], ExpenseCategory::class, ['name' => 'Internet & Phone', 'color' => '#0EA5E9'], ['name', 'color']],
            'expenses' => [$manager, '/expenses', ['expense_category_id' => $expCat->id, 'amount' => 1500, 'expense_date' => today()->toDateString(), 'method' => 'cash', 'paid_to' => 'K-Electric'], Expense::class, ['expense_category_id' => $expCat->id, 'amount' => 1750, 'expense_date' => today()->toDateString(), 'method' => 'bank'], ['expense_category_id', 'amount', 'method']],
        ];

        foreach ($modules as $label => [$actor, $url, $data, $model, $update, $required]) {
            $before = $model::max('id');
            $this->actingAs($actor)->from($url)->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
            $record = $model::latest('id')->first();
            $this->assertGreaterThan($before, $record->id, "{$label}: nothing was created");

            $this->actingAs($actor)->from($url)->put("{$url}/{$record->id}", array_merge($data, $update))->assertSessionHasNoErrors()->assertRedirect();
            $field = array_key_exists('name', $update) ? 'name' : 'amount';
            $this->assertEquals($update[$field], $record->fresh()->{$field}, "{$label}: update was not saved");

            $this->actingAs($actor)->from($url)->post($url, [])->assertSessionHasErrors($required);
        }

        // Unique rules ignore the record itself on update (saving an unchanged code/phone/email is fine).
        $branch = Branch::where('code', 'LRK')->first();
        $this->actingAs($owner)->put("/branches/{$branch->id}", ['name' => $branch->name, 'code' => 'LRK', 'city' => 'Larkana'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->put("/branches/{$branch->id}", ['name' => $branch->name, 'code' => 'KHI', 'city' => 'Larkana'])->assertSessionHasErrors('code');
    }

    public function test_master_data_can_be_deleted_unless_in_use(): void
    {
        $owner = User::where('email', 'owner@pos.test')->first();

        $unit = Unit::create(['name' => 'Temp', 'short_name' => 'tmp', 'allow_decimal' => false]);
        $this->actingAs($owner)->delete("/units/{$unit->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($unit);
        $this->actingAs($owner)->delete('/units/'.Product::value('unit_id'))->assertSessionHasErrors();

        $customer = Customer::create(['name' => 'Walk Away', 'phone' => '0300-9998887', 'is_active' => true, 'balance' => 0]);
        $this->actingAs($owner)->delete("/customers/{$customer->id}")->assertRedirect('/customers');
        $this->assertSoftDeleted($customer);
        $owing = Customer::where('balance', '>', 0)->first();
        $this->actingAs($owner)->delete("/customers/{$owing->id}")->assertSessionHasErrors('action');

        $supplier = Supplier::create(['name' => 'Gone Traders', 'is_active' => true, 'balance' => 0]);
        $this->actingAs($owner)->delete("/suppliers/{$supplier->id}")->assertRedirect('/suppliers');
        $this->assertSoftDeleted($supplier);

        $used = $this->hyd();
        $this->actingAs($owner)->delete("/branches/{$used->id}")->assertSessionHasErrors();
        $this->assertNotSoftDeleted($used);
    }
}
