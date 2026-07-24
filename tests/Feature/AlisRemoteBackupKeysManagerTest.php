<?php

namespace Tests\Feature;

use App\Livewire\AlisRemoteBackupKeysManager;
use App\Models\AlisBackupConfiguration;
use App\Models\AlisRemoteBackupKey;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\Region;
use App\Models\User;
use App\Support\AlisBackupConfigurationService;
use App\Support\AlisBackupStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AlisRemoteBackupKeysManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_support_user_can_register_a_backup_configuration(): void
    {
        $user = $this->actingAsSupportUser();
        $facility = $this->makeFacility('Arua RRH', 'UG000002-ARUA-RRH');
        $this->configureProvisioning();

        Livewire::actingAs($user)
            ->test(AlisRemoteBackupKeysManager::class)
            ->set('facilityId', (string) $facility->id)
            ->set('databaseName', 'alis_arua')
            ->set('databaseUsername', 'alis_user')
            ->set('databasePassword', 'super-secret')
            ->set('publicKey', $this->makeEd25519PublicKey('alis-offsite-backup'))
            ->call('saveConfiguration')
            ->assertSet('flashMessage', 'Backup configuration saved successfully. The facility backup directory has been created on the central backup server. Download the generated backup script and copy it to the A-LIS server.');

        $configuration = AlisBackupConfiguration::query()->where('facility_id', $facility->id)->firstOrFail();

        $this->assertSame('alis_arua', $configuration->database_name);
        $this->assertSame('alis_user', $configuration->database_username);
        $this->assertSame('provisioned', $configuration->status);
        $this->assertSame('super-secret', $configuration->database_password);
        $this->assertDatabaseMissing('alis_backup_configurations', [
            'facility_id' => $facility->id,
            'database_password' => 'super-secret',
        ]);
    }

    public function test_blank_password_preserves_existing_saved_password(): void
    {
        $user = $this->actingAsSupportUser();
        $facility = $this->makeFacility('Mbale RRH', 'UG000010-MBALE-RRH');
        $this->configureProvisioning();

        $service = app(AlisBackupConfigurationService::class);
        $service->saveConfiguration(
            $facility,
            'alis_mbale',
            'mbale_user',
            'initial-pass',
            $this->makeEd25519PublicKey('mbale-key'),
            $user,
        );

        Livewire::actingAs($user)
            ->test(AlisRemoteBackupKeysManager::class)
            ->set('facilityId', (string) $facility->id)
            ->set('databaseName', 'alis_mbale_v2')
            ->set('databaseUsername', 'mbale_user_v2')
            ->set('databasePassword', '')
            ->set('publicKey', $this->latestPublicKeyFor($facility))
            ->call('saveConfiguration')
            ->assertHasNoErrors();

        $configuration = AlisBackupConfiguration::query()->where('facility_id', $facility->id)->firstOrFail();

        $this->assertSame('initial-pass', $configuration->database_password);
        $this->assertSame('alis_mbale_v2', $configuration->database_name);
        $this->assertSame('mbale_user_v2', $configuration->database_username);
    }

    public function test_duplicate_generated_backup_directory_is_rejected(): void
    {
        $user = $this->actingAsSupportUser();
        $first = $this->makeFacility('Lemusi H/C III', 'UG000020-LEMUSI-1');
        $second = $this->makeFacility('Lemusi HC III', 'UG000021-LEMUSI-2');
        $this->configureProvisioning();

        app(AlisBackupConfigurationService::class)->saveConfiguration(
            $first,
            'alis_lemusi',
            'lemusi_user',
            'password-one',
            $this->makeEd25519PublicKey('lemusi-one'),
            $user,
        );

        Livewire::actingAs($user)
            ->test(AlisRemoteBackupKeysManager::class)
            ->set('facilityId', (string) $second->id)
            ->set('databaseName', 'alis_lemusi_two')
            ->set('databaseUsername', 'lemusi_two')
            ->set('databasePassword', 'password-two')
            ->set('publicKey', $this->makeEd25519PublicKey('lemusi-two'))
            ->call('saveConfiguration')
            ->assertHasErrors(['facilityId']);
    }

    public function test_duplicate_ssh_fingerprint_is_rejected_even_when_comments_differ(): void
    {
        $user = $this->actingAsSupportUser();
        $first = $this->makeFacility('Gulu RRH', 'UG000030-GULU-RRH');
        $second = $this->makeFacility('Lira RRH', 'UG000031-LIRA-RRH');
        $this->configureProvisioning();
        $sharedBytes = random_bytes(32);

        app(AlisBackupConfigurationService::class)->saveConfiguration(
            $first,
            'alis_gulu',
            'gulu_user',
            'password-one',
            $this->makeEd25519PublicKey('shared-one', $sharedBytes),
            $user,
        );

        Livewire::actingAs($user)
            ->test(AlisRemoteBackupKeysManager::class)
            ->set('facilityId', (string) $second->id)
            ->set('databaseName', 'alis_lira')
            ->set('databaseUsername', 'lira_user')
            ->set('databasePassword', 'password-two')
            ->set('publicKey', $this->makeEd25519PublicKey('shared-two', $sharedBytes))
            ->call('saveConfiguration')
            ->assertHasErrors(['publicKey']);
    }

    public function test_failed_provisioning_marks_configuration_as_failed_and_retry_can_recover(): void
    {
        $user = $this->actingAsSupportUser();
        $facility = $this->makeFacility('Mbarara RRH', 'UG000040-MBARARA-RRH');
        $this->configureProvisioning(false);

        Livewire::actingAs($user)
            ->test(AlisRemoteBackupKeysManager::class)
            ->set('facilityId', (string) $facility->id)
            ->set('databaseName', 'alis_mbarara')
            ->set('databaseUsername', 'mbarara_user')
            ->set('databasePassword', 'bad-pass')
            ->set('publicKey', $this->makeEd25519PublicKey('mbarara-key'))
            ->call('saveConfiguration')
            ->assertSet('flashError', 'The facility backup directory could not be provisioned on the central backup server.');

        $configuration = AlisBackupConfiguration::query()->where('facility_id', $facility->id)->firstOrFail();
        $this->assertSame(AlisBackupConfiguration::STATUS_PROVISIONING_FAILED, $configuration->status);

        $this->configureProvisioning(true);

        Livewire::actingAs($user)
            ->test(AlisRemoteBackupKeysManager::class)
            ->call('retryProvisioning', $configuration->id)
            ->assertSet('flashMessage', 'Provisioning completed successfully. The backup script is ready to download.');

        $this->assertSame(
            AlisBackupConfiguration::STATUS_PROVISIONED,
            $configuration->fresh()->status
        );
    }

    public function test_authorized_user_can_download_generated_script_and_download_is_audited(): void
    {
        $user = $this->actingAsSupportUser();
        $facility = $this->makeFacility('Masaka RRH', 'UG000050-MASAKA-RRH');
        $this->configureProvisioning();

        $configuration = app(AlisBackupConfigurationService::class)->saveConfiguration(
            $facility,
            'alis_masaka',
            'masaka_user',
            "p'ass",
            $this->makeEd25519PublicKey('masaka-key'),
            $user,
        );

        $response = $this->actingAs($user)->get(
            route('infrastructure.alis-remote-backup-keys.download-script', $configuration)
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'text/x-shellscript; charset=UTF-8');
        $this->assertStringContainsString('SSH_KEY="$HOME/.ssh/alis_backup_ed25519"', $response->streamedContent());
        $this->assertStringContainsString("REMOTE_SERVER='105.27.247.146'", $response->streamedContent());
        $this->assertStringContainsString("DB_PASS='p'\"'\"'ass'", $response->streamedContent());
        $this->assertStringNotContainsString('/root/.ssh/cis_backup_deploy', $response->streamedContent());

        $log = AuditLog::query()->where('action', 'downloaded_backup_script')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($configuration->id, $log->auditable_id);
        $this->assertArrayNotHasKey('database_password', $log->new_values ?? []);
    }

    public function test_unauthorized_user_cannot_download_generated_script(): void
    {
        $supportUser = $this->actingAsSupportUser();
        $manager = User::factory()->create([
            'role' => User::ROLE_ICT_MANAGER,
            'active' => true,
        ]);
        $facility = $this->makeFacility('Soroti RRH', 'UG000060-SOROTI-RRH');
        $this->configureProvisioning();

        $configuration = app(AlisBackupConfigurationService::class)->saveConfiguration(
            $facility,
            'alis_soroti',
            'soroti_user',
            'password',
            $this->makeEd25519PublicKey('soroti-key'),
            $supportUser,
        );

        $this->actingAs($manager)
            ->get(route('infrastructure.alis-remote-backup-keys.download-script', $configuration))
            ->assertForbidden();
    }

    public function test_authorized_user_can_start_an_audited_backup_download(): void
    {
        $user = $this->actingAsSupportUser();
        $facility = $this->makeFacility('Kayunga RRH', 'UG000070-KAYUNGA-RRH');
        $this->configureProvisioning();

        $configuration = app(AlisBackupConfigurationService::class)->saveConfiguration(
            $facility,
            'alis_kayunga',
            'kayunga_user',
            'password',
            $this->makeEd25519PublicKey('kayunga-key'),
            $user,
        );
        $filename = 'alisProduction_2026-07-24_16-29-00.sql.gz';
        $backupStatusService = Mockery::mock(AlisBackupStatusService::class);
        $backupStatusService->shouldReceive('assertDownloadable')
            ->once()
            ->with($configuration->backup_directory_name, $filename);
        $this->app->instance(AlisBackupStatusService::class, $backupStatusService);

        $this->actingAs($user)
            ->get(route('infrastructure.alis-remote-backup-keys.download-backup', [
                $configuration,
                'filename' => $filename,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/gzip')
            ->assertDownload($filename);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'downloaded_database_backup',
            'auditable_id' => $configuration->id,
            'user_id' => $user->id,
        ]);
    }

    private function actingAsSupportUser(): User
    {
        Region::query()->create(['name' => 'Northern', 'code' => 'northern']);

        return User::factory()->create([
            'role' => User::ROLE_ICT_SUPPORT_STAFF,
            'active' => true,
            'name' => 'Support User',
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    private function makeFacility(string $name, string $code): Facility
    {
        $region = Region::query()->first() ?? Region::query()->create(['name' => 'Central', 'code' => 'central']);

        return Facility::query()->create([
            'name' => $name,
            'code' => $code,
            'region_id' => $region->id,
            'district_name' => 'Kampala',
            'active' => true,
        ]);
    }

    private function latestPublicKeyFor(Facility $facility): string
    {
        return (string) AlisRemoteBackupKey::query()
            ->where('facility_id', $facility->id)
            ->value('public_key');
    }

    private function makeEd25519PublicKey(string $comment, ?string $publicKeyBytes = null): string
    {
        $type = 'ssh-ed25519';
        $publicKeyBytes ??= random_bytes(32);
        $payload = pack('N', strlen($type)).$type.pack('N', strlen($publicKeyBytes)).$publicKeyBytes;

        return $type.' '.base64_encode($payload).' '.$comment;
    }

    private function configureProvisioning(bool $success = true): void
    {
        Process::fake([
            '*' => $success
                ? Process::result('', '', 0)
                : Process::result('', 'failed', 1),
        ]);

        config()->set('alis_backup.server', '105.27.247.146');
        config()->set('alis_backup.user', 'backupuser');
        config()->set('alis_backup.port', 22);
        config()->set('alis_backup.root', '/dumps');
        config()->set('alis_backup.ssh_key', __FILE__);
        config()->set('alis_backup.facility_offsite_server_ip', '105.27.247.146');
        config()->set('alis_remote_backup_keys.host', '105.27.247.146');
        config()->set('alis_remote_backup_keys.port', 22);
        config()->set('alis_remote_backup_keys.user', 'backupuser');
        config()->set('alis_remote_backup_keys.identity_file', __FILE__);
        config()->set('alis_remote_backup_keys.authorized_keys_path', '/home/backupuser/.ssh/authorized_keys');
        config()->set('alis_remote_backup_keys.remote_temp_path', '/tmp/authorized_keys.generated');
        config()->set('alis_remote_backup_keys.install_command', 'sudo /usr/local/bin/install_alis_authorized_keys.sh');
    }
}
