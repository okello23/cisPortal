<?php

use App\Models\AlisBackupConfiguration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alis_backup_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->unique()->constrained('facilities')->cascadeOnDelete();
            $table->string('backup_directory_name')->unique();
            $table->string('database_name');
            $table->string('database_username');
            $table->text('database_password');
            $table->string('status')->default(AlisBackupConfiguration::STATUS_PENDING_PROVISIONING);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('provisioned_at')->nullable();
            $table->text('last_provisioning_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alis_backup_configurations');
    }
};
