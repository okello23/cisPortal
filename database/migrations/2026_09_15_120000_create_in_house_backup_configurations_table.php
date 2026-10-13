<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('in_house_backup_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('system_name')->unique();
            $table->string('server_name')->nullable();
            $table->string('database_type');
            $table->string('database_name');
            $table->string('database_username');
            $table->text('database_password');
            $table->string('backup_directory_name')->unique();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_house_backup_configurations');
    }
};
