<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->timestamp('last_worked_at')->nullable()->after('assigned_at');
            $table->timestamp('last_reminder_sent_at')->nullable()->after('last_worked_at');
        });

        DB::table('ticket_statuses')->updateOrInsert(
            ['code' => 'escalated'],
            [
                'name' => 'Escalated',
                'color' => 'warning',
                'active' => true,
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::statement("
            UPDATE ticket_statuses
            SET sort_order = CASE code
                WHEN 'new' THEN 1
                WHEN 'assigned' THEN 2
                WHEN 'in_progress' THEN 3
                WHEN 'pending_user' THEN 4
                WHEN 'escalated' THEN 5
                WHEN 'resolved' THEN 6
                WHEN 'closed' THEN 7
                WHEN 'reopened' THEN 8
                ELSE sort_order
            END
        ");

        DB::statement("
            UPDATE tickets
            SET assigned_at = COALESCE(updated_at, created_at),
                last_worked_at = COALESCE(updated_at, created_at)
            WHERE assigned_to IS NOT NULL
              AND assigned_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::table('ticket_statuses')->where('code', 'escalated')->delete();

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['assigned_at', 'last_worked_at', 'last_reminder_sent_at']);
        });
    }
};
