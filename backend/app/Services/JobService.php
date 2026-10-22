<?php

namespace App\Services;

use App\Models\Category;
use App\Models\DelalaJob;
use App\Models\User;
use App\Models\States\Draft;
use App\Models\States\Open;
use App\Models\States\Canceled;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Job CRUD and lifecycle service.
 * SRS FR-3.1, FR-3.2, FR-3.3.
 */
class JobService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * Create a job in DRAFT state.
     * SRS FR-3.1.
     */
    public function create(User $buyer, array $data, Request $request): DelalaJob
    {
        return DB::transaction(function () use ($buyer, $data, $request) {
            $job = DelalaJob::create([
                'buyer_id'          => $buyer->id,
                'category_id'       => $data['category_id'],
                'title'             => $data['title'],
                'description'       => $data['description'],
                'location'          => $data['location'] ?? null,
                'location_region'   => $data['location_region'] ?? null,
                'location_subcity'  => $data['location_subcity'] ?? null,
                'budget_min'        => $data['budget_min'] ?? null,
                'budget_max'        => $data['budget_max'] ?? null,
                'attributes'        => $data['attributes'] ?? null,
            ]);

            $this->auditLog->log('job_created', DelalaJob::class, $job->id, [], ['title' => $job->title], $request);

            return $job;
        });
    }

    /**
     * Publish a DRAFT job → OPEN state.
     * SRS FR-3.2.
     */
    public function publish(DelalaJob $job, Request $request): DelalaJob
    {
        if (! $job->isDraft()) {
            throw new \Exception('JOB_NOT_IN_DRAFT', 422);
        }

        DB::transaction(function () use ($job, $request) {
            $job->status->transitionTo(Open::class);
            $this->auditLog->log('job_published', DelalaJob::class, $job->id, ['status' => 'draft'], ['status' => 'open'], $request);
        });

        return $job->fresh();
    }

    /**
     * Cancel an OPEN or NEGOTIATING job.
     * SRS FR-3.3.
     */
    public function cancel(DelalaJob $job, string $reason, Request $request): DelalaJob
    {
        if (! $job->isOpen() && ! $job->isNegotiating()) {
            throw new \Exception('JOB_CANNOT_BE_CANCELED', 422);
        }

        DB::transaction(function () use ($job, $reason, $request) {
            $job->update(['canceled_reason' => $reason]);
            $job->status->transitionTo(Canceled::class);
            $this->auditLog->log('job_canceled', DelalaJob::class, $job->id, [], ['reason' => $reason], $request);
        });

        return $job->fresh();
    }

    /**
     * Clone a completed/canceled job back to DRAFT.
     * SRS FR-3.4.
     */
    public function clone(DelalaJob $job, User $buyer): DelalaJob
    {
        return DB::transaction(function () use ($job, $buyer) {
            return DelalaJob::create([
                'buyer_id'         => $buyer->id,
                'category_id'      => $job->category_id,
                'title'            => $job->title.' (copy)',
                'description'      => $job->description,
                'location'         => $job->location,
                'location_region'  => $job->location_region,
                'location_subcity' => $job->location_subcity,
                'budget_min'       => $job->budget_min,
                'budget_max'       => $job->budget_max,
                'attributes'       => $job->attributes,
                // status defaults to Draft via state machine default
            ]);
        });
    }

    /**
     * Build the Delala feed query with geo/category filters.
     * SRS FR-3.5: Approved Delalas only.
     *
     * @param array{category_id?: int, region?: string, subcity?: string, budget_max?: int} $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function feedQuery(array $filters)
    {
        $query = DelalaJob::with(['buyer', 'category'])
            ->where('status', 'open')
            ->latest('created_at');

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['region'])) {
            $query->where('location_region', $filters['region']);
        }

        if (isset($filters['subcity'])) {
            $query->where('location_subcity', $filters['subcity']);
        }

        if (isset($filters['budget_max'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('budget_max')
                  ->orWhere('budget_max', '<=', $filters['budget_max']);
            });
        }

        return $query;
    }
}
