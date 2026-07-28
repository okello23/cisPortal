<?php

namespace Tests\Unit;

use App\Models\AlisBackupConfiguration;
use App\Support\AlisBackupScriptGenerator;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AlisBackupScriptGeneratorTest extends TestCase
{
    public function test_generated_script_uses_expected_configuration_and_safe_escaping(): void
    {
        config()->set('alis_backup.facility_offsite_server_ip', '105.27.247.146');
        config()->set('alis_backup.user', 'backupuser');
        config()->set('alis_backup.port', 22);
        config()->set('alis_backup.root', '/backup/dumps');
        config()->set('alis_backup.dumps_root', '/dumps');
        config()->set('alis_backup.ssh_key', '/root/.ssh/cis_backup_deploy');

        $configuration = new AlisBackupConfiguration([
            'backup_directory_name' => 'lemusi_hciii',
            'database_name' => 'alis_db',
            'database_username' => 'alis_user',
            'database_password' => "pa'ss",
        ]);

        $script = (new AlisBackupScriptGenerator())->render($configuration);

        $this->assertStringContainsString("DB_NAME='alis_db'", $script);
        $this->assertStringContainsString("DB_USER='alis_user'", $script);
        $this->assertStringContainsString("DB_PASS='pa'\"'\"'ss'", $script);
        $this->assertStringContainsString("FACILITY='lemusi_hciii'", $script);
        $this->assertStringContainsString("REMOTE_SERVER='105.27.247.146'", $script);
        $this->assertStringContainsString("REMOTE_ROOT='/dumps'", $script);
        $this->assertStringNotContainsString("REMOTE_ROOT='/backup/dumps'", $script);
        $this->assertStringContainsString('BACKUP_DIR="$BACKUP_ROOT"', $script);
        $this->assertStringContainsString('REMOTE_FACILITY_DIR="$REMOTE_ROOT/$FACILITY"', $script);
        $this->assertStringContainsString('SSH_DIR="$HOME/.ssh"', $script);
        $this->assertStringContainsString('KNOWN_HOSTS_FILE="$SSH_DIR/known_hosts"', $script);
        $this->assertStringContainsString('mkdir -p "$SSH_DIR"', $script);
        $this->assertStringContainsString('chmod 700 "$SSH_DIR"', $script);
        $this->assertStringContainsString('touch "$KNOWN_HOSTS_FILE"', $script);
        $this->assertStringContainsString('chmod 600 "$KNOWN_HOSTS_FILE"', $script);
        $this->assertStringContainsString('-F "$REMOTE_SERVER"', $script);
        $this->assertStringContainsString('-f "$KNOWN_HOSTS_FILE"', $script);
        $this->assertStringContainsString('-p 22', $script);
        $this->assertStringContainsString('-H "$REMOTE_SERVER"', $script);
        $this->assertStringContainsString('>> "$KNOWN_HOSTS_FILE" 2>> "$LOG_FILE"', $script);
        $this->assertStringContainsString('log "ERROR: Failed to retrieve the backup server host key."', $script);
        $this->assertStringContainsString('-P 22', $script);
        $this->assertStringContainsString('-o StrictHostKeyChecking=yes', $script);
        $this->assertStringContainsString('-o UserKnownHostsFile="$KNOWN_HOSTS_FILE"', $script);
        $this->assertStringContainsString('cd "$REMOTE_FACILITY_DIR"', $script);
        $this->assertStringContainsString('put "$BACKUP_FILE"', $script);
        $this->assertStringNotContainsString('StrictHostKeyChecking=no', $script);
        $this->assertStringContainsString('SSH_KEY="$HOME/.ssh/alis_backup_ed25519"', $script);
        $this->assertStringNotContainsString('/root/.ssh/cis_backup_deploy', $script);
    }

    public function test_generated_script_passes_bash_syntax_validation(): void
    {
        config()->set('alis_backup.facility_offsite_server_ip', '105.27.247.146');
        config()->set('alis_backup.root', '/backup/dumps');
        config()->set('alis_backup.dumps_root', '/dumps');

        $configuration = new AlisBackupConfiguration([
            'backup_directory_name' => 'syntax_test',
            'database_name' => 'alis_db',
            'database_username' => 'alis_user',
            'database_password' => "a password with 'quotes'",
        ]);

        $process = new Process(['bash', '-n']);
        $process->setInput((new AlisBackupScriptGenerator())->render($configuration));
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }
}
