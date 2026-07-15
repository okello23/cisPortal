<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('priority_level_id');
            $table->uuid('submission_uuid')->nullable()->after('ticket_number');
            $table->string('content_fingerprint', 64)->nullable()->after('ip_address');
            $table->unsignedInteger('submission_risk_score')->default(0)->after('content_fingerprint');
            $table->string('submission_risk_level')->default('LOW')->after('submission_risk_score');
            $table->json('submission_risk_reasons')->nullable()->after('submission_risk_level');
            $table->boolean('is_suspected_spam')->default(false)->after('submission_risk_reasons');
            $table->boolean('is_possible_duplicate')->default(false)->after('is_suspected_spam');
            $table->timestamp('quarantined_at')->nullable()->after('is_possible_duplicate');
            $table->foreignId('quarantined_by')->nullable()->after('quarantined_at')->constrained('users')->nullOnDelete();
            $table->string('quarantine_reason')->nullable()->after('quarantined_by');
            $table->timestamp('reviewed_at')->nullable()->after('quarantine_reason');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->foreignId('duplicate_of_ticket_id')->nullable()->after('reviewed_by')->constrained('tickets')->nullOnDelete();
            $table->decimal('duplicate_confidence', 5, 2)->nullable()->after('duplicate_of_ticket_id');
            $table->string('duplicate_review_status')->nullable()->after('duplicate_confidence');
            $table->boolean('turnstile_verified')->default(false)->after('duplicate_review_status');
            $table->string('turnstile_error_code')->nullable()->after('turnstile_verified');
            $table->string('submission_review_status')->default('ACCEPTED')->after('turnstile_error_code');
            $table->timestamp('released_to_queue_at')->nullable()->after('submission_review_status');

            $table->unique('submission_uuid');
            $table->index(['submission_risk_level', 'submission_review_status']);
            $table->index('content_fingerprint');
            $table->index('ip_address');
            $table->index('email');
            $table->index('quarantined_at');
            $table->index('duplicate_of_ticket_id');
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_path');
            $table->string('original_filename');
            $table->string('detected_mime_type', 120)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->string('status', 32)->default('PENDING_SCAN');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'status']);
            $table->index('checksum');
        });

        Schema::create('public_submission_security_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 64);
            $table->uuid('submission_uuid')->nullable();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('source_ip', 45)->nullable();
            $table->string('requestor_email_hash', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedInteger('risk_score')->nullable();
            $table->json('metadata')->nullable();
            $table->string('action_taken', 120)->nullable();
            $table->foreignId('reviewing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
            $table->index('source_ip');
            $table->index('submission_uuid');
        });

        Schema::create('submission_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('block_type', 32);
            $table->string('block_value');
            $table->string('reason')->nullable();
            $table->timestamp('blocked_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['block_type', 'block_value', 'is_active']);
            $table->index('expires_at');
        });

        Schema::create('submission_duplicate_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('matched_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->string('review_status')->default('PENDING');
            $table->json('reasons')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'matched_ticket_id']);
            $table->index(['review_status', 'confidence']);
        });

        Schema::create('attachment_security_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_attachment_id')->constrained('ticket_attachments')->cascadeOnDelete();
            $table->string('status', 32)->default('PENDING_SCAN');
            $table->string('scanner')->nullable();
            $table->text('scan_result')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->index(['ticket_attachment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_security_scans');
        Schema::dropIfExists('submission_duplicate_matches');
        Schema::dropIfExists('submission_blocks');
        Schema::dropIfExists('public_submission_security_events');
        Schema::dropIfExists('ticket_attachments');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quarantined_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('duplicate_of_ticket_id');
            $table->dropUnique(['submission_uuid']);
            $table->dropIndex(['submission_risk_level', 'submission_review_status']);
            $table->dropIndex(['content_fingerprint']);
            $table->dropIndex(['ip_address']);
            $table->dropIndex(['email']);
            $table->dropIndex(['quarantined_at']);
            $table->dropIndex(['duplicate_of_ticket_id']);

            $table->dropColumn([
                'submission_uuid',
                'subject',
                'content_fingerprint',
                'submission_risk_score',
                'submission_risk_level',
                'submission_risk_reasons',
                'is_suspected_spam',
                'is_possible_duplicate',
                'quarantined_at',
                'quarantine_reason',
                'reviewed_at',
                'duplicate_confidence',
                'duplicate_review_status',
                'turnstile_verified',
                'turnstile_error_code',
                'submission_review_status',
                'released_to_queue_at',
            ]);
        });
    }
};
