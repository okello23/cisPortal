<?php

namespace Tests\Feature;

use App\Livewire\InHouseBackupsManager;
use App\Models\InHouseBackupConfiguration;
use App\Models\SupportSystem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

class InHouseBackupsManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_backup_directory_as_full_path(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ICT_ADMIN,
            'active' => true,
        ]);

        $system = SupportSystem::query()->create([
            'name' => 'LabSpars',
            'code' => 'labspars',
            'active' => true,
        ]);

        InHouseBackupConfiguration::query()->create([
            'system_id' => $system->id,
            'system_name' => 'LabSpars',
            'server_name' => '10.200.254.228',
            'database_type' => 'mysql',
            'database_name' => 'labspars',
            'database_username' => 'root',
            'database_password' => 'secret',
            'backup_directory_name' => 'labspars',
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(InHouseBackupsManager::class)
            ->assertSee('/backup/inhouse_systems/labspars');
    }

    public function test_downloads_script_with_updated_remote_directory(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ICT_ADMIN,
            'active' => true,
        ]);

        $system = SupportSystem::query()->create([
            'name' => 'LabSpars',
            'code' => 'labspars',
            'active' => true,
        ]);

        $configuration = InHouseBackupConfiguration::query()->create([
            'system_id' => $system->id,
            'system_name' => 'LabSpars',
            'server_name' => '10.200.254.228',
            'database_type' => 'mysql',
            'database_name' => 'labspars',
            'database_username' => 'root',
            'database_password' => 'secret',
            'backup_directory_name' => 'labspars',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('infrastructure.in-house-backups.download-script', $configuration));

        $response->assertOk();
        $this->assertStringContainsString("REMOTE_DIRECTORY='/backup/inhouse_systems/labspars'", $response->streamedContent());
    }

    public function test_recent_backups_queries_updated_directory(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ICT_ADMIN,
            'active' => true,
        ]);

        config()->set('alis_backup.server', '10.200.0.10');
        config()->set('alis_backup.user', 'backupuser');
        config()->set('alis_backup.port', 22);
        config()->set('alis_backup.in_house_root', '/backup');
        config()->set('alis_backup.ssh_key', __FILE__);

        Process::fake([
            '*' => Process::result(
                "1729000000.000\t10485760\tlabspars_2026-10-15_10-00-00.sql.gz\n",
                '',
                0
            ),
        ]);

        $system = SupportSystem::query()->create([
            'name' => 'LabSpars',
            'code' => 'labspars',
            'active' => true,
        ]);

        $configuration = InHouseBackupConfiguration::query()->create([
            'system_id' => $system->id,
            'system_name' => 'LabSpars',
            'server_name' => '10.200.254.228',
            'database_type' => 'mysql',
            'database_name' => 'labspars',
            'database_username' => 'root',
            'database_password' => 'secret',
            'backup_directory_name' => 'labspars',
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(InHouseBackupsManager::class)
            ->call('showBackupHistory', $configuration->id)
            ->assertSee('labspars_2026-10-15_10-00-00.sql.gz');

        Process::assertRan(function ($process) {
            $command = implode(' ', $process->command);

            return str_contains($command, "find '/backup/inhouse_systems/labspars'");
        });
    }
}
