<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_team_users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('designation')->nullable();
            $table->string('place_of_work')->nullable();
            $table->timestamps();
        });

        Schema::create('nutrition_tasks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('task_number', 30)->unique();
            $table->date('entry_date');
            $table->string('requestor_designation');
            $table->string('requestor_place_of_work');
            $table->string('requestor_name');
            $table->string('requestor_email');
            $table->string('requestor_phone', 50);
            $table->string('request_type', 30);
            $table->string('other_request_type')->nullable();
            $table->text('description');
            $table->string('requested_assignee_email')->nullable();
            $table->string('requested_assignee_name')->nullable();
            $table->string('priority', 20);
            $table->string('status', 20)->default('not_started');
            $table->foreignId('assigned_to')->nullable()->constrained('nutrition_team_users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->text('progress_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('nutrition_task_histories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('nutrition_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->foreignId('old_assignee_id')->nullable()->constrained('nutrition_team_users')->nullOnDelete();
            $table->foreignId('new_assignee_id')->nullable()->constrained('nutrition_team_users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_task_histories');
        Schema::dropIfExists('nutrition_tasks');
        Schema::dropIfExists('nutrition_team_users');
    }
};
