<?php

namespace Tests\Feature;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_branch_with_uppercased_code(): void
    {
        $this->actingAs($this->owner())->post('/branches', [
            'name' => 'Lahore Liberty', 'code' => 'lhr', 'city' => 'Lahore', 'is_active' => '1',
        ])->assertRedirect(route('branches.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branches', ['code' => 'LHR', 'is_active' => true]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'created', 'subject_type' => Branch::class]);
    }

    public function test_branch_code_must_be_three_letters_and_unique(): void
    {
        $owner = $this->owner();
        Branch::factory()->create(['code' => 'HYD']);

        $this->actingAs($owner)->post('/branches', ['name' => 'X', 'code' => 'HYD', 'city' => 'X'])->assertSessionHasErrors('code');
        $this->actingAs($owner)->post('/branches', ['name' => 'X', 'code' => 'HY1', 'city' => 'X'])->assertSessionHasErrors('code');
        $this->actingAs($owner)->post('/branches', ['name' => 'X', 'code' => 'HYDR', 'city' => 'X'])->assertSessionHasErrors('code');
    }

    public function test_owner_can_update_and_toggle_branch(): void
    {
        $owner = $this->owner();
        $branch = Branch::factory()->create();

        $this->actingAs($owner)->put("/branches/{$branch->id}", [
            'name' => 'Renamed', 'code' => $branch->code, 'city' => 'Hyderabad', 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $branch->fresh()->name);

        $this->actingAs($owner)->patch("/branches/{$branch->id}/toggle");
        $this->assertFalse($branch->fresh()->is_active);
    }

    public function test_branch_with_staff_cannot_be_deleted(): void
    {
        $owner = $this->owner();
        $branch = Branch::factory()->create();
        $this->makeUser('cashier', $branch);

        $this->actingAs($owner)->delete("/branches/{$branch->id}")->assertSessionHasErrors('branch');
        $this->assertNotSoftDeleted($branch);
    }

    public function test_empty_branch_can_be_deleted(): void
    {
        $branch = Branch::factory()->create();

        $this->actingAs($this->owner())->delete("/branches/{$branch->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted($branch);
    }

    public function test_manager_cannot_manage_branches(): void
    {
        $manager = $this->makeUser('manager');

        $this->actingAs($manager)->post('/branches', ['name' => 'X', 'code' => 'XYZ', 'city' => 'X'])->assertForbidden();
        $this->actingAs($manager)->patch("/branches/{$manager->branch_id}/toggle")->assertForbidden();
    }
}
