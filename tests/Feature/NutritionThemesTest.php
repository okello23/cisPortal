<?php

namespace Tests\Feature;

use App\Models\NutritionTask;
use App\Models\NutritionTheme;
use App\Models\User;
use App\Support\NutritionThemeReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

class NutritionThemesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['mail.default' => 'smtp']);
    }

    public function test_only_planned_activities_are_created_in_source_order_with_the_corrected_code(): void
    {
        $themes = NutritionTheme::query()->orderBy('sort_order')->get();
        $this->assertCount(30, $themes);
        $this->assertSame(['1', '2', '3', '3a', '3b', '3c', '3d', '4', '4a', '4b', '4c', '4d', '5', '6', '7', '7a', '8', '9', '9a', '9b', '10', '10a', '10b', '11', '12', '13', '14', '15', '16', '17'], $themes->pluck('code')->all());
        $this->assertSame('UNSCT approval of the ANC protocol', $themes[11]->name);
        $this->assertSame(['id', 'code', 'name', 'sort_order', 'created_at', 'updated_at'], Schema::getColumnListing('nutrition_themes'));
        $this->assertDatabaseCount('nutrition_tasks', 0);
    }

    public function test_project_brief_and_manage_themes_are_only_in_the_nutrition_workspace(): void
    {
        $this->actingAs($this->user(User::ROLE_TASK_ADMIN))->get(route('nutrition-tasks.dashboard'))
            ->assertOk()->assertSeeInOrder(['MicroNuT-IPHL Project', 'Objectives', 'Justification:', 'Total Tasks'])
            ->assertSee('Manage Themes')->assertDontSee('Support Team Performance');
        $this->get(route('nutrition-tasks.dashboard', ['tab' => 'themes']))->assertOk()->assertSee('Download Excel')->assertSee('Download Image')
            ->assertSeeInOrder(['<th>Not Started</th>', '<th>Started</th>', '<th>In Progress</th>', '<th>Completed</th>', '<th>Overdue</th>', '<th>Assigned Members</th>'], false);
        $this->actingAs($this->user(User::ROLE_ICT_ADMIN))->get(route('dashboard'))->assertOk()
            ->assertSee('Support Team Performance')->assertDontSee('Manage Themes')->assertDontSee('MicroNuT-IPHL Project');
    }

    public function test_admin_can_add_and_edit_a_theme_and_existing_task_links_are_preserved(): void
    {
        $admin = $this->user(User::ROLE_TASK_ADMIN);
        $this->actingAs($admin)->post(route('nutrition-themes.store'), ['code' => '18', 'name' => 'New activity', 'sort_order' => 31])
            ->assertRedirect(route('nutrition-tasks.dashboard', ['tab' => 'themes']))->assertSessionHasNoErrors();
        $theme = NutritionTheme::query()->where('code', '18')->sole();
        $task = $this->task(['theme_id' => $theme->id]);
        $this->put(route('nutrition-themes.update', $theme), ['code' => '18a', 'name' => 'Updated activity', 'sort_order' => 31])->assertSessionHasNoErrors();
        $this->assertSame($theme->id, $task->fresh()->theme_id);
        $this->get(route('nutrition-tasks.show', $task))->assertOk()->assertSee('18a')->assertSee('Updated activity');
        $this->assertDatabaseHas('audit_logs', ['action' => 'nutrition_theme.updated']);
        $this->post(route('nutrition-themes.store'), ['code' => '18a', 'name' => 'Duplicate', 'sort_order' => 32])->assertSessionHasErrors('code');
    }

    public function test_main_tasks_and_subtasks_each_accept_one_theme_from_the_same_list(): void
    {
        $this->actingAs($this->user(User::ROLE_TASK_ADMIN));
        $mainTheme = NutritionTheme::query()->where('code', '3')->sole();
        $subTheme = NutritionTheme::query()->where('code', '3a')->sole();
        $task = $this->task();
        $this->put(route('nutrition-tasks.update', $task), ['status' => 'started', 'theme_id' => $mainTheme->id])->assertSessionHasNoErrors();
        $this->post(route('nutrition-subtasks.store', $task), ['title' => 'Draft manuscript', 'theme_id' => $subTheme->id])->assertSessionHasNoErrors();
        $subtask = $task->subtasks()->sole();
        $this->assertSame($mainTheme->id, $task->fresh()->theme_id);
        $this->assertSame($subTheme->id, $subtask->theme_id);
        $this->get(route('nutrition-tasks.show', $task))->assertOk()->assertSee('name="theme_id"', false)->assertDontSee('name="theme_id[]"', false);
        $this->put(route('nutrition-subtasks.update', [$task, $subtask]), ['title' => 'Draft manuscript', 'status' => 'started', 'theme_id' => $mainTheme->id])->assertSessionHasNoErrors();
        $this->assertSame($mainTheme->id, $subtask->fresh()->theme_id);
        $this->put(route('nutrition-tasks.update', $task), ['status' => 'started', 'theme_id' => [$mainTheme->id, $subTheme->id]])->assertSessionHasErrors('theme_id');
        $this->put(route('nutrition-tasks.update', $task), ['status' => 'started', 'theme_id' => 999999])->assertSessionHasErrors('theme_id');
        $this->put(route('nutrition-tasks.update', $task), ['status' => 'started', 'theme_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($task->fresh()->theme_id);
    }

    public function test_theme_status_tracks_all_linked_work_and_members_are_unique_including_inherited_assignees(): void
    {
        $theme = NutritionTheme::query()->firstOrFail();
        $profile = $this->user(User::ROLE_NUTRITION_USER)->nutritionProfile()->create(['designation' => 'Analyst']);
        $task = $this->task(['theme_id' => $theme->id, 'assigned_to' => $profile->id, 'due_date' => today()->subDay()]);
        $subtask = $task->subtasks()->create(['theme_id' => $theme->id, 'title' => 'Child', 'status' => 'completed', 'completed_at' => now()]);
        $report = app(NutritionThemeReport::class);
        $row = $report->rows($theme->id)->sole();
        $this->assertSame('in_progress', $row['status']);
        $this->assertSame(1, $row['not_started']);
        $this->assertSame(1, $row['completed']);
        $this->assertSame(1, $row['overdue']);
        $this->assertSame(50.0, $row['completion_rate']);
        $this->assertSame([$profile->user->name], $row['members']);
        $task->update(['status' => 'completed', 'completed_at' => now()]);
        $row = $report->rows($theme->id)->sole();
        $this->assertSame('completed', $row['status']);
        $this->assertSame(0, $row['overdue']);
        $this->assertNotNull($row['completed_at']);
        $subtask->update(['status' => 'started', 'completed_at' => null]);
        $this->assertSame('in_progress', $report->rows($theme->id)->sole()['status']);
        $emptyTheme = NutritionTheme::query()->whereKeyNot($theme->id)->firstOrFail();
        $this->assertSame('not_started', $report->rows($emptyTheme->id)->sole()['status']);
    }

    public function test_reopening_or_adding_an_incomplete_subtask_reopens_the_main_task(): void
    {
        $theme = NutritionTheme::query()->firstOrFail();
        $this->actingAs($this->user(User::ROLE_TASK_ADMIN));
        $task = $this->task(['theme_id' => $theme->id, 'status' => 'completed', 'completed_at' => now()]);
        $subtask = $task->subtasks()->create(['theme_id' => $theme->id, 'title' => 'Child', 'status' => 'completed', 'completed_at' => now()]);
        $this->put(route('nutrition-subtasks.update', [$task, $subtask]), ['title' => 'Child', 'status' => 'in_progress', 'theme_id' => $theme->id])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
        $this->assertSame('in_progress', app(NutritionThemeReport::class)->rows($theme->id)->sole()['status']);
        $this->put(route('nutrition-tasks.update', $task), ['status' => 'completed'])->assertSessionHasErrors('status');
        $subtask->update(['status' => 'completed', 'completed_at' => now()]);
        $task->update(['status' => 'completed', 'completed_at' => now()]);
        $this->post(route('nutrition-subtasks.store', $task), ['title' => 'New pending child'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_creation_and_completion_dates_are_filtered_independently_and_timeliness_uses_due_date(): void
    {
        $theme = NutritionTheme::query()->firstOrFail();
        $task = $this->task(['theme_id' => $theme->id, 'status' => 'completed', 'due_date' => '2026-10-30']);
        $task->forceFill(['created_at' => '2026-09-01 09:00:00', 'completed_at' => '2026-10-30 23:59:00'])->save();
        $report = app(NutritionThemeReport::class);
        $result = $report->manager($report->rows($theme->id), Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        $this->assertSame(0, $result['cards']['Work Created in Period']);
        $this->assertSame(1, $result['cards']['Work Completed in Period']);
        $timeliness = collect($result['charts'])->firstWhere('id', 'theme-timeliness');
        $this->assertSame([1], $timeliness['datasets'][0]['data']);
        $this->assertSame([0], $timeliness['datasets'][1]['data']);
        $trend = collect($result['charts'])->firstWhere('id', 'execution-trend');
        $this->assertSame(1, array_sum($trend['datasets'][1]['data']));
        $this->assertSame(1, array_sum($trend['datasets'][2]['data']));
    }

    public function test_manager_filters_and_exportable_chart_data_work_with_no_activity(): void
    {
        $this->actingAs($this->user(User::ROLE_TASK_ADMIN));
        $theme = NutritionTheme::query()->where('code', '4d')->sole();
        $this->get(route('nutrition-tasks.dashboard', ['tab' => 'manager', 'theme_id' => $theme->id, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']))
            ->assertOk()->assertSee('Theme Manager Dashboard')->assertSee('Download PNG')->assertSee('View chart data')
            ->assertSee('theme-chart-data')->assertSee('theme-duration');
        $this->get(route('nutrition-tasks.dashboard', ['tab' => 'manager', 'start_date' => '2026-11-01', 'end_date' => '2026-10-01']))->assertSessionHasErrors('end_date');
        $this->get(route('nutrition-tasks.dashboard', ['tab' => 'manager', 'theme_id' => 999999]))->assertSessionHasErrors('theme_id');
        $this->get(route('nutrition-tasks.dashboard', ['tab' => 'performance']))->assertOk()->assertSee('Manage Themes');
    }

    public function test_excel_export_is_a_real_xlsx_with_text_cells_and_no_spreadsheet_metadata(): void
    {
        NutritionTheme::query()->create(['code' => '18', 'name' => '=1+1', 'sort_order' => 31]);
        $response = $this->actingAs($this->user(User::ROLE_TASK_ADMIN))->get(route('nutrition-themes.export'))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'test-themes-');
        try {
            file_put_contents($path, $response->getContent());
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertNotFalse(simplexml_load_string($sheet));
            $this->assertStringContainsString('4d', $sheet);
            $this->assertStringContainsString('Assigned Members', $sheet);
            $this->assertStringContainsString('=1+1', $sheet);
            $this->assertStringNotContainsString('<f>', $sheet);
            $this->assertStringNotContainsString('Responsibility', $sheet);
            $zip->close();
        } finally {
            unlink($path);
        }
    }

    public function test_members_and_global_only_users_cannot_manage_or_export_themes(): void
    {
        $theme = NutritionTheme::query()->firstOrFail();
        foreach ([User::ROLE_NUTRITION_USER, User::ROLE_ICT_ADMIN, User::ROLE_ICT_MANAGER] as $role) {
            $this->actingAs($this->user($role));
            $this->post(route('nutrition-themes.store'), ['code' => '18', 'name' => 'Unauthorized', 'sort_order' => 31])->assertForbidden();
            $this->put(route('nutrition-themes.update', $theme), ['code' => '1', 'name' => 'Unauthorized', 'sort_order' => 1])->assertForbidden();
            $this->get(route('nutrition-themes.export'))->assertForbidden();
        }
        $member = $this->user(User::ROLE_NUTRITION_USER);
        $profile = $member->nutritionProfile()->create(['designation' => 'Analyst']);
        $task = $this->task(['assigned_to' => $profile->id]);
        $this->actingAs($member)->put(route('nutrition-tasks.update', $task), ['status' => 'started', 'theme_id' => $theme->id])->assertSessionHasNoErrors();
        $this->assertNull($task->fresh()->theme_id);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true, 'force_password_change' => false, 'password_changed_at' => now()]);
    }

    private function task(array $attributes = []): NutritionTask
    {
        return NutritionTask::query()->create([
            'task_number' => 'NT-'.fake()->unique()->numerify('######'), 'entry_date' => today(),
            'requestor_name' => 'Requestor', 'requestor_email' => 'requestor@example.com', 'requestor_phone' => '0700000000',
            'requestor_designation' => 'Researcher', 'requestor_place_of_work' => 'CPHL',
            'request_type' => 'analysis', 'description' => 'Theme execution task', 'priority' => 'medium', 'status' => 'not_started',
            ...$attributes,
        ]);
    }
}
