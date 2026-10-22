<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DelalaJob;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    /**
     * Submit Double-Blind Mutual Rating (SRS §6.6 FR-6.1).
     */
    public function store(Request $request, DelalaJob $job): JsonResponse
    {
        $user = $request->user();

        if ($job->status->getValue() !== 'completed') {
            return response()->json([
                'message' => 'Ratings can only be submitted after the job is completed.',
            ], 422);
        }

        // 14-day mutual rating deadline check
        if ($job->updated_at->lt(now()->subDays(14))) {
            return response()->json([
                'message' => 'The 14-day rating window for this completed job has expired.',
                'error_code' => 'RATING_WINDOW_EXPIRED',
            ], 422);
        }

        $escrow = $job->escrows()->latest()->first();
        if (! $escrow) {
            return response()->json(['message' => 'No escrow transaction found for this job.'], 404);
        }

        $buyerId = $job->buyer_id;
        $delalaId = $escrow->delala_id;

        // Verify caller is a participant
        if ($user->id !== $buyerId && $user->id !== $delalaId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rateeId = ($user->id === $buyerId) ? $delalaId : $buyerId;

        // Prevent duplicate rating from same participant
        $existing = Rating::where('escrow_transaction_id', $escrow->id)
            ->where('rater_id', $user->id)
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'You have already submitted a rating for this transaction.',
                'error_code' => 'DUPLICATE_RATING',
            ], 422);
        }

        $validated = $request->validate([
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = DB::transaction(function () use ($escrow, $buyerId, $delalaId, $user, $rateeId, $validated) {
            $rating = Rating::create([
                'escrow_transaction_id' => $escrow->id,
                'buyer_id' => $buyerId,
                'delala_id' => $delalaId,
                'rater_id' => $user->id,
                'ratee_id' => $rateeId,
                'stars' => $validated['stars'],
                'review' => $validated['review'] ?? null,
                'is_revealed' => false,
            ]);

            // Check if both parties have now submitted
            $otherRating = Rating::where('escrow_transaction_id', $escrow->id)
                ->where('rater_id', '!=', $user->id)
                ->first();

            $bothSubmitted = $otherRating !== null;

            if ($bothSubmitted) {
                // Reveal both reviews simultaneously (Double-blind reveal)
                Rating::where('escrow_transaction_id', $escrow->id)
                    ->update(['is_revealed' => true, 'revealed_at' => now()]);
            }

            return [
                'rating' => $rating->fresh(),
                'both_submitted' => $bothSubmitted,
            ];
        });

        return response()->json([
            'message' => $result['both_submitted']
                ? 'Rating submitted. Both parties have submitted, reviews are now visible!'
                : 'Rating submitted. Reviews will remain hidden until the other party submits or 14 days elapse.',
            'both_submitted' => $result['both_submitted'],
            'is_revealed' => $result['both_submitted'],
            'rating' => $result['rating'],
        ], 201);
    }

    /**
     * Get Delala Public Trust Profile (SRS §6.6 FR-6.2).
     */
    public function getDelalaProfile(User $delala): JsonResponse
    {
        if (! $delala->isDelala()) {
            return response()->json(['message' => 'Delala not found.'], 404);
        }

        $completedDealsCount = DelalaJob::whereHas('proposals', function ($q) use ($delala) {
            $q->where('delala_id', $delala->id)->where('status', 'accepted');
        })->where('status', 'completed')->count();

        $reviews = Rating::with('rater:id,full_name')
            ->where('delala_id', $delala->id)
            ->where('ratee_id', $delala->id)
            ->where('is_revealed', true)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $avgStars = $delala->averageRating();
        $totalReviews = Rating::where('delala_id', $delala->id)
            ->where('ratee_id', $delala->id)
            ->where('is_revealed', true)
            ->count();

        return response()->json([
            'id' => $delala->id,
            'full_name' => $delala->full_name,
            'is_verified' => $delala->kycProfile && $delala->kycProfile->status->getValue() === 'approved',
            'member_since' => $delala->created_at->toIso8601String(),
            'completed_deals_count' => $completedDealsCount,
            'average_rating' => $avgStars,
            'total_reviews_count' => $totalReviews,
            'recent_reviews' => $reviews,
        ]);
    }

    /**
     * Update device FCM push token for authenticated user.
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:500'],
        ]);

        $request->user()->update(['fcm_token' => $validated['fcm_token']]);

        return response()->json(['message' => 'FCM device token registered successfully.']);
    }
}
