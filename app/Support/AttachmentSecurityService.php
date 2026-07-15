<?php

namespace App\Support;

use App\Models\AttachmentSecurityScan;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentSecurityService
{
    private const ALLOWED_MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'txt' => ['text/plain'],
    ];
    private const DEFAULT_MAX_FILES = 5;
    private const DEFAULT_MAX_SIZE_MB = 10;
    private const DEFAULT_ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'txt'];

    public function storeForTicket(Ticket $ticket, array $files): array
    {
        $this->validateFiles($files);

        $attachments = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $mimeType = strtolower((string) $file->getMimeType());
            $filename = (string) Str::uuid().'.'.$extension;
            $path = $file->storeAs('tickets/'.now()->format('Y/m'), $filename, 'local');
            $checksum = hash_file('sha256', $file->getRealPath());
            $status = config('cis_submission.attachments.antivirus_enabled') ? 'PENDING_SCAN' : 'SCAN_FAILED';

            $attachment = $ticket->attachments()->create([
                'storage_disk' => 'local',
                'storage_path' => $path,
                'original_filename' => $originalName,
                'detected_mime_type' => $mimeType,
                'extension' => $extension,
                'file_size' => $file->getSize(),
                'checksum' => $checksum,
                'status' => $status === 'PENDING_SCAN' ? 'PENDING_SCAN' : 'SCAN_FAILED',
                'metadata' => [
                    'uploaded_by' => 'public_form',
                ],
            ]);

            AttachmentSecurityScan::query()->create([
                'ticket_attachment_id' => $attachment->id,
                'status' => $status,
                'scanner' => config('cis_submission.attachments.antivirus_enabled') ? 'configured' : null,
                'scan_result' => $status === 'SCAN_FAILED' ? 'Antivirus scanning unavailable.' : null,
                'scanned_at' => config('cis_submission.attachments.antivirus_enabled') ? null : now(),
            ]);

            $attachments[] = $attachment;
        }

        return $attachments;
    }

    public function validateFiles(array $files): void
    {
        $maxFiles = max(1, (int) config('cis_submission.attachments.max_files', self::DEFAULT_MAX_FILES));
        $maxSizeMb = max(1, (int) config('cis_submission.attachments.max_size_mb', self::DEFAULT_MAX_SIZE_MB));

        if (count($files) > $maxFiles) {
            throw ValidationException::withMessages([
                'attachments' => 'Too many files were uploaded. You can attach up to '.$maxFiles.' files. If you have more than 2 screenshots, please combine them into one PDF and upload that instead.',
            ]);
        }

        $maxBytes = $maxSizeMb * 1024 * 1024;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            if ($file->getSize() > $maxBytes) {
                throw ValidationException::withMessages([
                    'attachments' => 'One of the attachments is too large.',
                ]);
            }

            $originalName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $mimeType = strtolower((string) $file->getMimeType());
            $allowedExtensions = config('cis_submission.attachments.allowed_extensions', self::DEFAULT_ALLOWED_EXTENSIONS);
            $allowedExtensions = is_array($allowedExtensions) ? array_values($allowedExtensions) : self::DEFAULT_ALLOWED_EXTENSIONS;

            if (! (bool) config('cis_submission.attachments.allow_plain_text', false)) {
                $allowedExtensions = array_values(array_diff($allowedExtensions, ['txt']));
            }

            if (substr_count($originalName, '.') > 1) {
                $parts = explode('.', strtolower($originalName));
                if (! in_array(end($parts), $allowedExtensions, true) || ! in_array(prev($parts), $allowedExtensions, true)) {
                    throw ValidationException::withMessages([
                        'attachments' => 'One of the attachments has an invalid filename.',
                    ]);
                }
            }

            if (! in_array($extension, $allowedExtensions, true)) {
                throw ValidationException::withMessages([
                    'attachments' => 'One of the attachments uses a blocked file type.',
                ]);
            }

            if (! in_array($mimeType, self::ALLOWED_MIME_TYPES[$extension] ?? [], true)) {
                throw ValidationException::withMessages([
                    'attachments' => 'One of the attachments could not be verified.',
                ]);
            }
        }
    }

    public function streamDownload(TicketAttachment $attachment)
    {
        return Storage::disk($attachment->storage_disk)->download(
            $attachment->storage_path,
            $attachment->original_filename,
            [
                'Content-Disposition' => 'attachment; filename="'.$attachment->original_filename.'"',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function streamInline(TicketAttachment $attachment): StreamedResponse
    {
        return Storage::disk($attachment->storage_disk)->response(
            $attachment->storage_path,
            $attachment->original_filename,
            [
                'Content-Type' => $attachment->detected_mime_type,
                'Content-Disposition' => 'inline; filename="'.$attachment->original_filename.'"',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
