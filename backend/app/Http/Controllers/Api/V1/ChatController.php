<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\Proposal;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chat controller — negotiation messaging.
 * SRS FR-4.5.
 */
class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chatService) {}

    /** POST /api/v1/chats/initiate/{proposal} */
    public function initiate(Request $request, Proposal $proposal): JsonResponse
    {
        // Only buyer of the job may initiate
        if ($request->user()->id !== $proposal->job->buyer_id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        $chat = $this->chatService->initiateForProposal($proposal, $request);

        return response()->json(['chat' => new ChatResource($chat)], 201);
    }

    /** GET /api/v1/chats */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $chats  = Chat::with(['proposal', 'buyer', 'delala'])
            ->where('buyer_id', $userId)
            ->orWhere('delala_id', $userId)
            ->latest()
            ->paginate(20);

        return response()->json(['chats' => ChatResource::collection($chats->items())]);
    }

    /** GET /api/v1/chats/{chat} */
    public function show(Request $request, Chat $chat): JsonResponse
    {
        $this->authorizeChatAccess($request, $chat);

        return response()->json(['chat' => new ChatResource($chat->load('buyer', 'delala', 'proposal'))]);
    }

    /** GET /api/v1/chats/{chat}/messages */
    public function messages(Request $request, Chat $chat): JsonResponse
    {
        $this->authorizeChatAccess($request, $chat);

        $messages = $chat->messages()->with('sender')->latest()->paginate(50);

        return response()->json([
            'messages' => ChatMessageResource::collection($messages->items()),
            'meta'     => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
            ],
        ]);
    }

    /** POST /api/v1/chats/{chat}/messages */
    public function sendMessage(SendMessageRequest $request, Chat $chat): JsonResponse
    {
        try {
            $message = $this->chatService->sendMessage(
                $chat,
                $request->user(),
                $request->input('body'),
                $request,
            );
        } catch (\Exception $e) {
            $statusCode = match ($e->getMessage()) {
                'PII_BLOCKED'        => 422,
                'CHAT_IS_READ_ONLY'  => 403,
                'NOT_CHAT_PARTICIPANT' => 403,
                default              => 422,
            };

            return response()->json([
                'error'   => $e->getMessage(),
                'message' => match ($e->getMessage()) {
                    'PII_BLOCKED' => 'Your message was blocked because it contains contact information. Share contact details only after escrow is funded.',
                    default       => 'Message could not be sent.',
                },
            ], $statusCode);
        }

        return response()->json([
            'message' => new ChatMessageResource($message->load('sender')),
        ], 201);
    }

    private function authorizeChatAccess(Request $request, Chat $chat): void
    {
        $userId = $request->user()->id;
        if ($userId !== $chat->buyer_id && $userId !== $chat->delala_id && ! $request->user()->isAdmin()) {
            abort(403, 'FORBIDDEN');
        }
    }
}
