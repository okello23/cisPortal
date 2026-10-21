<?php

namespace Tests\Feature;

use App\Models\NutritionTask;
use App\Models\Role;
use App\Models\User;
use App\Support\TicketRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MultipleUserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_account_can_be_created_with_global_and_micronutrient_roles(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->actingAs($this->user(User::ROLE_ICT_ADMIN));
        $this->post(route('admin.users.store'), $this->payload(['ict_manager', 'task_admin']))->assertSessionHasNoErrors();
        $user = User::query()->where('email', 'combined@example.com')->sole();
        $this->assertCount(2, $user->assignedRoles);
        $this->assertTrue($user->hasAnyRole([User::ROLE_ICT_MANAGER]));
        $this->assertTrue($user->hasAnyRole([User::ROLE_TASK_ADMIN]));
        $this->assertSame('Analyst', $user->designation);
        $this->assertSame('CPHL', $user->place_of_work);
        $this->assertSame($user->id, $user->nutritionProfile->user_id);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_existing_global_account_can_gain_nutrition_roles_without_changing_identity_or_password(): void
    {
        $user = $this->user(User::ROLE_ICT_MANAGER);
        $password = $user->getRawOriginal('password');
        $this->actingAs($this->user(User::ROLE_ICT_ADMIN))
            ->put(route('admin.users.update', $user), [...$this->payload(['ict_manager', 'task_admin']), 'email' => $user->email])
            ->assertSessionHasNoErrors();
        $this->assertCount(2, $user->fresh()->assignedRoles);
        $this->assertSame($password, $user->fresh()->getRawOriginal('password'));
        $this->assertSame($user->id, $user->fresh()->nutritionProfile->user_id);
    }

    public function test_dual_role_login_shows_both_workspaces_with_separate_content(): void
    {
        $user = $this->user(User::ROLE_ICT_MANAGER);
        $this->assign($user, ['ict_manager', 'task_admin']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Support Tickets')->assertSee('Micronutrient')
            ->assertSee('Recent Tickets')->assertDontSee('Recent Team Tasks');
        $this->get(route('nutrition-tasks.dashboard'))->assertOk()->assertSee('Support Tickets')->assertSee('Micronutrient')
            ->assertSee('Recent Team Tasks')->assertSee('Manage Nutrition Team Users')->assertDontSee('Recent Tickets');
    }

    public function test_single_category_accounts_cannot_open_the_other_workspace(): void
    {
        $global = $this->user(User::ROLE_ICT_SUPPORT_STAFF);
        $this->assign($global, ['ict_support_staff']);
        $this->actingAs($global)->get(route('dashboard'))->assertOk()->assertSee('Support Tickets')->assertDontSee('>Micronutrient</a>', false);
        $this->get(route('nutrition-tasks.dashboard'))->assertForbidden();

        $nutrition = $this->user(User::ROLE_NUTRITION_USER);
        $this->assign($nutrition, ['nutrition_user']);
        $this->actingAs($nutrition)->get(route('nutrition-tasks.dashboard'))->assertOk()->assertSee('>Micronutrient</a>', false)->assertDontSee('>Support Tickets</a>', false);
        $this->get(route('dashboard'))->assertRedirect(route('nutrition-tasks.dashboard'));
        $this->get(route('admin.tickets.index'))->assertForbidden();
        $this->get(route('dashboard.support-performance.export', 'csv'))->assertForbidden();
    }

    public function test_removing_all_roles_removes_both_workspace_permissions(): void
    {
        $user = $this->user(User::ROLE_ICT_MANAGER);
        $this->assign($user, ['ict_manager', 'task_admin']);
        $this->actingAs($this->user(User::ROLE_ICT_ADMIN))
            ->put(route('admin.users.update', $user), [...$this->payload([]), 'email' => $user->email])->assertSessionHasNoErrors();
        $user = $user->fresh();
        $this->assertFalse($user->hasGlobalRole());
        $this->assertFalse($user->isNutritionUser());
        $this->assertSame([], $user->roleCodes());
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
        $this->get(route('nutrition-tasks.dashboard'))->assertForbidden();
        $this->get(route('admin.tickets.index'))->assertForbidden();
    }

    public function test_task_admin_cannot_assign_global_roles_or_edit_an_account_with_global_access(): void
    {
        $admin = $this->user(User::ROLE_TASK_ADMIN);
        $this->assign($admin, ['task_admin']);
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertDontSee('value="ict_admin"', false);
        $this->post(route('admin.users.store'), $this->payload(['ict_admin', 'task_admin']))->assertSessionHasErrors('roles.0');
        $global = $this->user(User::ROLE_ICT_MANAGER);
        $this->assign($global, ['ict_manager', 'nutrition_user']);
        $this->put(route('admin.users.update', $global), [...$this->payload(['task_admin']), 'email' => $global->email])->assertForbidden();
        $this->get(route('admin.users.index', ['edit' => $global->id]))->assertForbidden();
    }

    public function test_multiple_roles_in_same_category_and_invalid_roles_are_validated(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->actingAs($this->user(User::ROLE_ICT_ADMIN));
        $this->post(route('admin.users.store'), $this->payload(['ict_support_staff', 'developer']))->assertSessionHasNoErrors();
        $user = User::query()->where('email', 'combined@example.com')->sole();
        $this->assertCount(2, $user->assignedRoles);
        $this->assertTrue($user->hasAnyRole(['developer']));
        $this->put(route('admin.users.update', $user), [...$this->payload(['missing']), 'email' => $user->email])->assertSessionHasErrors('roles.0');
        $this->put(route('admin.users.update', $user), [...$this->payload(['developer', 'developer']), 'email' => $user->email])->assertSessionHasErrors('roles.0');
    }

    public function test_recipient_queries_and_permissions_include_non_primary_roles(): void
    {
        $user = $this->user(User::ROLE_ICT_SUPPORT_STAFF);
        $this->assign($user, ['ict_support_staff', 'ict_manager']);
        // Select a different primary value to prove queries use all assignments.
        $user->update(['role' => User::ROLE_ICT_SUPPORT_STAFF]);
        $this->assertContains($user->email, app(TicketRecipientResolver::class)->escalationCcEmails());
        $this->assertTrue(User::query()->withAccessRoles(['ict_manager'])->whereKey($user->id)->exists());
        $this->actingAs($user)->get(route('dashboard', ['tab' => 'manager']))->assertOk();
    }

    public function test_global_manager_with_nutrition_member_role_only_sees_own_nutrition_tasks(): void
    {
        $user = $this->user(User::ROLE_ICT_MANAGER);
        $this->assign($user, ['ict_manager', 'nutrition_user']);
        $other = $this->user(User::ROLE_NUTRITION_USER);
        $this->assign($other, ['nutrition_user']);
        $task = NutritionTask::query()->create([
            'task_number' => 'NT-OTHER', 'entry_date' => today(), 'requestor_name' => 'Other Requestor',
            'requestor_email' => 'other@example.com', 'requestor_phone' => '0700000000',
            'requestor_designation' => 'Analyst', 'requestor_place_of_work' => 'CPHL',
            'request_type' => 'analysis', 'description' => 'Other member task', 'priority' => 'medium', 'status' => 'not_started',
            'assigned_to' => $other->nutritionProfile->id,
        ]);
        $this->actingAs($user)->get(route('nutrition-tasks.dashboard'))->assertOk()->assertDontSee('NT-OTHER');
        $this->get(route('nutrition-tasks.show', $task))->assertForbidden();
    }

    public function test_migration_preserves_nutrition_profile_and_existing_assignment_identifiers(): void
    {
        $migration = require database_path('migrations/2026_10_22_000100_add_multiple_user_roles.php');
        $migration->down();
        $user = User::factory()->create(['role' => User::ROLE_TASK_ADMIN]);
        $profile = $user->nutritionProfile()->create(['designation' => 'Coordinator', 'place_of_work' => 'CPHL']);
        $password = $user->getRawOriginal('password');
        $migration->up();
        $user = $user->fresh();
        $this->assertCount(1, $user->assignedRoles);
        $this->assertTrue($user->hasAnyRole(['task_admin']));
        $this->assertSame('Coordinator', $user->designation);
        $this->assertSame('CPHL', $user->place_of_work);
        $this->assertSame($password, $user->getRawOriginal('password'));
        $this->assertSame($profile->id, $user->nutritionProfile->id);
    }

    public function test_revoking_nutrition_role_keeps_history_but_prevents_new_assignment(): void
    {
        $member = $this->user(User::ROLE_NUTRITION_USER);
        $this->assign($member, ['nutrition_user']);
        $profileId = $member->nutritionProfile->id;
        $this->assign($member, ['ict_support_staff']);
        $admin = $this->user(User::ROLE_TASK_ADMIN);
        $task = NutritionTask::query()->create([
            'task_number' => 'NT-REVOKED', 'entry_date' => today(), 'requestor_name' => 'Requestor',
            'requestor_email' => 'requestor@example.com', 'requestor_phone' => '0700000000',
            'requestor_designation' => 'Analyst', 'requestor_place_of_work' => 'CPHL',
            'request_type' => 'analysis', 'description' => 'New task', 'priority' => 'medium', 'status' => 'not_started',
        ]);
        $this->assertDatabaseHas('nutrition_team_users', ['id' => $profileId, 'user_id' => $member->id]);
        $this->actingAs($admin)->put(route('nutrition-tasks.update', $task), [
            'status' => 'not_started', 'assignee_ids' => [$profileId],
        ])->assertSessionHasErrors('assignee_ids.0');
        $this->actingAs($member->fresh())->get(route('nutrition-tasks.dashboard'))->assertForbidden();
    }

    public function test_invalid_roles_input_returns_to_a_working_form(): void
    {
        $this->actingAs($this->user(User::ROLE_ICT_ADMIN))->from(route('admin.users.index'))
            ->post(route('admin.users.store'), [...$this->payload([]), 'roles' => 'invalid'])
            ->assertRedirect(route('admin.users.index'))->assertSessionHasErrors('roles');
        $this->get(route('admin.users.index'))->assertOk();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true, 'force_password_change' => false, 'password_changed_at' => now()]);
    }

    private function assign(User $user, array $codes): void
    {
        $user->fill(['designation' => 'Analyst', 'place_of_work' => 'CPHL']);
        $user->syncRoles(Role::query()->whereIn('code', $codes)->pluck('id')->all());
    }

    private function payload(array $codes): array
    {
        return ['name' => 'Combined User', 'email' => 'combined@example.com', 'roles' => $codes,
            'designation' => 'Analyst', 'place_of_work' => 'CPHL', 'active' => 1];
    }
}
