<?php

namespace App\Support;

use App\Models\AlisRemoteBackupKey;
use App\Models\AlisRemoteBackupKeyHistory;
use App\Models\AlisRemoteBackupKeyUpdateReason;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AlisRemoteBackupKeyService
{
    public function saveKey(
        Facility $facility,
        string $publicKeyInput,
        User $user,
        ?AlisRemoteBackupKeyUpdateReason $reason = null,
        ?string $comments = null,
    ): AlisRemoteBackupKey {
        $existing = AlisRemoteBackupKey::query()
            ->where('facility_id', $facility->id)
            ->where('status', 'active')
            ->first();

        $payload = $this->parsePublicKey($publicKeyInput, $facility->id, $existing?->id);
        $isChangingKey = ! $existing || $existing->fingerprint !== $payload['fingerprint'];

        if ($existing && $isChangingKey && ! $reason) {
            throw ValidationException::withMessages([
                'reasonId' => 'Please provide a reason for updating the existing SSH key.',
            ]);
        }

        if ($isChangingKey && $reason?->isOtherReason() && blank(trim((string) $comments))) {
            throw ValidationException::withMessages([
                'comments' => 'Please specify the reason for this SSH key update.',
            ]);
        }

        return DB::transaction(function () use ($existing, $facility, $payload, $user, $reason, $comments, $isChangingKey) {
            if (! $existing) {
                $key = AlisRemoteBackupKey::query()->create([
                    'facility_id' => $facility->id,
                    'public_key' => $payload['normalized_key'],
                    'fingerprint' => $payload['fingerprint'],
                    'key_type' => $payload['key_type'],
                    'key_comment' => $payload['key_comment'],
                    'status' => 'active',
                    'deployment_status' => 'pending',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $this->recordHistory($facility->id, 'created', null, $key, $user->id, null, null);

                return $key;
            }

            if (! $isChangingKey) {
                return $existing->fresh();
            }

            $oldValues = clone $existing;
            $existing->fill([
                'public_key' => $payload['normalized_key'],
                'fingerprint' => $payload['fingerprint'],
                'key_type' => $payload['key_type'],
                'key_comment' => $payload['key_comment'],
                'deployment_status' => 'pending',
                'updated_by' => $user->id,
                'deployed_by' => null,
                'deployed_at' => null,
                'last_deployment_error' => null,
            ]);
            $existing->save();

            $this->recordHistory($facility->id, 'updated', $oldValues, $existing, $user->id, $reason?->id, $comments);

            return $existing->fresh();
        });
    }

    public function parsePublicKey(string $input, ?int $facilityId = null, ?int $ignoreKeyId = null): array
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            throw ValidationException::withMessages(['publicKey' => 'SSH public key is required.']);
        }

        foreach ([
            '-----BEGIN OPENSSH PRIVATE KEY-----',
            '-----BEGIN RSA PRIVATE KEY-----',
            '-----BEGIN PRIVATE KEY-----',
        ] as $forbiddenMarker) {
            if (str_contains($trimmed, $forbiddenMarker)) {
                throw ValidationException::withMessages(['publicKey' => 'Private key content is not allowed.']);
            }
        }

        if (preg_match('/\r|\n/', $trimmed)) {
            throw ValidationException::withMessages(['publicKey' => 'SSH public key must be a single line.']);
        }

        $parts = preg_split('/\s+/', $trimmed, 3) ?: [];

        if (count($parts) < 2) {
            throw ValidationException::withMessages(['publicKey' => 'SSH public key format is invalid.']);
        }

        [$keyType, $keyBody] = [$parts[0], $parts[1]];
        $keyComment = $parts[2] ?? null;
        $allowRsa = (bool) config('alis_remote_backup_keys.allow_rsa', false);

        if ($keyType !== 'ssh-ed25519' && ! ($allowRsa && $keyType === 'ssh-rsa')) {
            $message = $allowRsa
                ? 'Only ssh-ed25519 and explicitly enabled ssh-rsa public keys are allowed.'
                : 'Only ssh-ed25519 public keys are allowed.';

            throw ValidationException::withMessages(['publicKey' => $message]);
        }

        $decoded = base64_decode($keyBody, true);

        if ($decoded === false) {
            throw ValidationException::withMessages(['publicKey' => 'SSH public key body is not valid base64.']);
        }

        $parsedType = $this->readSshString($decoded, 0);

        if ($parsedType === null || $parsedType['value'] !== $keyType) {
            throw ValidationException::withMessages(['publicKey' => 'SSH public key type does not match the encoded key body.']);
        }

        $fingerprint = 'SHA256:'.rtrim(base64_encode(hash('sha256', $decoded, true)), '=');
        $normalizedKey = trim($keyType.' '.$keyBody.' '.trim((string) $keyComment));

        $duplicate = AlisRemoteBackupKey::query()
            ->where('fingerprint', $fingerprint)
            ->when($ignoreKeyId, fn ($query) => $query->where('id', '!=', $ignoreKeyId))
            ->when($facilityId, fn ($query) => $query->where('facility_id', '!=', $facilityId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['publicKey' => 'This SSH public key is already registered for another facility.']);
        }

        return [
            'normalized_key' => $normalizedKey,
            'fingerprint' => $fingerprint,
            'key_type' => $keyType,
            'key_comment' => $keyComment ? trim($keyComment) : null,
        ];
    }

    private function readSshString(string $bytes, int $offset): ?array
    {
        if (strlen($bytes) < $offset + 4) {
            return null;
        }

        $length = unpack('N', substr($bytes, $offset, 4))[1];
        $start = $offset + 4;
        $value = substr($bytes, $start, $length);

        if (strlen($value) !== $length) {
            return null;
        }

        return [
            'value' => $value,
            'offset' => $start + $length,
        ];
    }

    private function recordHistory(
        int $facilityId,
        string $action,
        ?AlisRemoteBackupKey $oldKey,
        AlisRemoteBackupKey $newKey,
        ?int $performedBy,
        ?int $reasonId,
        ?string $comments,
        ?int $deploymentBatchId = null,
    ): void {
        AlisRemoteBackupKeyHistory::query()->create([
            'facility_id' => $facilityId,
            'action' => $action,
            'old_public_key' => $oldKey?->public_key,
            'new_public_key' => $newKey->public_key,
            'old_fingerprint' => $oldKey?->fingerprint,
            'new_fingerprint' => $newKey->fingerprint,
            'reason_id' => $reasonId,
            'comments' => $comments ? trim($comments) : null,
            'performed_by' => $performedBy,
            'performed_at' => now(),
            'deployment_batch_id' => $deploymentBatchId,
        ]);
    }
}
