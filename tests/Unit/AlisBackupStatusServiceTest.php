<?php

namespace Tests\Unit;

use App\Support\AlisBackupStatusService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class AlisBackupStatusServiceTest extends TestCase
{
    public function test_it_returns_the_latest_backup_for_each_facility_directory(): void
    {
        config()->set('alis_backup.server', '105.27.247.146');
        config()->set('alis_backup.user', 'backupuser');
        config()->set('alis_backup.port', 22);
        config()->set('alis_backup.root', '/dumps');
        config()->set('alis_backup.ssh_key', __FILE__);

        Process::fake([
            '*' => Process::result(implode("\n", [
                "1720000000.100\t/dumps/kayunga_rrh",
                "1720001000.900\t/dumps/kayunga_rrh",
                "1710000000.000\t/dumps/mbale_rrh",
                "1729999999.000\t/dumps/not_requested",
            ]), '', 0),
        ]);

        $backups = app(AlisBackupStatusService::class)->latestBackups(
            new Collection(['kayunga_rrh', 'mbale_rrh', 'no_backup'])
        );

        $this->assertNotNull($backups);
        $this->assertSame(1720001000, $backups['kayunga_rrh']?->timestamp);
        $this->assertSame(1710000000, $backups['mbale_rrh']?->timestamp);
        $this->assertNull($backups['no_backup']);

        Process::assertRan(function ($process) {
            $command = implode(' ', $process->command);

            return str_contains($command, 'find') && str_contains($command, '*.sql.gz');
        });
    }

    public function test_it_reports_unavailable_when_the_remote_check_fails(): void
    {
        config()->set('alis_backup.server', '105.27.247.146');
        config()->set('alis_backup.user', 'backupuser');
        config()->set('alis_backup.port', 22);
        config()->set('alis_backup.root', '/dumps');
        config()->set('alis_backup.ssh_key', __FILE__);

        Process::fake(['*' => Process::result('', 'Connection failed', 1)]);

        $this->assertNull(
            app(AlisBackupStatusService::class)->latestBackups(new Collection(['kayunga_rrh']))
        );
    }
}
