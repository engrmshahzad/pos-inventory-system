<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Zain Ali',
            'email' => 'zain@pos.test',
            'phone' => '0300-0000000',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'is_active' => '1',
        ];
    }

    public function test_owner_can_create_manager_with_avatar(): void
    {
        Storage::fake('public');
        $branch = Branch::factory()->create();

        $this->actingAs($this->owner())->post('/users', $this->payload([
            'role' => 'manager', 'branch_id' => $branch->id, 'avatar' => UploadedFile::fake()->image('a.jpg'),
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', 'zain@pos.test')->first();
        $this->assertTrue($user->isManager());
        $this->assertSame($branch->id, $user->branch_id);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_branch_is_required_for_manager_and_cashier(): void
    {
        $this->actingAs($this->owner())->post('/users', $this->payload(['role' => 'cashier']))
            ->assertSessionHasErrors('branch_id');
    }

    public function test_owner_role_has_no_branch(): void
    {
        $branch = Branch::factory()->create();

        $this->actingAs($this->owner())->post('/users', $this->payload(['role' => 'owner', 'branch_id' => $branch->id]))
            ->assertSessionHasNoErrors();

        $this->assertNull(User::where('email', 'zain@pos.test')->value('branch_id'));
    }

    public function test_manager_creates_only_cashiers_in_own_branch(): void
    {
        $manager = $this->makeUser('manager');
        $otherBranch = Branch::factory()->create();

        // Tries to create a manager in another branch: forced to cashier in own branch.
        $this->actingAs($manager)->post('/users', $this->payload(['role' => 'manager', 'branch_id' => $otherBranch->id]))
            ->assertSessionHasNoErrors();

        $created = User::where('email', 'zain@pos.test')->first();
        $this->assertTrue($created->isCashier());
        $this->assertSame($manager->branch_id, $created->branch_id);
    }

    public function test_manager_can_edit_own_branch_cashier_only(): void
    {
        $manager = $this->makeUser('manager');
        $ownCashier = $this->makeUser('cashier', $manager->branch);
        $otherCashier = $this->makeUser('cashier');
        $peerManager = $this->makeUser('manager', $manager->branch);

        $this->actingAs($manager)->put("/users/{$ownCashier->id}", $this->payload([
            'name' => 'Updated', 'email' => $ownCashier->email, 'password' => '', 'password_confirmation' => '',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('Updated', $ownCashier->fresh()->name);

        $this->actingAs($manager)->put("/users/{$otherCashier->id}", $this->payload(['email' => $otherCashier->email]))->assertForbidden();
        $this->actingAs($manager)->put("/users/{$peerManager->id}", $this->payload(['email' => $peerManager->email]))->assertForbidden();
        $this->actingAs($manager)->delete("/users/{$otherCashier->id}")->assertForbidden();
        $this->actingAs($manager)->patch("/users/{$peerManager->id}/toggle")->assertForbidden();
    }

    public function test_manager_only_lists_own_branch_staff(): void
    {
        $manager = $this->makeUser('manager');
        $own = $this->makeUser('cashier', $manager->branch, ['name' => 'Own Branch Cashier']);
        $other = $this->makeUser('cashier', null, ['name' => 'Other Branch Cashier']);

        $this->actingAs($manager)->get('/users')
            ->assertSee('Own Branch Cashier')
            ->assertDontSee('Other Branch Cashier');
    }

    public function test_cashier_cannot_manage_users(): void
    {
        $cashier = $this->makeUser('cashier');

        $this->actingAs($cashier)->post('/users', $this->payload(['role' => 'cashier']))->assertForbidden();
    }

    public function test_owner_cannot_deactivate_or_delete_self(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put("/users/{$owner->id}", $this->payload([
            'email' => $owner->email, 'role' => 'owner', 'is_active' => '0', 'password' => '', 'password_confirmation' => '',
        ]))->assertSessionHasErrors('is_active');

        $this->actingAs($owner)->delete("/users/{$owner->id}")->assertForbidden();
    }

    public function test_profile_update(): void
    {
        $user = $this->makeUser('cashier');

        $this->actingAs($user)->patch('/profile', ['name' => 'New Name', 'email' => $user->email, 'phone' => '0311-1111111'])
            ->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertSame('New Name', $user->fresh()->name);
    }
}
