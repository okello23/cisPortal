<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_feedback', function (Blueprint $table) {
            $table->string('incident_report_path')->nullable()->after('comments');
            $table->timestamp('incident_report_generated_at')->nullable()->after('incident_report_path');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_feedback', function (Blueprint $table) {
            $table->dropColumn(['incident_report_path', 'incident_report_generated_at']);
        });
    }
};
