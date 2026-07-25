<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Support\AuditService;
use App\Support\BugAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class BugAssistantController extends Controller
{
    public function __construct(
        private readonly BugAssistantService $assistant,
        private readonly AuditService $auditService,
    ) {}

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request->user(), $ticket);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:3000'],
            'attachment_ids' => ['nullable', 'array', 'max:3'],
            'attachment_ids.*' => [
                'integer',
                Rule::exists('ticket_attachments', 'id')->where('ticket_id', $ticket->id),
            ],
        ]);

        $attachments = $this->eligibleAttachments(
            $ticket,
            collect($validated['attachment_ids'] ?? [])->map(fn ($id) => (int) $id)
        );

        try {
            $result = $this->assistant->respond($ticket, $attachments, trim($validated['message']));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'The AI assistant could not be reached. Please try again.',
            ], 503);
        }

        $ticket->aiMessages()->create([
            'user_id' => $request->user()->id,
            'role' => 'user',
            'content' => trim($validated['message']),
            'attachment_ids' => $attachments->pluck('id')->all(),
        ]);

        $assistantMessage = $ticket->aiMessages()->create([
            'role' => 'assistant',
            'content' => $result['content'],
            'provider' => 'openai',
            'model' => $result['model'],
            'provider_response_id' => $result['response_id'],
            'metadata' => ['usage' => $result['usage']],
        ]);

        $this->auditService->log(
            'ticket.ai_assistant_responded',
            $ticket,
            null,
            [
                'message_id' => $assistantMessage->id,
                'attachment_ids' => $attachments->pluck('id')->all(),
                'model' => $result['model'],
            ],
            $request->user()->id,
            $request
        );

        return response()->json([
            'message' => [
                'id' => $assistantMessage->id,
                'role' => 'assistant',
                'content' => $assistantMessage->content,
                'created_at' => $assistantMessage->created_at->toIso8601String(),
            ],
        ]);
    }

    private function eligibleAttachments(Ticket $ticket, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $attachments = $ticket->attachments()
            ->whereIn('id', $ids)
            ->get()
            ->filter(function (TicketAttachment $attachment) {
                return $attachment->isPreviewableImage()
                    && ! in_array(strtoupper((string) $attachment->status), ['INFECTED', 'BLOCKED'], true)
                    && $attachment->file_size <= (int) config('services.openai.max_image_bytes', 6 * 1024 * 1024)
                    && Storage::disk($attachment->storage_disk)->exists($attachment->storage_path);
            })
            ->values();

        abort_if($attachments->count() !== $ids->unique()->count(), 422, 'One or more selected screenshots cannot be analyzed safely.');

        return $attachments;
    }

    private function authorizeTicket(User $user, Ticket $ticket): void
    {
        $manager = $user->hasAnyRole([
            User::ROLE_ICT_ADMIN,
            User::ROLE_ICT_MANAGER,
            User::ROLE_ICT_SUPERVISOR,
        ]);

        abort_unless($manager || $ticket->assigned_to === $user->id, 403);
    }
}
