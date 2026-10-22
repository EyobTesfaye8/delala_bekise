<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DelalaJob;
use App\Services\JsonSchemaValidator;
use App\States\Job\Negotiating;
use App\States\Job\Open;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    public function __construct(
        private JsonSchemaValidator $schemaValidator
    ) {}

    /**
     * Get global job feed (cursor paginated) (FR-3.3 & FR-3.4).
     */
    public function index(Request $request): JsonResponse
    {
        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = DelalaJob::query()
            ->with(['category:id,name,slug,parent_id'])
            ->whereIn('status', [Open::$name, Negotiating::$name]); // Feed exposes OPEN and NEGOTIATING

        if ($request->filled('q')) {
            $searchTerm = '%'.$request->q.'%';
            $query->where(function ($q) use ($searchTerm, $likeOperator) {
                $q->where('title', $likeOperator, $searchTerm)
                    ->orWhere('description', $likeOperator, $searchTerm);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('location_region')) {
            $query->where('location_region', $likeOperator, '%'.$request->location_region.'%');
        }

        if ($request->filled('location_subcity')) {
            $query->where('location_subcity', $likeOperator, '%'.$request->location_subcity.'%');
        }

        if ($request->filled('location')) {
            $query->where('location', $likeOperator, '%'.$request->location.'%');
        }

        $minBudget = $request->input('budget_min_santim', $request->input('min_budget'));
        if ($minBudget !== null) {
            $query->where(function ($q) use ($minBudget) {
                $q->where('budget_max_santim', '>=', $minBudget)
                    ->orWhere('budget_max', '>=', $minBudget / 100);
            });
        }

        $maxBudget = $request->input('budget_max_santim', $request->input('max_budget'));
        if ($maxBudget !== null) {
            $query->where(function ($q) use ($maxBudget) {
                $q->where('budget_min_santim', '<=', $maxBudget)
                    ->orWhere('budget_min', '<=', $maxBudget / 100);
            });
        }

        // FR-3.4 Visibility Constraints: Delalas cannot see jobs they have already proposed on
        if ($request->user() && $request->user()->isDelala()) {
            $query->whereDoesntHave('proposals', function ($q) use ($request) {
                $q->where('delala_id', $request->user()->id);
            });
        }

        $jobs = $query->orderByDesc('created_at')->cursorPaginate(15);

        return response()->json($jobs);
    }

    /**
     * Store a new job (FR-3.1, FR-3.2 & Trade-off 1).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // SRS §11 Trade-off 1: Guest Buyers capped at 2 active OPEN jobs simultaneously
        if ($user->isGuest()) {
            $activeJobsCount = DelalaJob::where('buyer_id', $user->id)
                ->where('status', Open::$name)
                ->count();

            if ($activeJobsCount >= 2) {
                return response()->json([
                    'message' => 'Guest buyers may only have up to 2 active open jobs. Please upgrade your account to post more.',
                    'error_code' => 'GUEST_JOB_LIMIT_REACHED',
                ], 403);
            }
        }

        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_region' => ['nullable', 'string', 'max:100'],
            'location_subcity' => ['nullable', 'string', 'max:100'],
            'budget_min_santim' => ['nullable', 'integer', 'min:0'],
            'budget_max_santim' => ['nullable', 'integer', 'min:0', 'gte:budget_min_santim'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'gte:budget_min'],
            'attributes' => ['nullable', 'array'],
        ]);

        $category = Category::findOrFail($validated['category_id']);

        // Only leaf categories accept jobs
        if ($category->is_leaf === false) {
            return response()->json([
                'message' => 'Jobs must be posted under a subcategory (leaf category).',
            ], 422);
        }

        // FR-3.2 Write-Time JSON Schema Validation
        $schema = $category->json_schema ?? $category->attributes_schema;
        if (! empty($schema) && is_array($schema)) {
            $attributePayload = $validated['attributes'] ?? [];
            $schemaErrors = $this->schemaValidator->validate($schema, $attributePayload);

            if (! empty($schemaErrors)) {
                return response()->json([
                    'message' => 'The provided attributes do not conform to the category schema.',
                    'errors' => $schemaErrors,
                ], 422);
            }
        }

        // Sync Santim amounts if only decimal was provided
        if (isset($validated['budget_min']) && ! isset($validated['budget_min_santim'])) {
            $validated['budget_min_santim'] = (int) ($validated['budget_min'] * 100);
        }
        if (isset($validated['budget_max']) && ! isset($validated['budget_max_santim'])) {
            $validated['budget_max_santim'] = (int) ($validated['budget_max'] * 100);
        }

        $validated['buyer_id'] = $user->id;

        $job = DelalaJob::create($validated);
        $job->status->transitionTo(Open::class);

        return response()->json([
            'message' => 'Job created successfully',
            'job' => $job->load('category:id,name,slug,parent_id'),
        ], 201);
    }

    /**
     * Show job details.
     */
    public function show(DelalaJob $job): JsonResponse
    {
        return response()->json($job->load([
            'category:id,name,slug,parent_id',
            'buyer:id,full_name,type,created_at',
        ]));
    }
}
