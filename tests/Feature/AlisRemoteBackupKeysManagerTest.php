<?php

namespace Tests\Feature;

use App\Livewire\AlisRemoteBackupKeysManager;
use App\Models\AlisRemoteBackupKey;
use App\Models\AlisRemoteBackupKeyUpdateReason;
use App\Models\Facility;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

class AlisRemoteBackupKeysManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_register_a_new_remote_backup_key(): void
    {
        $user = $this->actingAsSupportUser();
        $facility = Facility::query()->create(['name' => 'Arua RRH', 'code' => 'UG000002-ARUA-RRH']);

        Livewire::actingAs($user)
            ->test(AlisRemoteBackupKeysManager::class)
            ->set('facilityId', (string) $facility->id)
            ->set('publicKey', $this->makeEd25519PublicKey('alis-offsite-backup'))
            ->call('saveKey')
            ->assertSet('flashMessage', 'Key saved successfully. Pending deployment.');

        $this->assertDatabaseHas('alis_remote_backup_keys', [
            'facility_id' => $facility->id,
            'status' => 'active',
            'deployment_status' => 'pending',
            'key_type' => 'ssh-ed25519',
        ]);
    }

    public function test_updating_existing_key_requires_reason(): void
    {
        $user = $this->actingAsSupportUser();
        $facility = Facility::query()->create(['name' => 'Mbale RRH', 'code' => 'UG000010-MBALE-RRH']);

        AlisRemoteBackupKey::query()->create([
            'facility_id' => $facility->id,
            'public_key' => $this->makeEd25519PublicKey('first-key'),
            'fingerprint' => 'SHA256:firstFingerprint',
            'key_type' => 'ssh-ed25519',
            'status' => 'active',
            'deployment_status' => 'deployed',
        ]);

        Livewire::actingAs($user)
            ->test(AlisRemoteBackupKeysManager::class)
            ->set('facilityId', (string) $facility->id)
            ->set('publicKey', $this->makeEd25519PublicKey('second-key'))
            ->call('saveKey')
            ->assertHasErrors(['reasonId']);
    }

    public function test_duplicate_key_for_another_facility_is_rejected(): void
    {
        $user = $this->actingAsSupportUser();
        $key = $this->makeEd25519PublicKey('shared-key');
        $firstFacility = Facility::query()->create(['name' => 'Gulu RRH', 'code' => 'UG000020-GULU-RRH']);
        $secondFacility = Facility::query()->create(['name' => 'Lira RRH', 'code' => 'UG000021-LIRA-RRH']);

        AlisRemoteBackupKey::query()->create([
            'facility_id' => $firstFacility->id,
            'public_key' => $key,
            'fingerprint' => 'SHA256:existingShared',
            'key_type' => 'ssh-ed25519',
            'status' => 'active',
            'deployment_status' => 'deployed',
        ]);

        Livewire::actingAs($user)
            ->test(AlisRemoteBackupKeysManager::class)
            ->set('facilityId', (string) $secondFacility->id)
            ->set('publicKey', $key)
            ->call('saveKey')
            ->assertHasErrors(['publicKey']);
    }

    public function test_pending_keys_can_be_deployed_from_livewire(): void
    {
        $user = $this->actingAsSupportUser();
        $facility = Facility::query()->create(['name' => 'Mbarara RRH', 'code' => 'UG000030-MBARARA-RRH']);
        $reason = AlisRemoteBackupKeyUpdateReason::query()->where('name', 'Other')->firstOrFail();

        AlisRemoteBackupKey::query()->create([
            'facility_id' => $facility->id,
            'public_key' => $this->makeEd25519PublicKey('deployable'),
            'fingerprint' => 'SHA256:deployableKey',
            'key_type' => 'ssh-ed25519',
            'status' => 'active',
            'deployment_status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Process::fake([
            '*' => Process::result('', '', 0),
        ]);

        config()->set('alis_remote_backup_keys.host', '10.200.0.160');
        config()->set('alis_remote_backup_keys.port', 865);
        config()->set('alis_remote_backup_keys.user', 'cis-backup-admin');
        config()->set('alis_remote_backup_keys.install_command', 'sudo /usr/local/bin/install_alis_authorized_keys.sh');
        config()->set('alis_remote_backup_keys.remote_temp_path', '/tmp/authorized_keys.generated');

        Livewire::actingAs($user)
            ->test(AlisRemoteBackupKeysManager::class)
            ->set('facilityId', (string) $facility->id)
            ->set('reasonId', (string) $reason->id)
            ->call('deployKeys')
            ->assertSet('flashMessage', 'All active SSH keys were deployed successfully.');

        $this->assertDatabaseHas('alis_remote_backup_keys', [
            'facility_id' => $facility->id,
            'deployment_status' => 'deployed',
            'deployed_by' => $user->id,
        ]);

        $this->assertDatabaseHas('alis_remote_backup_deployments', [
            'status' => 'success',
            'backup_server' => '10.200.0.160',
        ]);
    }

    private function actingAsSupportUser(): User
    {
        $region = Region::query()->create(['name' => 'Northern', 'code' => 'northern']);

        return User::factory()->create([
            'role' => User::ROLE_ICT_SUPPORT_STAFF,
            'active' => true,
            'name' => 'Support User',
            'email' => 'support@example.com',
        ]);
    }

    private function makeEd25519PublicKey(string $comment): string
    {
        $type = 'ssh-ed25519';
        $publicKeyBytes = random_bytes(32);
        $payload = pack('N', strlen($type)).$type.pack('N', strlen($publicKeyBytes)).$publicKeyBytes;

        return $type.' '.base64_encode($payload).' '.$comment;
    }
}
