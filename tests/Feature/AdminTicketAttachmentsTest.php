<?php

namespace Tests\Feature;

use App\Models\AttachmentSecurityScan;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTicketAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_ticket_detail_page_lists_all_uploaded_attachments(): void
    {
        $supportUser = User::query()->where('email', 'support@cphl.go.ug')->firstOrFail();
        $supportUser->forceFill([
            'force_password_change' => false,
            'password_changed_at' => now(),
        ])->save();

        $ticket = Ticket::query()->create([
            'ticket_number' => 'CIS-TEST-001',
            'system_id' => \App\Models\SupportSystem::query()->where('code', 'rds')->value('id'),
            'designation_id' => \App\Models\Designation::query()->firstOrFail()->id,
            'issue_started_at' => now()->toDateString(),
            'full_name' => 'Test Reporter',
            'phone' => '0700000010',
            'email' => 'reporter@example.com',
            'lab_manager_name' => 'Manager',
            'lab_manager_email' => 'manager@example.com',
            'region_id' => \App\Models\Region::query()->firstOrFail()->id,
            'district_name' => 'Kyegegwa',
            'facility_id' => \App\Models\Facility::query()->firstOrFail()->id,
            'issue_type_id' => \App\Models\IssueType::query()->firstOrFail()->id,
            'priority_level_id' => \App\Models\PriorityLevel::query()->firstOrFail()->id,
            'description' => 'Attachment display test for multiple screenshots.',
            'status_id' => \App\Models\TicketStatus::query()->where('code', 'new')->value('id'),
            'submission_review_status' => 'ACCEPTED',
            'assigned_to' => $supportUser->id,
        ]);

        foreach (['first.png', 'second.png', 'third.png'] as $filename) {
            $attachment = TicketAttachment::query()->create([
                'ticket_id' => $ticket->id,
                'storage_disk' => 'local',
                'storage_path' => 'tickets/2026/07/'.$filename,
                'original_filename' => $filename,
                'detected_mime_type' => 'image/png',
                'extension' => 'png',
                'file_size' => 1024,
                'checksum' => hash('sha256', $filename),
                'status' => 'SCAN_FAILED',
                'metadata' => ['uploaded_by' => 'public_form'],
            ]);

            AttachmentSecurityScan::query()->create([
                'ticket_attachment_id' => $attachment->id,
                'status' => 'SCAN_FAILED',
                'scan_result' => 'Antivirus scanning unavailable.',
                'scanned_at' => now(),
            ]);
        }

        $response = $this->actingAs($supportUser)
            ->get(route('admin.tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Attachments:');
        $response->assertSee('first.png');
        $response->assertSee('second.png');
        $response->assertSee('third.png');
        $response->assertSee('attachments/'.$ticket->attachments[0]->id.'/preview', false);
    }

    public function test_preview_route_renders_image_inline_and_download_route_remains_available(): void
    {
        $ticket = Ticket::query()->create([
            'ticket_number' => 'CIS-TEST-002',
            'system_id' => \App\Models\SupportSystem::query()->where('code', 'rds')->value('id'),
            'designation_id' => \App\Models\Designation::query()->firstOrFail()->id,
            'issue_started_at' => now()->toDateString(),
            'full_name' => 'Test Reporter',
            'phone' => '0700000010',
            'email' => 'reporter@example.com',
            'lab_manager_name' => 'Manager',
            'lab_manager_email' => 'manager@example.com',
            'region_id' => \App\Models\Region::query()->firstOrFail()->id,
            'district_name' => 'Kyegegwa',
            'facility_id' => \App\Models\Facility::query()->firstOrFail()->id,
            'issue_type_id' => \App\Models\IssueType::query()->firstOrFail()->id,
            'priority_level_id' => \App\Models\PriorityLevel::query()->firstOrFail()->id,
            'description' => 'Inline preview test.',
            'status_id' => \App\Models\TicketStatus::query()->where('code', 'new')->value('id'),
            'submission_review_status' => 'ACCEPTED',
        ]);

        $imagePath = 'tickets/2026/07/inline-preview.png';
        \Illuminate\Support\Facades\Storage::disk('local')->put($imagePath, 'fake-image-content');

        $attachment = TicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'storage_disk' => 'local',
            'storage_path' => $imagePath,
            'original_filename' => 'inline-preview.png',
            'detected_mime_type' => 'image/png',
            'extension' => 'png',
            'file_size' => 1024,
            'checksum' => hash('sha256', 'inline-preview.png'),
            'status' => 'SCAN_FAILED',
            'metadata' => ['uploaded_by' => 'public_form'],
        ]);

        $previewResponse = $this->get($attachment->previewUrl());
        $previewResponse->assertOk();
        $previewResponse->assertHeader('Content-Disposition', 'inline; filename="inline-preview.png"');

        $downloadResponse = $this->get($attachment->downloadUrl());
        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('content-disposition');
    }
}
