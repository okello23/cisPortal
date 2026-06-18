<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['status_id', 'expected_resolution_date'], 'tickets_status_expected_idx');
            $table->index(['assigned_to', 'status_id'], 'tickets_assigned_status_idx');
            $table->index(['system_id', 'status_id'], 'tickets_system_status_idx');
            $table->index('created_at', 'tickets_created_at_idx');
            $table->index('resolved_at', 'tickets_resolved_at_idx');
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->index(['region_id', 'district_name'], 'facilities_region_district_idx');
            $table->index(['active', 'sort_order'], 'facilities_active_sort_idx');
            $table->index('district_name', 'facilities_district_name_idx');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->index(['active', 'sort_order'], 'regions_active_sort_idx');
        });

        Schema::table('support_systems', function (Blueprint $table) {
            $table->index(['active', 'sort_order'], 'support_systems_active_sort_idx');
        });

        Schema::table('support_modules', function (Blueprint $table) {
            $table->index(['system_id', 'active', 'sort_order'], 'support_modules_system_active_sort_idx');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->index(['active', 'sort_order'], 'departments_active_sort_idx');
        });

        Schema::table('issue_types', function (Blueprint $table) {
            $table->index(['active', 'sort_order'], 'issue_types_active_sort_idx');
        });

        Schema::table('priority_levels', function (Blueprint $table) {
            $table->index(['active', 'sort_order'], 'priority_levels_active_sort_idx');
        });

        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->index(['active', 'sort_order'], 'ticket_statuses_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->dropIndex('ticket_statuses_active_sort_idx');
        });

        Schema::table('priority_levels', function (Blueprint $table) {
            $table->dropIndex('priority_levels_active_sort_idx');
        });

        Schema::table('issue_types', function (Blueprint $table) {
            $table->dropIndex('issue_types_active_sort_idx');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex('departments_active_sort_idx');
        });

        Schema::table('support_modules', function (Blueprint $table) {
            $table->dropIndex('support_modules_system_active_sort_idx');
        });

        Schema::table('support_systems', function (Blueprint $table) {
            $table->dropIndex('support_systems_active_sort_idx');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->dropIndex('regions_active_sort_idx');
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->dropIndex('facilities_region_district_idx');
            $table->dropIndex('facilities_active_sort_idx');
            $table->dropIndex('facilities_district_name_idx');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_status_expected_idx');
            $table->dropIndex('tickets_assigned_status_idx');
            $table->dropIndex('tickets_system_status_idx');
            $table->dropIndex('tickets_created_at_idx');
            $table->dropIndex('tickets_resolved_at_idx');
        });
    }
};
