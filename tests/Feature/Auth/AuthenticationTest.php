<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back');
    }

    public function test_users_can_authenticate_and_login_is_recorded(): void
    {
        $user = $this->owner();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertSame('127.0.0.1', $user->fresh()->last_login_ip);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'login']);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = $this->owner();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = $this->makeUser('cashier', null, ['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Your account has been deactivated. Please contact the owner.']);

        $this->assertGuest();
    }

    public function test_user_of_inactive_branch_cannot_log_in(): void
    {
        $branch = Branch::factory()->create(['is_active' => false, 'name' => 'Closed Branch']);
        $user = $this->makeUser('manager', $branch);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Closed Branch', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_logged_in_user_is_signed_out_when_branch_is_deactivated(): void
    {
        $branch = Branch::factory()->create();
        $user = $this->makeUser('cashier', $branch);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $branch->update(['is_active' => false]);

        $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = $this->owner();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
