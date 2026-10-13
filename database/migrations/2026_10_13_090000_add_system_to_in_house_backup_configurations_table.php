<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('in_house_backup_configurations', function (Blueprint $table) {
            $table->foreignId('system_id')->nullable()->unique()->after('id')->constrained('support_systems')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('in_house_backup_configurations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('system_id');
        });
    }
};
