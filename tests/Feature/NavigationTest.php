<?php

namespace Tests\Feature;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_every_page(): void
    {
        $owner = $this->owner();
        Branch::factory()->create();

        foreach (['/dashboard', '/branches', '/users', '/roles', '/ui-kit', '/profile', '/products', '/pos', '/reports', '/settings'] as $uri) {
            $this->actingAs($owner)->get($uri)->assertOk();
        }
    }

    public function test_owner_sees_full_menu_and_branch_switcher(): void
    {
        $this->actingAs($this->owner())->get('/dashboard')
            ->assertSee('Switch branch')
            ->assertSee('All Branches')
            ->assertSee(route('branches.index'))
            ->assertSee(route('ui-kit'));
    }

    public function test_manager_menu_and_access(): void
    {
        $manager = $this->makeUser('manager');

        $this->actingAs($manager)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Switch branch')
            ->assertSee($manager->branch->name)
            ->assertSee(route('users.index'))
            ->assertDontSee(route('branches.index'));

        $this->actingAs($manager)->get('/users')->assertOk();
        $this->actingAs($manager)->get('/products')->assertOk();
        $this->actingAs($manager)->get('/branches')->assertForbidden();
        $this->actingAs($manager)->get('/ui-kit')->assertForbidden();
        $this->actingAs($manager)->get('/settings')->assertForbidden();
        $this->actingAs($manager)->post('/branch/switch', ['branch' => 'all'])->assertForbidden();
    }

    public function test_cashier_menu_and_access(): void
    {
        $cashier = $this->makeUser('cashier');

        $this->actingAs($cashier)->get('/dashboard')
            ->assertOk()
            ->assertSee(route('pos.index'))
            ->assertDontSee(route('users.index'))
            ->assertDontSee(route('purchases.index'))
            ->assertDontSee("Today's Profit");

        $this->actingAs($cashier)->get('/pos')->assertOk();
        $this->actingAs($cashier)->get('/users')->assertForbidden();
        $this->actingAs($cashier)->get('/products')->assertForbidden();
        $this->actingAs($cashier)->get('/branches')->assertForbidden();
    }

    public function test_owner_can_switch_branch(): void
    {
        $owner = $this->owner();
        $branch = Branch::factory()->create(['name' => 'Karachi Test']);

        $this->actingAs($owner)->post('/branch/switch', ['branch' => $branch->id])->assertRedirect();
        $this->assertSame($branch->id, session('current_branch_id'));
        $this->actingAs($owner)->get('/dashboard')->assertSee('Karachi Test');

        $this->actingAs($owner)->post('/branch/switch', ['branch' => 'all']);
        $this->assertNull(session('current_branch_id'));
    }

    public function test_owner_cannot_switch_to_inactive_branch(): void
    {
        $branch = Branch::factory()->create(['is_active' => false]);

        $this->actingAs($this->owner())->post('/branch/switch', ['branch' => $branch->id])->assertNotFound();
    }

    public function test_current_branch_id_ignores_session_for_non_owners(): void
    {
        $manager = $this->makeUser('manager');
        $other = Branch::factory()->create();

        $this->actingAs($manager)->withSession(['current_branch_id' => $other->id])->get('/dashboard')->assertOk();
        $this->assertSame($manager->branch_id, currentBranchId());
    }
}
