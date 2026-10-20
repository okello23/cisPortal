<?php

namespace Tests\Unit;

use App\Models\InHouseBackupConfiguration;
use App\Support\InHouseBackupProvisioningService;
use App\Support\InHouseBackupScriptGenerator;
use App\Support\InHouseBackupStatusService;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class InHouseBackupServicesTest extends TestCase
{
    public function test_in_house_backup_directory_path_defaults_to_backup_root(): void
    {
        $configuration = new InHouseBackupConfiguration([
            'backup_directory_name' => 'labspars',
        ]);

        $this->assertSame('/backup/inhouse_systems/labspars', $configuration->backup_directory_path);
    }

    public function test_script_generator_uses_backup_inhouse_systems_directory_and_expected_structure(): void
    {
        config()->set('alis_backup.server', '10.200.0.160');
        config()->set('alis_backup.port', 865);
        config()->set('alis_backup.user', 'reverse');
        config()->set('alis_backup.in_house_root', '/dumps');

        $configuration = new InHouseBackupConfiguration([
            'system_name' => 'VL LIMS',
            'database_type' => 'mysql',
            'database_name' => 'vl_lims',
            'database_username' => 'vl_user',
            'database_password' => 'vl8910',
            'backup_directory_name' => 'vllims',
        ]);

        $script = (new InHouseBackupScriptGenerator())->render($configuration);

        $this->assertStringContainsString("DB_NAME='vl_lims'", $script);
        $this->assertStringContainsString("DB_USER='vl_user'", $script);
        $this->assertStringContainsString("DB_PASS='vl8910'", $script);
        $this->assertStringContainsString("REMOTE_SERVER='10.200.0.160'", $script);
        $this->assertStringContainsString("REMOTE_PORT='865'", $script);
        $this->assertStringContainsString("REMOTE_USER='reverse'", $script);
        $this->assertStringContainsString("REMOTE_DIRECTORY='/dumps/inhouse_systems/vllims'", $script);
        $this->assertStringContainsString('SSH_KEY="$SSH_DIR/alis_backup_ed25519"', $script);
        $this->assertStringContainsString('KNOWN_HOSTS_FILE="$SSH_DIR/known_hosts"', $script);
        $this->assertStringContainsString('LOG_FILE="$BACKUP_ROOT/backup.log"', $script);
        $this->assertStringContainsString('log() {', $script);
        $this->assertStringContainsString('ssh-keygen -F "$REMOTE_SERVER" -f "$KNOWN_HOSTS_FILE"', $script);
        $this->assertStringContainsString('ssh-keyscan -p "$REMOTE_PORT" -H "$REMOTE_SERVER"', $script);
        $this->assertStringContainsString('sftp -i "$SSH_KEY" -P "$REMOTE_PORT"', $script);
        $this->assertStringContainsString('DELETED_COUNT=$(find "$BACKUP_DIR" -type f -name \'*.sql.gz\' -mtime +3 | wc -l)', $script);
    }

    public function test_generated_script_passes_bash_syntax_validation(): void
    {
        config()->set('alis_backup.server', '10.200.0.160');
        config()->set('alis_backup.port', 865);
        config()->set('alis_backup.user', 'reverse');
        config()->set('alis_backup.in_house_root', '/backup');

        $configuration = new InHouseBackupConfiguration([
            'system_name' => 'LabSpars',
            'database_type' => 'mysql',
            'database_name' => 'labspars',
            'database_username' => 'root',
            'database_password' => "secret'quote",
            'backup_directory_name' => 'labspars',
        ]);

        $script = (new InHouseBackupScriptGenerator())->render($configuration);

        $process = new SymfonyProcess(['bash', '-n']);
        $process->setInput($script);
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Bash syntax error: '.$process->getErrorOutput());
    }

    public function test_status_service_queries_backup_inhouse_systems_directory(): void
    {
        config()->set('alis_backup.server', '10.200.0.10');
        config()->set('alis_backup.user', 'backupuser');
        config()->set('alis_backup.port', 22);
        config()->set('alis_backup.in_house_root', '/backup');
        config()->set('alis_backup.ssh_key', __FILE__);

        Process::fake([
            '*' => Process::result(implode("\n", [
                "1729000000.000\t10485760\tlabspars_2026-10-15_10-00-00.sql.gz",
                "1728900000.000\t10485700\tlabspars_2026-10-14_10-00-00.sql.gz",
            ]), '', 0),
        ]);

        $statusService = new InHouseBackupStatusService();
        $this->assertSame('/backup/inhouse_systems/labspars', $statusService->directoryPath('labspars'));

        $backups = $statusService->recentBackups('labspars');

        $this->assertNotNull($backups);
        $this->assertCount(2, $backups);
        $this->assertSame('labspars_2026-10-15_10-00-00.sql.gz', $backups[0]['filename']);
        $this->assertSame(10485760, $backups[0]['size_bytes']);

        Process::assertRan(function ($process) {
            $command = implode(' ', $process->command);

            return str_contains($command, "find '/backup/inhouse_systems/labspars'");
        });
    }

    public function test_provisioning_service_provisions_in_backup_inhouse_systems(): void
    {
        config()->set('alis_backup.server', '10.200.0.10');
        config()->set('alis_backup.user', 'backupuser');
        config()->set('alis_backup.port', 22);
        config()->set('alis_backup.in_house_root', '/backup');
        config()->set('alis_backup.ssh_key', __FILE__);

        Process::fake([
            '*' => Process::result('', '', 0),
        ]);

        $configuration = new InHouseBackupConfiguration([
            'id' => 999,
            'system_name' => 'LabSpars',
            'backup_directory_name' => 'labspars',
            'status' => 'pending_provisioning',
        ]);

        Process::assertNotRan('*');
    }
}
