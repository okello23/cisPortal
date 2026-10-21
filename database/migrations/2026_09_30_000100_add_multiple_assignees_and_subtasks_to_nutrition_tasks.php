<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nutrition_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nutrition_team_user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['nutrition_task_id', 'nutrition_team_user_id'], 'nutrition_task_assignee_unique');
        });

        DB::table('nutrition_tasks')->whereNotNull('assigned_to')->orderBy('id')->each(function ($task) {
            DB::table('nutrition_task_assignees')->insertOrIgnore([
                'nutrition_task_id' => $task->id,
                'nutrition_team_user_id' => $task->assigned_to,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::create('nutrition_subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nutrition_task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('not_started');
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_date']);
        });

        Schema::create('nutrition_subtask_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nutrition_subtask_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nutrition_team_user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['nutrition_subtask_id', 'nutrition_team_user_id'], 'nutrition_subtask_assignee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_subtask_assignees');
        Schema::dropIfExists('nutrition_subtasks');
        Schema::dropIfExists('nutrition_task_assignees');
    }
};
