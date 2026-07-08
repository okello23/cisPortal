<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alis_remote_backup_key_update_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('alis_remote_backup_deployments', function (Blueprint $table) {
            $table->id();
            $table->string('deployment_batch_reference')->unique();
            $table->foreignId('deployed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deployed_at')->nullable();
            $table->enum('status', ['success', 'failed', 'partial'])->default('failed');
            $table->unsignedInteger('total_active_keys')->default(0);
            $table->unsignedInteger('total_pending_changes')->default(0);
            $table->string('backup_server')->nullable();
            $table->string('authorized_keys_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('alis_remote_backup_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->text('public_key');
            $table->string('fingerprint')->unique();
            $table->string('key_type', 50);
            $table->string('key_comment')->nullable();
            $table->enum('status', ['active', 'inactive', 'revoked'])->default('active');
            $table->enum('deployment_status', ['pending', 'deployed', 'failed'])->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deployed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deployed_at')->nullable();
            $table->text('last_deployment_error')->nullable();
            $table->timestamps();
            $table->unique(['facility_id', 'status']);
        });

        Schema::create('alis_remote_backup_key_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->enum('action', ['created', 'updated', 'revoked', 'deployed', 'deployment_failed']);
            $table->text('old_public_key')->nullable();
            $table->text('new_public_key')->nullable();
            $table->string('old_fingerprint')->nullable();
            $table->string('new_fingerprint')->nullable();
            $table->foreignId('reason_id')->nullable()->constrained('alis_remote_backup_key_update_reasons')->nullOnDelete();
            $table->text('comments')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->foreignId('deployment_batch_id')->nullable()->constrained('alis_remote_backup_deployments')->nullOnDelete();
            $table->timestamps();
        });

        $timestamp = now();
        $defaults = [
            'Re-installed A-LIS Server',
            'New Facility Server Commissioned',
            'SSH Key Changed',
            'Operating System Re-installed',
            'Server Storage Failure',
            'Server Motherboard Replacement',
            'Server Hardware Replacement',
            'Virtual Machine Recreated',
            'Server Migration',
            'Security Key Rotation',
            'Compromised SSH Key',
            'Disaster Recovery Restoration',
            'Facility Infrastructure Upgrade',
            'Migration to New Linux Version',
            'Incorrect Key Previously Registered',
            'Testing / Commissioning',
            'Other',
        ];

        DB::table('alis_remote_backup_key_update_reasons')->insert(
            collect($defaults)->map(fn (string $name) => [
                'name' => $name,
                'active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('alis_remote_backup_key_history');
        Schema::dropIfExists('alis_remote_backup_keys');
        Schema::dropIfExists('alis_remote_backup_deployments');
        Schema::dropIfExists('alis_remote_backup_key_update_reasons');
    }
};
