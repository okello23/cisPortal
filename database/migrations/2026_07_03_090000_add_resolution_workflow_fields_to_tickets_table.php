<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->text('work_done')->nullable()->after('resolution_summary');
            $table->text('recommendations')->nullable()->after('work_done');
            $table->text('challenges_faced')->nullable()->after('recommendations');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['work_done', 'recommendations', 'challenges_faced']);
        });
    }
};
