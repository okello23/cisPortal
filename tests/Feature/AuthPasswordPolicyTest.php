<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthPasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_temporary_password_is_redirected_to_change_password_after_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'force_password_change' => true,
            'password_changed_at' => null,
        ]);

        $response = $this->post(route('login.store', [], false), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('password.change.edit', [], false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_password_blocks_access_to_dashboard(): void
    {
        $user = User::factory()->create([
            'password_changed_at' => now()->subDays(91),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [], false));

        $response->assertRedirect(route('password.change.edit', [], false));
    }

    public function test_authenticated_user_can_change_password_and_clear_policy_flags(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'force_password_change' => true,
            'password_changed_at' => null,
        ]);

        $response = $this->actingAs($user)->put(route('password.change.update', [], false), [
            'current_password' => 'password123',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect(route('dashboard', [], false));

        $user->refresh();

        $this->assertFalse($user->force_password_change);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_password_change_rejects_password_without_required_complexity(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'force_password_change' => true,
            'password_changed_at' => null,
        ]);

        $response = $this->from(route('password.change.edit', [], false))
            ->actingAs($user)
            ->put(route('password.change.update', [], false), [
                'current_password' => 'password123',
                'password' => 'lowercase1',
                'password_confirmation' => 'lowercase1',
            ]);

        $response->assertRedirect(route('password.change.edit', [], false));
        $response->assertSessionHasErrors('password');
    }

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post(route('password.email', [], false), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_updates_password_policy_fields(): void
    {
        $user = User::factory()->create([
            'force_password_change' => true,
            'password_changed_at' => null,
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update', [], false), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect(route('login', [], false));

        $user->refresh();

        $this->assertFalse($user->force_password_change);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_password_reset_rejects_password_without_required_complexity(): void
    {
        $user = User::factory()->create();

        $token = Password::broker()->createToken($user);

        $response = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email], false))
            ->post(route('password.update', [], false), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'password1',
                'password_confirmation' => 'password1',
            ]);

        $response->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email], false));
        $response->assertSessionHasErrors('password');
    }
}
