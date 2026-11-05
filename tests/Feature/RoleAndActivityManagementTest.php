<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Unit;
use App\Services\CashRegisterService;
use App\Services\ProductService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndActivityManagementTest extends TestCase
{
    use RefreshDatabase;

    private function rolePayload(array $overrides = []): array
    {
        return array_merge(['name' => 'Senior Cashier', 'base_type' => 'cashier', 'permissions' => ['pos.access', 'sales.view', 'sales.create', 'sales.discount', 'sales.cancel']], $overrides);
    }

    public function test_owner_creates_edits_and_deletes_a_custom_role(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/roles', $this->rolePayload())->assertSessionHasNoErrors()->assertRedirect('/roles');
        $role = Role::findByName('Senior Cashier');
        $this->assertSame('cashier', $role->base_type);
        $this->assertTrue($role->hasPermissionTo('sales.cancel'));

        $this->actingAs($owner)->put("/roles/{$role->id}", $this->rolePayload(['name' => 'Head Cashier', 'permissions' => ['pos.access', 'sales.view', 'sales.create']]))
            ->assertSessionHasNoErrors();
        $role->refresh();
        $this->assertSame('Head Cashier', $role->name);
        $this->assertFalse($role->hasPermissionTo('sales.cancel'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'role_updated']);
        $this->assertStringContainsString('removed sales.discount, sales.cancel', ActivityLog::where('action', 'role_updated')->latest('id')->value('description'));

        $this->actingAs($owner)->get('/roles')->assertOk()->assertSee('Head Cashier');

        $this->actingAs($owner)->delete("/roles/{$role->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('roles', ['name' => 'Head Cashier']);
    }

    public function test_role_validation(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/roles', [])->assertSessionHasErrors(['name', 'base_type']);
        $this->actingAs($owner)->post('/roles', $this->rolePayload(['name' => 'manager']))->assertSessionHasErrors('name');
        $this->actingAs($owner)->post('/roles', $this->rolePayload(['base_type' => 'owner']))->assertSessionHasErrors('base_type');
        // owner-only permissions can never be given away
        $this->actingAs($owner)->post('/roles', $this->rolePayload(['permissions' => ['pos.access', 'settings.manage']]))->assertSessionHasErrors('permissions.1');
        $this->assertDatabaseMissing('roles', ['name' => 'Senior Cashier']);
    }

    public function test_built_in_roles_are_protected_and_can_be_reset(): void
    {
        $owner = $this->owner();
        $owner_ = Role::findByName('owner');
        $manager = Role::findByName('manager');
        $cashier = Role::findByName('cashier');

        $this->actingAs($owner)->put("/roles/{$owner_->id}", $this->rolePayload(['permissions' => []]))->assertForbidden();
        $this->actingAs($owner)->delete("/roles/{$manager->id}")->assertSessionHasErrors('action');
        $this->assertNotNull(Role::findByName('manager'));

        // name/type of a built-in role stay; permissions change
        $this->actingAs($owner)->put("/roles/{$cashier->id}", ['name' => 'Kashier', 'base_type' => 'manager', 'permissions' => ['pos.access', 'sales.create', 'sales.view']])
            ->assertSessionHasNoErrors();
        $cashier->refresh();
        $this->assertSame(['cashier', 'cashier'], [$cashier->name, $cashier->base_type]);
        $this->assertFalse($cashier->hasPermissionTo('customers.create'));

        // seeder no longer overwrites the edit; reset restores defaults
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->assertFalse($cashier->fresh()->hasPermissionTo('customers.create'));
        $this->actingAs($owner)->post("/roles/{$cashier->id}/reset")->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(Permissions::CASHIER, $cashier->fresh()->permissions->pluck('name')->all());
    }

    public function test_role_with_users_cannot_be_deleted(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/roles', $this->rolePayload());
        $role = Role::findByName('Senior Cashier');
        $this->makeUser('Senior Cashier', Branch::factory()->create());

        $this->actingAs($owner)->delete("/roles/{$role->id}")->assertSessionHasErrors('action');
        $this->assertNotNull(Role::find($role->id));
    }

    public function test_only_the_owner_manages_roles(): void
    {
        $manager = $this->makeUser('manager');
        $role = Role::findByName('cashier');

        $this->actingAs($manager)->get('/roles')->assertForbidden();
        $this->actingAs($manager)->post('/roles', $this->rolePayload())->assertForbidden();
        $this->actingAs($manager)->put("/roles/{$role->id}", $this->rolePayload())->assertForbidden();
        $this->actingAs($manager)->delete("/roles/{$role->id}")->assertForbidden();
    }

    public function test_custom_cashier_type_role_sees_only_own_sales_and_manager_type_sees_branch(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/roles', $this->rolePayload());
        $this->actingAs($owner)->post('/roles', ['name' => 'Supervisor', 'base_type' => 'manager', 'permissions' => ['dashboard.view', 'sales.view', 'pos.access', 'sales.create']]);

        $branch = Branch::factory()->create(['code' => 'HYD']);
        $cashier = $this->makeUser('cashier', $branch);
        $senior = $this->makeUser('Senior Cashier', $branch);
        $supervisor = $this->makeUser('Supervisor', $branch);
        $this->assertTrue($senior->isCashier());
        $this->assertTrue($supervisor->isManager());

        $tea = app(ProductService::class)->create(['name' => 'Tapal', 'unit_id' => Unit::factory()->create()->id, 'purchase_price' => 100, 'sale_price' => 150, 'is_active' => true], null, [$branch->id => 10]);
        app(CashRegisterService::class)->open($cashier, $branch->id, 0);
        $this->actingAs($cashier)->postJson('/pos/checkout', ['items' => [['product_id' => $tea->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 150]]])->assertOk();
        $sale = Sale::first();

        $this->actingAs($senior)->get("/sales/{$sale->id}")->assertForbidden();
        $this->actingAs($supervisor)->get("/sales/{$sale->id}")->assertOk();
        $this->actingAs($senior)->get('/settings')->assertForbidden();
    }

    public function test_users_form_offers_custom_roles_and_managers_can_only_give_cashier_types(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/roles', $this->rolePayload());
        $this->actingAs($owner)->post('/roles', ['name' => 'Supervisor', 'base_type' => 'manager', 'permissions' => ['sales.view']]);
        $branch = Branch::factory()->create();
        $manager = $this->makeUser('manager', $branch);

        $this->actingAs($owner)->get('/users')->assertSee('Senior Cashier')->assertSee('Supervisor');
        $this->actingAs($manager)->get('/users')->assertSee('Senior Cashier')->assertDontSee('value="Supervisor"', false);

        $base = ['name' => 'X', 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'is_active' => 1];
        $this->actingAs($manager)->post('/users', $base + ['email' => 'a@pos.test', 'role' => 'Senior Cashier'])->assertSessionHasNoErrors();
        $this->assertSame('Senior Cashier', \App\Models\User::where('email', 'a@pos.test')->first()->role_name);
        $this->actingAs($manager)->post('/users', $base + ['email' => 'b@pos.test', 'role' => 'Supervisor'])->assertSessionHasNoErrors();
        $this->assertSame('cashier', \App\Models\User::where('email', 'b@pos.test')->first()->role_name);

        // owner: manager-type custom role needs a branch and a strong password
        $this->actingAs($owner)->post('/users', ['email' => 'c@pos.test', 'role' => 'Supervisor', 'password' => 'secret123', 'password_confirmation' => 'secret123'] + $base)
            ->assertSessionHasErrors(['branch_id', 'password']);
    }

    public function test_owner_deletes_one_selected_or_old_entries_and_clearing_leaves_a_record(): void
    {
        $owner = $this->owner();
        $old = ActivityLog::create(['action' => 'login', 'description' => 'old one', 'user_id' => $owner->id]);
        $old->forceFill(['created_at' => now()->subDays(200)])->save();
        $a = ActivityLog::create(['action' => 'login', 'description' => 'recent a', 'user_id' => $owner->id]);
        $b = ActivityLog::create(['action' => 'login', 'description' => 'recent b', 'user_id' => $owner->id]);
        $c = ActivityLog::create(['action' => 'login', 'description' => 'recent c', 'user_id' => $owner->id]);

        $this->actingAs($owner)->get('/activity-log')->assertOk()->assertSee('Clear log');

        $this->actingAs($owner)->delete("/activity-log/{$a->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($a);
        $this->actingAs($owner)->post('/activity-log/delete-selected', ['ids' => [$b->id]])->assertSessionHasNoErrors();
        $this->assertModelMissing($b);

        $this->actingAs($owner)->post('/activity-log/clear', ['older_than' => '180', 'password' => 'wrong'])->assertSessionHasErrorsIn('clearLog', 'password');
        $this->assertModelExists($old);

        $this->actingAs($owner)->post('/activity-log/clear', ['older_than' => '180', 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertModelMissing($old);
        $this->assertModelExists($c);

        $this->actingAs($owner)->post('/activity-log/clear', ['older_than' => 'all', 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame(1, ActivityLog::count());
        $this->assertSame('activity_cleared', ActivityLog::first()->action);
        $this->assertStringContainsString('everything', ActivityLog::first()->description);
    }

    public function test_only_the_owner_can_delete_activity(): void
    {
        $manager = $this->makeUser('manager');
        $log = ActivityLog::create(['action' => 'login', 'description' => 'x']);

        $this->actingAs($manager)->delete("/activity-log/{$log->id}")->assertForbidden();
        $this->actingAs($manager)->post('/activity-log/delete-selected', ['ids' => [$log->id]])->assertForbidden();
        $this->actingAs($manager)->post('/activity-log/clear', ['older_than' => 'all', 'password' => 'password'])->assertForbidden();
        $this->assertModelExists($log);
    }
}
