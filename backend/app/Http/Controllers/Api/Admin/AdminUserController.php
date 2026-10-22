<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\States\Proposal\Withdrawn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    /**
     * User Suspension Kill-Switch (SRS AR-4).
     * Revokes Sanctum tokens, marks user suspended, and auto-withdraws pending proposals.
     */
    public function suspend(Request $request, User $user): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->isAdmin()) {
            return response()->json(['message' => 'Cannot suspend an administrator account.'], 403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($user) {
            // 1. Revoke all Sanctum API tokens immediately
            $user->tokens()->delete();

            // 2. Update user status
            $user->update(['status' => 'suspended']);

            // 3. Auto-withdraw any pending proposals
            $user->proposals()
                ->where('status', 'pending')
                ->get()
                ->each(function ($proposal) {
                    if ($proposal->status->canTransitionTo(Withdrawn::class)) {
                        $proposal->status->transitionTo(Withdrawn::class);
                    }
                });
        });

        return response()->json([
            'message' => "User #{$user->id} has been suspended, active sessions terminated, and pending proposals withdrawn.",
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Reactivate a suspended user account.
     */
    public function reactivate(Request $request, User $user): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user->update(['status' => 'active', 'circumvention_strike_count' => 0]);

        return response()->json([
            'message' => "User #{$user->id} has been reactivated.",
            'user' => $user->fresh(),
        ]);
    }
}
