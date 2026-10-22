<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rating\StoreRatingRequest;
use App\Http\Resources\RatingResource;
use App\Models\DelalaJob;
use App\Models\Rating;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rating controller — double-blind mutual rating.
 * SRS FR-6.1, FR-6.2.
 */
class RatingController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * POST /api/v1/ratings/jobs/{job}
     * SRS FR-6.1: buyer rates the Delala after job COMPLETED.
     * Double-blind: rating not revealed until both submit or 14 days pass.
     */
    public function rate(StoreRatingRequest $request, DelalaJob $job): JsonResponse
    {
        $user = $request->user();

        if (! $job->isCompleted()) {
            return response()->json([
                'error'   => 'JOB_NOT_COMPLETED',
                'message' => 'Ratings can only be submitted for completed jobs.',
            ], 422);
        }

        // Only the buyer of the job may rate
        if ($user->id !== $job->buyer_id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        // Find the accepted Delala from the contracted proposal
        $delalaId = $job->proposals()
            ->where('status', 'proposal_accepted')
            ->value('delala_id');

        if (! $delalaId) {
            return response()->json(['error' => 'NO_CONTRACTED_DELALA'], 422);
        }

        // Prevent duplicate rating
        $existing = Rating::where('job_id', $job->id)->where('rater_id', $user->id)->exists();
        if ($existing) {
            return response()->json(['error' => 'ALREADY_RATED'], 409);
        }

        $rating = DB::transaction(function () use ($user, $job, $delalaId, $request) {
            $rating = Rating::create([
                'job_id'      => $job->id,
                'rater_id'    => $user->id,
                'delala_id'   => $delalaId,
                'stars'       => $request->input('stars'),
                'review_text' => $request->input('review_text'),
                // Blind until both submit (SRS FR-6.1)
                'revealed_at' => null,
            ]);

            // Check if both parties have now rated — reveal immediately (SRS FR-6.1)
            $bothRated = Rating::where('job_id', $job->id)->count() >= 2;
            if ($bothRated) {
                Rating::where('job_id', $job->id)->update(['revealed_at' => now()]);
            }

            $this->auditLog->log('rating_submitted', Rating::class, $rating->id, [], [
                'stars'     => $rating->stars,
                'job_id'    => $job->id,
                'delala_id' => $delalaId,
            ], $request);

            return $rating;
        });

        return response()->json([
            'message' => 'Rating submitted. It will be revealed once both parties have rated, or after 14 days.',
            'rating'  => new RatingResource($rating),
        ], 201);
    }

    /**
     * GET /api/v1/ratings/delala/{user}
     * SRS FR-6.2: Trust profile — revealed ratings and average score.
     */
    public function delalaTrustProfile(Request $request, User $user): JsonResponse
    {
        $ratings = Rating::where('delala_id', $user->id)
            ->whereNotNull('revealed_at')
            ->latest('revealed_at')
            ->paginate(20);

        $stats = Rating::where('delala_id', $user->id)
            ->whereNotNull('revealed_at')
            ->selectRaw('COUNT(*) as total, AVG(stars) as average, COUNT(CASE WHEN stars >= 4 THEN 1 END) as positive')
            ->first();

        return response()->json([
            'delala_id'      => $user->id,
            'full_name'      => $user->full_name,
            'rating_summary' => [
                'total'   => (int) ($stats->total ?? 0),
                'average' => round((float) ($stats->average ?? 0), 2),
                'positive_percent' => $stats->total > 0
                    ? round(($stats->positive / $stats->total) * 100, 1)
                    : 0,
            ],
            'ratings' => RatingResource::collection($ratings->items()),
            'meta'    => [
                'current_page' => $ratings->currentPage(),
                'last_page'    => $ratings->lastPage(),
            ],
        ]);
    }
}
