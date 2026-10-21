<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NutritionUserEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_loads_user_into_the_single_left_form_with_only_nutrition_roles(): void
    {
        $role = Role::query()->create([
            'name' => 'Nutrition Analyst', 'code' => 'nutrition_analyst',
            'category' => Role::MICRONUTRIENT, 'access_role' => User::ROLE_NUTRITION_USER,
        ]);
        $user = User::factory()->create([
            'name' => 'Jane Analyst', 'email' => 'jane@example.com', 'phone' => '0700123456',
            'role' => User::ROLE_NUTRITION_USER, 'managed_role_id' => $role->id, 'active' => false,
        ]);
        $profile = $user->nutritionProfile()->create(['designation' => 'Analyst', 'place_of_work' => 'CPHL']);

        $response = $this->actingAs($this->admin())->get(route('admin.users.index', ['edit' => $user->id]));
        $response->assertOk()->assertSee('Edit User')->assertSee('>Update</button>', false)
            ->assertSee('action="'.route('admin.users.update', $user).'"', false)
            ->assertSee('value="Jane Analyst"', false)->assertSee('value="jane@example.com"', false)
            ->assertSee('value="0700123456"', false)->assertSee('value="Analyst"', false)->assertSee('value="CPHL"', false)
            ->assertSee('value="nutrition_analyst" checked', false)->assertSee('Cancel')
            ->assertDontSee('value="ict_admin"', false)->assertDontSee('value="ict_manager"', false)
            ->assertDontSee('<details>', false)->assertDontSee('Create User')
            ->assertDontSee('name="password"', false)->assertDontSee('name="password_confirmation"', false);
        $this->assertSame(3, substr_count($response->getContent(), 'name="roles[]"'));
        $this->assertDoesNotMatchRegularExpression('/id="user-active"[^>]*checked/', $response->getContent());

        $this->get(route('admin.users.index'))->assertOk()->assertSee('Add User')
            ->assertSee('Create User')->assertDontSee('name="password"', false);
    }

    public function test_update_cannot_reset_password_and_returns_to_edit_mode(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_NUTRITION_USER]);
        $password = $user->getRawOriginal('password');
        $profile = $user->nutritionProfile()->create(['designation' => 'Analyst', 'place_of_work' => 'CPHL']);
        $this->actingAs($this->admin())->put(route('admin.users.update', $user), [
            'name' => 'Updated Name', 'email' => $user->email, 'phone' => '0700123456',
            'designation' => 'Senior Analyst', 'place_of_work' => 'Regional Office',
            'role' => User::ROLE_NUTRITION_USER, 'active' => '0', 'password' => 'Replacement123!', 'password_confirmation' => 'Replacement123!',
        ])->assertRedirect(route('admin.users.index', ['edit' => $user->id]))->assertSessionHasNoErrors();
        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertFalse($user->fresh()->active);
        $this->assertSame($password, $user->fresh()->getRawOriginal('password'));
        $this->assertSame('Regional Office', $profile->fresh()->place_of_work);
    }

    public function test_invalid_update_keeps_edit_mode_input_and_inactive_status(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_NUTRITION_USER]);
        $profile = $user->nutritionProfile()->create(['designation' => 'Analyst', 'place_of_work' => 'CPHL']);
        $editUrl = route('admin.users.index', ['edit' => $user->id]);
        $this->actingAs($this->admin())->from($editUrl)->put(route('admin.users.update', $user), [
            'name' => 'Pending Name', 'email' => 'invalid', 'designation' => 'Analyst',
            'place_of_work' => 'CPHL', 'role' => User::ROLE_NUTRITION_USER, 'active' => '0',
        ])->assertRedirect($editUrl)->assertSessionHasErrors('email');
        $response = $this->get($editUrl)->assertOk()->assertSee('value="Pending Name"', false)->assertSee('>Update</button>', false);
        $this->assertDoesNotMatchRegularExpression('/id="user-active"[^>]*checked/', $response->getContent());
        $this->assertNotSame('Pending Name', $user->fresh()->name);
    }

    public function test_edit_rejects_missing_profiles_and_unauthorized_users(): void
    {
        $this->actingAs($this->admin())->get(route('admin.nutrition-users.index', ['edit' => 999999]))->assertNotFound();
        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_NUTRITION_USER, 'force_password_change' => false, 'password_changed_at' => now(),
        ]))->get(route('admin.nutrition-users.index', ['edit' => 999999]))->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_TASK_ADMIN, 'force_password_change' => false, 'password_changed_at' => now(),
        ]);
    }
}
