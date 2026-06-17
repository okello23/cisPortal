<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('code');
            $table->unsignedBigInteger('external_id')->nullable()->after('source_system');
            $table->string('facility_type')->nullable()->after('external_id');
            $table->unsignedBigInteger('moh_id')->nullable()->after('facility_type');
            $table->string('nhlds_uuid')->nullable()->after('moh_id');
            $table->string('district_name')->nullable()->after('nhlds_uuid');
            $table->string('subcounty_name')->nullable()->after('district_name');
            $table->string('phone')->nullable()->after('subcounty_name');
            $table->string('email')->nullable()->after('phone');
            $table->json('source_payload')->nullable()->after('email');

            $table->unique(['source_system', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropUnique(['source_system', 'external_id']);
            $table->dropColumn([
                'source_system',
                'external_id',
                'facility_type',
                'moh_id',
                'nhlds_uuid',
                'district_name',
                'subcounty_name',
                'phone',
                'email',
                'source_payload',
            ]);
        });
    }
};
