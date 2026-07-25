<?php

namespace Tests\Unit;

use App\Models\AlisBackupConfiguration;
use App\Support\AlisBackupScriptGenerator;
use Tests\TestCase;

class AlisBackupScriptGeneratorTest extends TestCase
{
    public function test_generated_script_uses_expected_configuration_and_safe_escaping(): void
    {
        config()->set('alis_backup.facility_offsite_server_ip', '105.27.247.146');
        config()->set('alis_backup.user', 'backupuser');
        config()->set('alis_backup.port', 22);
        config()->set('alis_backup.root', '/dumps');
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
        $this->assertStringContainsString('BACKUP_DIR="$BACKUP_ROOT"', $script);
        $this->assertStringContainsString('REMOTE_FACILITY_DIR="$REMOTE_ROOT/$FACILITY"', $script);
        $this->assertStringContainsString('put "${BACKUP_FILE}" "${REMOTE_FACILITY_DIR}/"', $script);
        $this->assertStringNotContainsString('cd $REMOTE_ROOT/$FACILITY', $script);
        $this->assertStringContainsString('SSH_KEY="$HOME/.ssh/alis_backup_ed25519"', $script);
        $this->assertStringNotContainsString('/root/.ssh/cis_backup_deploy', $script);
    }
}
