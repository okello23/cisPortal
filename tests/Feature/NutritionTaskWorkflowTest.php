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
            'assigned_to' => $profile->id,
            'due_date' => today()->addWeek()->toDateString(),
            'progress_notes' => 'Initial assignment.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('nutrition_tasks', ['id' => $task->id, 'assigned_to' => $profile->id]);
        Mail::assertSent(NutritionTaskAssignedMail::class, fn ($mail) => $mail->hasTo('member@example.com'));
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
