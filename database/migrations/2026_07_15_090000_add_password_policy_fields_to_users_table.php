<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('force_password_change')->default(false)->after('active');
            $table->timestamp('password_changed_at')->nullable()->after('password');
        });

        DB::table('users')->update([
            'force_password_change' => false,
            'password_changed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['force_password_change', 'password_changed_at']);
        });
    }
};
