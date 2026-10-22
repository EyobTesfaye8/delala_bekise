<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Proposal;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PiiFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Chat service — negotiation channel management.
 * SRS FR-4.5: PII filter blocks contact info sharing before escrow FUNDED.
 */
class ChatService
{
    public function __construct(
        private readonly PiiFilterService $pii,
        private readonly AuditLogService  $auditLog,
    ) {}

    /**
     * Open (or retrieve) the chat channel for a proposal.
     */
    public function initiateForProposal(Proposal $proposal, Request $request): Chat
    {
        $existing = Chat::where('proposal_id', $proposal->id)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($proposal, $request) {
            $chat = Chat::create([
                'proposal_id' => $proposal->id,
                'buyer_id'    => $proposal->job->buyer_id,
                'delala_id'   => $proposal->delala_id,
                'is_read_only' => false,
            ]);

            // Post a system event message
            ChatMessage::create([
                'chat_id'   => $chat->id,
                'sender_id' => $proposal->job->buyer_id,
                'type'      => 'system_event',
                'body'      => 'Chat channel opened for proposal negotiation.',
                'metadata'  => ['event' => 'chat_opened', 'proposal_uuid' => $proposal->uuid],
            ]);

            $this->auditLog->log('chat_initiated', Chat::class, $chat->id, [], [], $request);

            return $chat;
        });
    }

    /**
     * Send a message in a chat.
     * SRS FR-4.5: Checks PII filter unless escrow is already FUNDED.
     */
    public function sendMessage(Chat $chat, User $sender, string $body, Request $request): ChatMessage
    {
        if ($chat->is_read_only) {
            throw new \Exception('CHAT_IS_READ_ONLY', 403);
        }

        // Authorize — only buyer or delala of this chat may send
        if ($sender->id !== $chat->buyer_id && $sender->id !== $chat->delala_id) {
            throw new \Exception('NOT_CHAT_PARTICIPANT', 403);
        }

        // PII filter — block contact sharing before escrow FUNDED
        $escrowFunded = $chat->proposal
            ?->items
            ?->first()
            ?->escrowTransactions()
            ->whereIn('status', ['funded', 'handover_declared', 'released'])
            ->exists();

        if (! $escrowFunded && $this->pii->containsBlockedContent($body)) {
            $category = $this->pii->detectCategory($body);

            $this->auditLog->log('pii_blocked', Chat::class, $chat->id, [], [
                'sender_id'      => $sender->id,
                'pii_category'   => $category,
            ], $request);

            // Increment circumvention strike (SRS NFR-SEC-3)
            $sender->increment('circumvention_strikes');

            throw new \Exception('PII_BLOCKED', 422);
        }

        return ChatMessage::create([
            'chat_id'   => $chat->id,
            'sender_id' => $sender->id,
            'type'      => 'text',
            'body'      => $body,
        ]);
    }

    /**
     * Lock chat channel (called after escrow reaches a terminal state).
     */
    public function lockChat(Chat $chat): void
    {
        $chat->update(['is_read_only' => true]);
    }
}
