<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('designation')->nullable();
            $table->string('place_of_work')->nullable();
            $table->boolean('roles_initialized')->default(false);
        });

        DB::table('users')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['roles_initialized' => true]);
            $roleId = $user->managed_role_id ?? DB::table('roles')->where('code', $user->role)->value('id');
            if ($roleId) {
                DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $roleId]);
            }
            $profile = DB::table('nutrition_team_users')->where('user_id', $user->id)->first();
            if ($profile) {
                DB::table('users')->where('id', $user->id)->update([
                    'designation' => $profile->designation, 'place_of_work' => $profile->place_of_work,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Preserve a usable single role for older application versions.
        DB::table('users')->orderBy('id')->each(function ($user) {
            $role = DB::table('roles')->join('role_user', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $user->id)->orderBy('roles.id')->select('roles.*')->first();
            DB::table('users')->where('id', $user->id)->update([
                'managed_role_id' => $role?->id, 'role' => $role?->access_role ?? 'none',
            ]);
        });
        Schema::dropIfExists('role_user');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['designation', 'place_of_work', 'roles_initialized']));
    }
};
