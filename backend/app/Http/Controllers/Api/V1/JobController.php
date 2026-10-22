<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Job\StoreJobRequest;
use App\Http\Resources\JobResource;
use App\Models\DelalaJob;
use App\Services\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Job CRUD and lifecycle controller.
 * SRS FR-3.1 – FR-3.5.
 */
class JobController extends Controller
{
    public function __construct(private readonly JobService $jobService) {}

    /** POST /api/v1/jobs */
    public function store(StoreJobRequest $request): JsonResponse
    {
        $job = $this->jobService->create($request->user(), $request->validated(), $request);

        return response()->json(['job' => new JobResource($job->load('category', 'buyer'))], 201);
    }

    /** GET /api/v1/jobs/{job} */
    public function show(Request $request, DelalaJob $job): JsonResponse
    {
        $this->authorizeJobAccess($request, $job);

        return response()->json(['job' => new JobResource($job->load('category', 'buyer', 'proposals'))]);
    }

    /** PUT /api/v1/jobs/{job} */
    public function update(StoreJobRequest $request, DelalaJob $job): JsonResponse
    {
        if ($job->buyer_id !== $request->user()->id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        if (! $job->isDraft()) {
            return response()->json(['error' => 'JOB_NOT_IN_DRAFT', 'message' => 'Only draft jobs can be edited.'], 422);
        }

        $job->update($request->validated());

        return response()->json(['job' => new JobResource($job->fresh()->load('category', 'buyer'))]);
    }

    /** POST /api/v1/jobs/{job}/publish */
    public function publish(Request $request, DelalaJob $job): JsonResponse
    {
        if ($job->buyer_id !== $request->user()->id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        try {
            $job = $this->jobService->publish($job, $request);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['job' => new JobResource($job->load('category', 'buyer'))]);
    }

    /** POST /api/v1/jobs/{job}/cancel */
    public function cancel(Request $request, DelalaJob $job): JsonResponse
    {
        if ($job->buyer_id !== $request->user()->id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        $request->validate(['reason' => ['sometimes', 'string', 'max:500']]);

        try {
            $job = $this->jobService->cancel($job, $request->input('reason', ''), $request);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['job' => new JobResource($job->load('category', 'buyer'))]);
    }

    /** POST /api/v1/jobs/{job}/clone */
    public function clone(Request $request, DelalaJob $job): JsonResponse
    {
        if ($job->buyer_id !== $request->user()->id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        $cloned = $this->jobService->clone($job, $request->user());

        return response()->json(['job' => new JobResource($cloned->load('category', 'buyer'))], 201);
    }

    /** GET /api/v1/jobs/feed — Delala feed */
    public function feed(Request $request): JsonResponse
    {
        $filters = $request->only(['category_id', 'region', 'subcity', 'budget_max']);
        $jobs    = $this->jobService->feedQuery($filters)->paginate(20);

        return response()->json([
            'jobs'  => JobResource::collection($jobs->items()),
            'meta'  => [
                'current_page' => $jobs->currentPage(),
                'last_page'    => $jobs->lastPage(),
                'total'        => $jobs->total(),
            ],
        ]);
    }

    /** GET /api/v1/jobs/my — Buyer's own jobs */
    public function myJobs(Request $request): JsonResponse
    {
        $jobs = DelalaJob::with(['category'])
            ->where('buyer_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'jobs' => JobResource::collection($jobs->items()),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page'    => $jobs->lastPage(),
                'total'        => $jobs->total(),
            ],
        ]);
    }

    private function authorizeJobAccess(Request $request, DelalaJob $job): void
    {
        // Open jobs are visible to all auth'd users
        // Draft/Canceled jobs visible only to the buyer
    }
}
