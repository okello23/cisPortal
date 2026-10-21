<?php

namespace Tests\Feature;

use App\Mail\NutritionTaskAssignedMail;
use App\Mail\NutritionTaskDueReminderMail;
use App\Models\NutritionTask;
use App\Models\NutritionTeamUser;
use App\Models\User;
use App\Support\NutritionTaskReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NutritionTaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp']);
    }

    public function test_guest_can_submit_an_external_task_request(): void
    {
        $response = $this->post(route('nutrition-tasks.store'), [
            'entry_date' => today()->toDateString(),
            'requestor_designation' => 'Nutritionist',
            'requestor_place_of_work' => 'CPHL',
            'requestor_name' => 'Jane Requestor',
            'requestor_email' => 'jane@example.com',
            'requestor_phone' => '0700000000',
            'request_type' => 'analysis',
            'description' => 'Analyse the quarterly nutrition dataset.',
            'requested_assignee_name' => 'Data Analyst',
            'requested_assignee_email' => 'analyst@example.com',
            'priority' => 'high',
        ]);

        $response->assertRedirect(route('nutrition-tasks.create'));
        $this->assertDatabaseHas('nutrition_tasks', [
            'requestor_email' => 'jane@example.com',
            'status' => 'not_started',
            'priority' => 'high',
        ]);
    }

    public function test_other_request_requires_a_description_of_the_type(): void
    {
        $response = $this->from(route('nutrition-tasks.create'))->post(route('nutrition-tasks.store'), [
            'entry_date' => today()->toDateString(),
            'requestor_designation' => 'Nutritionist',
            'requestor_place_of_work' => 'CPHL',
            'requestor_name' => 'Jane Requestor',
            'requestor_email' => 'jane@example.com',
            'requestor_phone' => '0700000000',
            'request_type' => 'other',
            'description' => 'A specialised request.',
            'priority' => 'medium',
        ]);

        $response->assertSessionHasErrors('other_request_type');
    }

    public function test_task_admin_can_assign_and_assignee_receives_email(): void
    {
        Mail::fake();
        $admin = $this->user(User::ROLE_TASK_ADMIN, 'admin@example.com');
        $admin->nutritionProfile()->create(['designation' => 'Team Lead', 'place_of_work' => 'CPHL']);
        $assignee = $this->user(User::ROLE_NUTRITION_USER, 'member@example.com');
        $profile = $assignee->nutritionProfile()->create(['designation' => 'Analyst', 'place_of_work' => 'CPHL']);
        $task = NutritionTask::query()->create($this->taskPayload());

        $response = $this->actingAs($admin)->put(route('nutrition-tasks.update', $task), [
            'status' => 'not_started',
            'assignee_ids' => [$profile->id],
            'due_date' => today()->addWeek()->toDateString(),
            'progress_notes' => 'Initial assignment.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('nutrition_tasks', ['id' => $task->id, 'assigned_to' => $profile->id]);
        Mail::assertSent(NutritionTaskAssignedMail::class, fn ($mail) => $mail->hasTo('member@example.com'));
    }

    public function test_admin_can_assign_main_task_to_multiple_people(): void
    {
        Mail::fake();
        $admin = $this->user(User::ROLE_TASK_ADMIN, 'admin@example.com');
        $admin->nutritionProfile()->create(['designation' => 'Lead']);
        $first = $this->user(User::ROLE_NUTRITION_USER, 'first@example.com')->nutritionProfile()->create(['designation' => 'Analyst']);
        $second = $this->user(User::ROLE_NUTRITION_USER, 'second@example.com')->nutritionProfile()->create(['designation' => 'Officer']);
        $task = NutritionTask::query()->create($this->taskPayload());

        $this->actingAs($admin)->put(route('nutrition-tasks.update', $task), [
            'status' => 'not_started', 'assignee_ids' => [$first->id, $second->id],
        ])->assertRedirect();

        $this->assertCount(2, $task->fresh()->assignees);
        Mail::assertSent(NutritionTaskAssignedMail::class, 2);
        $this->actingAs($admin)->get(route('nutrition-tasks.dashboard'))->assertOk()->assertSee($first->user->name)->assertSee($second->user->name);
        $this->actingAs($admin)->get(route('nutrition-tasks.index'))->assertOk()->assertSee($first->user->name)->assertSee($second->user->name);
    }

    public function test_subtask_can_have_independent_assignees_or_inherit_main_assignees(): void
    {
        Mail::fake();
        $admin = $this->user(User::ROLE_TASK_ADMIN, 'admin@example.com');
        $admin->nutritionProfile()->create(['designation' => 'Lead']);
        $main = $this->user(User::ROLE_NUTRITION_USER, 'main@example.com')->nutritionProfile()->create(['designation' => 'Analyst']);
        $specialist = $this->user(User::ROLE_NUTRITION_USER, 'specialist@example.com')->nutritionProfile()->create(['designation' => 'Officer']);
        $task = NutritionTask::query()->create([...$this->taskPayload(), 'assigned_to' => $main->id]);

        $this->actingAs($admin)->post(route('nutrition-subtasks.store', $task), [
            'title' => 'Validate indicators', 'assignee_ids' => [$specialist->id],
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('nutrition-subtasks.store', $task), ['title' => 'Prepare summary'])->assertRedirect();

        $this->assertTrue($task->subtasks()->first()->effectiveAssignees()->contains('id', $specialist->id));
        $this->assertTrue($task->subtasks()->latest('id')->first()->effectiveAssignees()->contains('id', $main->id));
        Mail::assertSent(\App\Mail\NutritionSubtaskAssignedMail::class, fn ($mail) => $mail->hasTo('specialist@example.com'));
        Mail::assertSent(\App\Mail\NutritionSubtaskAssignedMail::class, fn ($mail) => $mail->hasTo('main@example.com'));

        $assignedSubtask = $task->subtasks()->oldest('id')->first();
        $this->actingAs($specialist->user)->get(route('nutrition-tasks.show', $task))->assertOk();
        $this->actingAs($specialist->user)->put(route('nutrition-subtasks.update', [$task, $assignedSubtask]), ['status' => 'completed'])->assertRedirect();
        $this->assertSame('completed', $assignedSubtask->fresh()->status);
        $this->actingAs($specialist->user)->put(route('nutrition-tasks.update', $task), ['status' => 'completed'])->assertForbidden();
    }

    public function test_main_task_cannot_be_completed_until_subtasks_are_complete(): void
    {
        $admin = $this->user(User::ROLE_TASK_ADMIN, 'admin@example.com');
        $admin->nutritionProfile()->create(['designation' => 'Lead']);
        $task = NutritionTask::query()->create($this->taskPayload());
        $task->subtasks()->create(['title' => 'Pending dependency', 'created_by' => $admin->id]);

        $this->actingAs($admin)->put(route('nutrition-tasks.update', $task), ['status' => 'completed'])
            ->assertSessionHasErrors('status');
        $this->assertSame('not_started', $task->fresh()->status);
    }

    public function test_member_sees_only_their_tasks_and_cannot_open_another_members_task(): void
    {
        $member = $this->user(User::ROLE_NUTRITION_USER, 'member@example.com');
        $profile = $member->nutritionProfile()->create(['designation' => 'Analyst', 'place_of_work' => 'CPHL']);
        $other = $this->user(User::ROLE_NUTRITION_USER, 'other@example.com');
        $otherProfile = $other->nutritionProfile()->create(['designation' => 'Officer', 'place_of_work' => 'CPHL']);
        $ownTask = NutritionTask::query()->create([...$this->taskPayload(), 'task_number' => 'NT-OWN-001', 'assigned_to' => $profile->id]);
        $otherTask = NutritionTask::query()->create([...$this->taskPayload(), 'task_number' => 'NT-OTHER-001', 'assigned_to' => $otherProfile->id]);

        $this->actingAs($member)->get(route('nutrition-tasks.index'))->assertOk()->assertSee($ownTask->task_number)->assertDontSee($otherTask->task_number);
        $this->actingAs($member)->get(route('nutrition-tasks.show', $otherTask))->assertForbidden();
    }

    public function test_due_reminder_is_sent_once_per_day_only_for_incomplete_tasks(): void
    {
        Mail::fake();
        $member = $this->user(User::ROLE_NUTRITION_USER, 'member@example.com');
        $profile = $member->nutritionProfile()->create(['designation' => 'Analyst', 'place_of_work' => 'CPHL']);
        $task = NutritionTask::query()->create([
            ...$this->taskPayload(), 'assigned_to' => $profile->id, 'due_date' => today()->addDay(),
        ]);

        $service = app(NutritionTaskReminderService::class);
        $this->assertSame(1, $service->sendDueReminders());
        $this->assertSame(0, $service->sendDueReminders());
        Mail::assertSent(NutritionTaskDueReminderMail::class, 1);
        $this->assertNotNull($task->fresh()->last_reminder_sent_at);
    }

    private function user(string $role, string $email): User
    {
        return User::factory()->create(['role' => $role, 'email' => $email, 'active' => true, 'force_password_change' => false, 'password_changed_at' => now()]);
    }

    private function taskPayload(): array
    {
        return [
            'task_number' => 'NT-TEST-001', 'entry_date' => today(),
            'requestor_designation' => 'Nutritionist', 'requestor_place_of_work' => 'CPHL',
            'requestor_name' => 'Requestor', 'requestor_email' => 'requestor@example.com',
            'requestor_phone' => '0700000000', 'request_type' => 'analysis',
            'description' => 'Analyse a nutrition dataset.', 'priority' => 'medium', 'status' => 'not_started',
        ];
    }
}
