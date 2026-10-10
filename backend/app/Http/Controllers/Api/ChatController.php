<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Get or create a chat for a proposal.
     */
    public function getOrCreate(Request $request, Proposal $proposal): JsonResponse
    {
        $user = $request->user();

        // Must be the buyer of the job or the delala of the proposal
        if ($user->id !== $proposal->job->buyer_id && $user->id !== $proposal->delala_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $chat = Chat::firstOrCreate([
            'proposal_id' => $proposal->id,
        ], [
            'buyer_id' => $proposal->job->buyer_id,
            'delala_id' => $proposal->delala_id,
            'is_read_only' => false,
        ]);

        return response()->json([
            'chat' => $chat->load(['messages' => function ($q) {
                $q->orderBy('created_at', 'desc')->take(50);
            }]),
        ]);
    }

    /**
     * Send a message in a chat.
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
            'type' => 'required|in:text,image,system',
            'body' => 'required_if:type,text,system|string',
            'metadata' => 'nullable|array',
        ]);

        $message = $chat->messages()->create([
            'sender_id' => $user->id,
            'type' => $validated['type'],
            'body' => $validated['body'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        // Fire Laravel Reverb Event here for websockets
        broadcast(new ChatMessageSent($message))->toOthers();
        // broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'message' => 'Sent successfully',
            'data' => $message,
        ], 201);
    }
}
