<?php

namespace Tests;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $seedRoles = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->seedRoles && in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive($this))) {
            $this->seed(RolePermissionSeeder::class);
        }
    }

    protected function makeUser(string $role, ?Branch $branch = null, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + [
            'branch_id' => $role === 'owner' ? null : ($branch ?? Branch::factory()->create())->id,
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function owner(array $attributes = []): User
    {
        return $this->makeUser('owner', null, $attributes);
    }
}
