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
                "1720000000.100\t1024\t/dumps/kayunga_rrh",
                "1720001000.900\t2048\t/dumps/kayunga_rrh",
                "1710000000.000\t4096\t/dumps/mbale_rrh",
                "1729999999.000\t8192\t/dumps/not_requested",
            ]), '', 0),
        ]);

        $backups = app(AlisBackupStatusService::class)->latestBackups(
            new Collection(['kayunga_rrh', 'mbale_rrh', 'no_backup'])
        );

        $this->assertNotNull($backups);
        $this->assertSame(1720001000, $backups['kayunga_rrh']['backed_up_at']->timestamp);
        $this->assertSame(2048, $backups['kayunga_rrh']['size_bytes']);
        $this->assertSame(1710000000, $backups['mbale_rrh']['backed_up_at']->timestamp);
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

    public function test_it_lists_only_the_three_most_recent_valid_backup_files(): void
    {
        config()->set('alis_backup.server', '105.27.247.146');
        config()->set('alis_backup.user', 'backupuser');
        config()->set('alis_backup.port', 22);
        config()->set('alis_backup.root', '/dumps');
        config()->set('alis_backup.ssh_key', __FILE__);

        Process::fake([
            '*' => Process::result(implode("\n", [
                "1710000000.000\t1024\talisProduction_2026-07-21.sql.gz",
                "1740000000.000\t5242880\talisProduction_2026-07-24.sql.gz",
                "1730000000.000\t3145728\talisProduction_2026-07-23.sql.gz",
                "1720000000.000\t2097152\talisProduction_2026-07-22.sql.gz",
                "1750000000.000\t999\t../unsafe.sql.gz",
            ]), '', 0),
        ]);

        $backups = app(AlisBackupStatusService::class)->recentBackups('kayunga_rrh');

        $this->assertCount(3, $backups);
        $this->assertSame([
            'alisProduction_2026-07-24.sql.gz',
            'alisProduction_2026-07-23.sql.gz',
            'alisProduction_2026-07-22.sql.gz',
        ], array_column($backups, 'filename'));
        $this->assertSame(5242880, $backups[0]['size_bytes']);
    }

    public function test_it_formats_backup_sizes_for_display(): void
    {
        $this->assertSame('0 B', AlisBackupStatusService::formatBytes(0));
        $this->assertSame('1.00 KB', AlisBackupStatusService::formatBytes(1024));
        $this->assertSame('5.00 MB', AlisBackupStatusService::formatBytes(5242880));
    }
}
