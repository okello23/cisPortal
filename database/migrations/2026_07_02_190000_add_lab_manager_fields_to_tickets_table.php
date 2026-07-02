<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('lab_manager_name')->nullable()->after('email');
            $table->string('lab_manager_email')->nullable()->after('lab_manager_name');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['lab_manager_name', 'lab_manager_email']);
        });
    }
};
