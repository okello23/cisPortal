<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('in_house_backup_configurations', function (Blueprint $table) {
            $table->timestamp('provisioned_at')->nullable()->after('status');
            $table->text('last_provisioning_error')->nullable()->after('provisioned_at');
        });
    }

    public function down(): void
    {
        Schema::table('in_house_backup_configurations', function (Blueprint $table) {
            $table->dropColumn(['provisioned_at', 'last_provisioning_error']);
        });
    }
};
