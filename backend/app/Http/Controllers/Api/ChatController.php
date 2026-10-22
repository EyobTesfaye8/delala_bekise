<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Proposal;
use App\Services\AntiCircumventionFilter;
use App\States\Job\Negotiating as JobNegotiating;
use App\States\Proposal\Negotiating as ProposalNegotiating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(
        private AntiCircumventionFilter $filter
    ) {}

    /**
     * Get or create a chat channel for a proposal (FR-4.3).
     */
    public function getOrCreate(Request $request, Proposal $proposal): JsonResponse
    {
        $user = $request->user();

        // Must be the buyer of the job or the delala of the proposal
        if ($user->id !== $proposal->job->buyer_id && $user->id !== $proposal->delala_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // FR-4.3 Gate: Delalas cannot initiate chat first before the buyer engages
        $chat = Chat::where('proposal_id', $proposal->id)->first();
        if (! $chat && $user->id === $proposal->delala_id) {
            return response()->json([
                'message' => 'The buyer must initiate the negotiation chat first.',
            ], 403);
        }

        if (! $chat) {
            $chat = Chat::create([
                'proposal_id' => $proposal->id,
                'buyer_id' => $proposal->job->buyer_id,
                'delala_id' => $proposal->delala_id,
                'is_read_only' => false,
            ]);

            // Transition proposal to Negotiating
            if ($proposal->status->canTransitionTo(ProposalNegotiating::class)) {
                $proposal->status->transitionTo(ProposalNegotiating::class);
            }

            // Transition job to Negotiating if open
            if ($proposal->job->status->canTransitionTo(JobNegotiating::class)) {
                $proposal->job->status->transitionTo(JobNegotiating::class);
            }
        }

        $job = $proposal->job;
        $isContracted = in_array($job->status->getValue(), ['contracted', 'completed'], true);

        // Header info: Phone numbers revealed ONLY once Escrow is Funded (Contracted) per FR-4.5
        $contactDetails = $isContracted ? [
            'buyer_phone' => $chat->buyer->phone,
            'delala_phone' => $chat->delala->phone,
        ] : null;

        return response()->json([
            'chat' => $chat->load(['messages' => function ($q) {
                $q->orderBy('created_at', 'desc')->take(50);
            }]),
            'contact_details' => $contactDetails,
            'is_contracted' => $isContracted,
        ]);
    }

    /**
     * Send a message in a chat with Anti-Circumvention Protection (FR-4.5).
     */
    public function sendMessage(Request $request, Chat $chat): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $chat->buyer_id && $user->id !== $chat->delala_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($chat->is_read_only) {
            return response()->json(['message' => 'This chat is read-only.'], 403);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:text,image,system'],
            'body' => ['required_if:type,text,system', 'nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ]);

        $job = $chat->proposal->job;
        $isContracted = in_array($job->status->getValue(), ['contracted', 'completed'], true);

        // FR-4.5 Anti-Circumvention Filter (Pre-Contract)
        if (! $isContracted && ! empty($validated['body'])) {
            if ($this->filter->containsProhibitedContactInfo($validated['body'])) {
                $user->increment('circumvention_strike_count');

                if ($user->circumvention_strike_count >= 3) {
                    Log::warning("Circumvention Strike Alert: User #{$user->id} ({$user->phone}) reached {$user->circumvention_strike_count} strikes.");
                }

                return response()->json([
                    'message' => 'Sharing contact details before funding escrow violates platform rules.',
                    'error_code' => 'CIRCUMVENTION_DETECTED',
                    'strike_count' => $user->circumvention_strike_count,
                ], 422);
            }
        }

        $message = $chat->messages()->create([
            'sender_id' => $user->id,
            'type' => $validated['type'],
            'body' => $validated['body'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        // Broadcast to WebSocket channel
        broadcast(new ChatMessageSent($message))->toOthers();

        return response()->json([
            'message' => 'Sent successfully',
            'data' => $message,
        ], 201);
    }
}
