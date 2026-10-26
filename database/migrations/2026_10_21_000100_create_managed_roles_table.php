<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 100)->unique();
            $table->string('category', 30)->index();
            $table->string('access_role', 50);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('managed_role_id')->nullable()->constrained('roles')->restrictOnDelete();
        });

        foreach ([
            ['ict_admin', 'ICT Admin', 'global'],
            ['ict_manager', 'ICT Manager', 'global'],
            ['ict_supervisor', 'Software Development Supervisor', 'global'],
            ['ict_support_staff', 'ICT Support Staff', 'global'],
            ['developer', 'Developer', 'global'],
            ['task_admin', 'Task Admin', 'micronutrient'],
            ['nutrition_user', 'Nutrition Team Member', 'micronutrient'],
        ] as [$code, $name, $category]) {
            $id = DB::table('roles')->insertGetId([
                'code' => $code, 'name' => $name, 'category' => $category, 'access_role' => $code,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('users')->where('role', $code)->update(['managed_role_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('managed_role_id'));
        Schema::dropIfExists('roles');
    }
};
