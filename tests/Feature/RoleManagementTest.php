<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_roles_are_available_in_the_correct_categories(): void
    {
        $this->assertSame(5, Role::query()->where('category', Role::GLOBAL)->count());
        $this->assertSame(2, Role::query()->where('category', Role::MICRONUTRIENT)->count());
        foreach (User::roleLabels() as $code => $name) {
            $this->assertDatabaseHas('roles', ['code' => $code, 'name' => $name, 'access_role' => $code]);
        }
        $this->actingAs($this->user(User::ROLE_ICT_ADMIN))
            ->get(route('admin.roles.index'))->assertOk()->assertSee('Global Roles')->assertSee('Micronutrient Roles');
    }

    public function test_admin_can_create_rename_and_delete_an_unused_custom_role(): void
    {
        $this->actingAs($this->user(User::ROLE_ICT_ADMIN));
        $this->post(route('admin.roles.store'), [
            'name' => 'Nutrition Analyst', 'category' => Role::MICRONUTRIENT, 'access_role' => User::ROLE_NUTRITION_USER,
        ])->assertSessionHasNoErrors();
        $role = Role::query()->where('name', 'Nutrition Analyst')->sole();
        $this->put(route('admin.roles.update', $role), [
            'name' => 'Nutrition Officer', 'category' => Role::MICRONUTRIENT, 'access_role' => User::ROLE_NUTRITION_USER,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Nutrition Officer', $role->fresh()->name);
        $this->delete(route('admin.roles.destroy', $role))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.deleted']);
    }

    public function test_only_ict_admins_can_manage_roles(): void
    {
        $role = Role::query()->firstOrFail();
        $this->get(route('admin.roles.index'))->assertRedirect(route('login'));
        foreach ([User::ROLE_TASK_ADMIN, User::ROLE_NUTRITION_USER, User::ROLE_ICT_MANAGER, User::ROLE_ICT_SUPPORT_STAFF] as $access) {
            $this->actingAs($this->user($access));
            $this->get(route('admin.roles.index'))->assertForbidden();
            $this->post(route('admin.roles.store'), [])->assertForbidden();
            $this->put(route('admin.roles.update', $role), [])->assertForbidden();
            $this->delete(route('admin.roles.destroy', $role))->assertForbidden();
        }
    }

    public function test_role_category_must_match_its_access_level_and_names_are_unique(): void
    {
        $this->actingAs($this->user(User::ROLE_ICT_ADMIN));
        $this->post(route('admin.roles.store'), [
            'name' => 'Nutrition Admin', 'category' => Role::MICRONUTRIENT, 'access_role' => User::ROLE_ICT_ADMIN,
        ])->assertSessionHasErrors('access_role');
        $this->post(route('admin.roles.store'), [
            'name' => 'ICT Admin', 'category' => Role::GLOBAL, 'access_role' => User::ROLE_ICT_ADMIN,
        ])->assertSessionHasErrors('name');
        $this->post(route('admin.roles.store'), [
            'name' => 'Unknown', 'category' => 'unknown', 'access_role' => User::ROLE_NUTRITION_USER,
        ])->assertSessionHasErrors('category');
    }

    public function test_built_in_roles_can_be_renamed_but_cannot_be_deleted_or_change_permissions(): void
    {
        $this->actingAs($this->user(User::ROLE_ICT_ADMIN));
        $role = Role::query()->where('code', User::ROLE_NUTRITION_USER)->sole();
        $this->put(route('admin.roles.update', $role), [
            'name' => 'Micronutrient Team Member', 'category' => Role::MICRONUTRIENT, 'access_role' => User::ROLE_NUTRITION_USER,
        ])->assertSessionHasNoErrors();
        $this->get(route('admin.users.index'))->assertSee('Micronutrient Team Member');
        $this->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');
        $this->put(route('admin.roles.update', $role), [
            'name' => $role->name, 'category' => Role::GLOBAL, 'access_role' => User::ROLE_ICT_ADMIN,
        ])->assertSessionHasErrors('category');
    }

    public function test_assigned_roles_cannot_be_deleted_or_change_category_or_permissions(): void
    {
        $role = Role::query()->create([
            'name' => 'Analyst', 'code' => 'analyst', 'category' => Role::MICRONUTRIENT, 'access_role' => User::ROLE_NUTRITION_USER,
        ]);
        $user = $this->user(User::ROLE_NUTRITION_USER);
        $user->syncRoles([$role->id]);
        $this->actingAs($this->user(User::ROLE_ICT_ADMIN));
        $this->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');
        $this->put(route('admin.roles.update', $role), [
            'name' => $role->name, 'category' => Role::MICRONUTRIENT, 'access_role' => User::ROLE_TASK_ADMIN,
        ])->assertSessionHasErrors('category');
        $this->assertSame(User::ROLE_NUTRITION_USER, $role->fresh()->access_role);
    }

    public function test_migration_preserves_existing_users_and_links_their_roles(): void
    {
        $multiple = require database_path('migrations/2026_10_22_000100_add_multiple_user_roles.php');
        $migration = require database_path('migrations/2026_10_21_000100_create_managed_roles_table.php');
        $multiple->down();
        $migration->down();
        $id = DB::table('users')->insertGetId([
            'name' => 'Existing Admin', 'email' => 'existing@example.com', 'password' => 'existing-hash',
            'role' => User::ROLE_ICT_ADMIN, 'active' => true,
        ]);
        $migration->up();
        $multiple->up();
        $user = User::query()->findOrFail($id);
        $this->assertSame(User::ROLE_ICT_ADMIN, $user->role);
        $this->assertSame('existing-hash', $user->getRawOriginal('password'));
        $this->assertSame(User::ROLE_ICT_ADMIN, $user->managedRole->code);
        $this->assertTrue($user->hasAnyRole([User::ROLE_ICT_ADMIN]));
    }

    public function test_old_nutrition_management_link_redirects_to_unified_users(): void
    {
        $this->actingAs($this->user(User::ROLE_TASK_ADMIN))->get(route('admin.nutrition-users.index'))
            ->assertRedirect(route('admin.users.index', ['category' => Role::MICRONUTRIENT]));
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true, 'force_password_change' => false, 'password_changed_at' => now()]);
    }
}
