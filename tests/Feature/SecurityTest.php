<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Unit;
use App\Services\CashRegisterService;
use App\Services\LedgerService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $user = $this->owner();

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $owner = $this->owner();
        $branch = Branch::factory()->create();
        $base = ['name' => 'X', 'email' => 'x@pos.test', 'branch_id' => $branch->id, 'is_active' => 1];

        $this->actingAs($owner)->post('/users', $base + ['role' => 'cashier', 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrors('password');
        $this->actingAs($owner)->post('/users', $base + ['role' => 'manager', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertSessionHasErrors('password');
        $this->actingAs($owner)->post('/users', $base + ['role' => 'cashier', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertSessionHasNoErrors();
    }

    public function test_svg_uploads_are_rejected(): void
    {
        $owner = $this->owner();
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($owner)->post('/brands', ['name' => 'Evil', 'logo' => $svg])->assertSessionHasErrors('logo');
        $this->actingAs($owner)->put('/settings/general', [
            'business_name' => 'X', 'currency_symbol' => 'Rs', 'number_format' => 'international', 'date_format' => 'd M Y', 'timezone' => 'Asia/Karachi', 'session_timeout' => 60,
            'business_logo' => $svg,
        ])->assertSessionHasErrors('business_logo');
    }

    public function test_spreadsheet_formula_cells_are_neutralised(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", \App\Support\SafeCell::text('=HYPERLINK("x")'));
        $this->assertSame("'@SUM(A1)", \App\Support\SafeCell::text('@SUM(A1)'));
        $this->assertSame('-150', \App\Support\SafeCell::text('-150')); // numbers stay numbers
        $this->assertSame('Tapal', \App\Support\SafeCell::text('Tapal'));
    }

    public function test_changing_password_signs_out_other_devices(): void
    {
        $user = $this->makeUser('cashier');
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($user)->put('/password', ['current_password' => 'password', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
    }

    public function test_session_timeout_comes_from_settings(): void
    {
        \App\Models\Setting::set('session_timeout', '30');
        (new \App\Providers\AppServiceProvider($this->app))->boot();

        $this->assertSame(30, config('session.lifetime'));
    }

    public function test_managers_cannot_delete_shared_products(): void
    {
        $manager = $this->makeUser('manager');
        $product = \App\Models\Product::factory()->create();

        $this->actingAs($manager)->delete("/products/{$product->id}")->assertForbidden();
        $this->actingAs($manager)->post('/products/bulk', ['action' => 'delete', 'ids' => [$product->id]])->assertForbidden();
        $this->actingAs($manager)->post('/products/bulk', ['action' => 'deactivate', 'ids' => [$product->id]])->assertRedirect();
    }

    private function saleSetup(): array
    {
        $branch = Branch::factory()->create(['code' => 'HYD']);
        $cashier = $this->makeUser('cashier', $branch);
        $manager = $this->makeUser('manager', $branch);
        $manager->forceFill(['pin' => '2468'])->save();
        $unit = Unit::factory()->create();
        $tea = app(ProductService::class)->create(['name' => 'Tapal', 'unit_id' => $unit->id, 'purchase_price' => 1000, 'sale_price' => 1500, 'is_active' => true], null, [$branch->id => 10]);
        app(CashRegisterService::class)->open($cashier, $branch->id, 2000);

        return [$branch, $cashier, $manager, $tea];
    }

    public function test_cashier_needs_manager_pin_to_cancel_and_everything_reverses(): void
    {
        [$branch, $cashier, $manager, $tea] = $this->saleSetup();
        $customer = Customer::factory()->create();
        $this->actingAs($cashier)->postJson('/pos/checkout', [
            'customer_id' => $customer->id, 'items' => [['product_id' => $tea->id, 'quantity' => 2]], 'payments' => [['method' => 'cash', 'amount' => 1000]],
        ])->assertOk();
        $sale = Sale::first();
        $this->assertEquals(2000, $customer->fresh()->balance);

        $this->actingAs($cashier)->post("/sales/{$sale->id}/cancel", ['cancel_reason' => 'Wrong bill'])->assertSessionHasErrors('pin');
        $this->actingAs($cashier)->post("/sales/{$sale->id}/cancel", ['cancel_reason' => 'Wrong bill', 'pin' => '9999'])->assertSessionHasErrors('pin');
        $this->assertSame('completed', $sale->fresh()->status);

        $this->actingAs($cashier)->post("/sales/{$sale->id}/cancel", ['cancel_reason' => 'Wrong bill', 'pin' => '2468'])->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame('cancelled', $sale->status);
        $this->assertSame($manager->id, $sale->cancel_approved_by);
        $this->assertEquals(10, $tea->stocks()->where('branch_id', $branch->id)->value('quantity'));
        $this->assertEquals(0, $customer->fresh()->balance);
        $this->assertEquals(0, app(LedgerService::class)->customer($customer->fresh())['closing']);
        // the cash taken (1000) was refunded from the till
        $this->assertEquals(2000, app(CashRegisterService::class)->summary(CashRegister::first())['expected_cash']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'sale_cancelled']);

        $this->actingAs($manager)->post("/sales/{$sale->id}/cancel", ['cancel_reason' => 'Again'])->assertSessionHasErrors('reason');
    }

    public function test_manager_cancels_without_pin_and_sale_with_returns_cannot_be_cancelled(): void
    {
        [$branch, $cashier, $manager, $tea] = $this->saleSetup();
        $this->actingAs($cashier)->postJson('/pos/checkout', ['items' => [['product_id' => $tea->id, 'quantity' => 3]], 'payments' => [['method' => 'cash', 'amount' => 4500]]])->assertOk();
        $sale = Sale::with('items')->first();
        $this->actingAs($cashier)->post('/sale-returns', ['sale_id' => $sale->id, 'items' => [$sale->items[0]->id => 1], 'refund_method' => 'cash']);

        $this->actingAs($manager)->post("/sales/{$sale->id}/cancel", ['cancel_reason' => 'Mistake'])->assertSessionHasErrors('reason');

        $this->actingAs($cashier)->postJson('/pos/checkout', ['items' => [['product_id' => $tea->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 1500]]]);
        $second = Sale::latest('id')->first();
        $this->actingAs($manager)->post("/sales/{$second->id}/cancel", ['cancel_reason' => 'Mistake'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $second->fresh()->status);
    }

    public function test_price_overrides_and_big_discounts_are_logged(): void
    {
        [$branch, , $manager, $tea] = $this->saleSetup();
        app(CashRegisterService::class)->open($manager, $branch->id, 0);

        $this->actingAs($manager)->postJson('/pos/checkout', [
            'items' => [['product_id' => $tea->id, 'quantity' => 1, 'unit_price' => 1200]],
            'discount_value' => 300, 'payments' => [['method' => 'cash', 'amount' => 900]],
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', ['action' => 'price_override']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'large_discount']);
    }

    public function test_demo_reset_refuses_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('pos:demo-reset', ['--force' => true])->assertFailed();
        $this->assertDatabaseCount('users', 0); // nothing was touched
    }
}
