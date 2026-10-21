<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NutritionDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_admin_login_opens_nutrition_home_with_team_management(): void
    {
        $user = $this->user(User::ROLE_TASK_ADMIN);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('nutrition-tasks.dashboard'));
        $this->get(route('nutrition-tasks.dashboard'))->assertOk()
            ->assertSee('Manage Nutrition Team Users')->assertSee(route('admin.users.index', ['category' => 'micronutrient']))
            ->assertDontSee('Manager Lists')->assertDontSee('Infrastructure')
            ->assertDontSee('Manage ICT Users')->assertDontSee('Manage Roles');
        $this->get(route('admin.users.index', ['category' => 'micronutrient']))->assertOk();
        $this->get(route('lists.index', 'systems'))->assertForbidden();
        $this->get(route('infrastructure.alis-remote-backup-keys.index'))->assertForbidden();
    }

    public function test_nutrition_users_visiting_the_ict_dashboard_are_redirected_to_nutrition_home(): void
    {
        foreach ([User::ROLE_TASK_ADMIN, User::ROLE_NUTRITION_USER] as $role) {
            $this->actingAs($this->user($role))->get(route('dashboard'))
                ->assertRedirect(route('nutrition-tasks.dashboard'));
        }
    }

    public function test_nutrition_members_do_not_get_team_management_access(): void
    {
        $this->actingAs($this->user(User::ROLE_NUTRITION_USER));
        $this->get(route('nutrition-tasks.dashboard'))->assertOk()
            ->assertDontSee('Manage Nutrition Team Users')->assertDontSee('Manager Lists')->assertDontSee('Infrastructure');
        $this->get(route('admin.users.index', ['category' => 'micronutrient']))->assertForbidden();
    }

    public function test_custom_task_admin_role_uses_nutrition_home_and_global_admin_keeps_ict_home(): void
    {
        $role = Role::query()->create([
            'name' => 'Nutrition Lead', 'code' => 'nutrition_lead', 'category' => Role::MICRONUTRIENT,
            'access_role' => User::ROLE_TASK_ADMIN,
        ]);
        $user = $this->user(User::ROLE_TASK_ADMIN);
        $user->update(['managed_role_id' => $role->id]);
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('nutrition-tasks.dashboard'));
        $this->get(route('nutrition-tasks.dashboard'))->assertOk()->assertSee('Manage Nutrition Team Users');

        $this->actingAs($this->user(User::ROLE_ICT_ADMIN))->get(route('dashboard'))
            ->assertOk()->assertSee('Manage Users')->assertSee('Manager Lists');
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role, 'active' => true, 'force_password_change' => false, 'password_changed_at' => now(),
        ]);
    }
}
