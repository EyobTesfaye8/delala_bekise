<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DelalaJob;
use App\States\Job\Negotiating;
use App\States\Job\Open;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * Get global job feed (cursor paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $query = DelalaJob::query()
            ->with(['category'])
            ->whereIn('status', [Open::$name, Negotiating::$name]); // feed exposes OPEN and NEGOTIATING

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('location')) {
            $query->where('location', 'ilike', '%'.$request->location.'%');
        }

        if ($request->has('budget_min')) {
            $query->where('budget_max', '>=', $request->budget_min);
        }

        // FR-3.2 Visibility Constraints: Delalas cannot see jobs they have proposed on
        if ($request->user() && $request->user()->isDelala()) {
            $query->whereDoesntHave('proposals', function ($q) use ($request) {
                $q->where('delala_id', $request->user()->id);
            });
        }

        $jobs = $query->orderByDesc('created_at')->cursorPaginate(15);

        return response()->json($jobs);
    }

    /**
     * Store a new job.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0|gte:budget_min',
            'attributes' => 'nullable|array',
        ]);

        // Category Schema Validation
        // If the category has a JSON schema for attributes, validate $validated['attributes'] against it.
        // E.g., using a custom Laravel rule or simple array checking here.
        if (! empty($category->attributes_schema) && ! empty($validated['attributes'])) {
            // For now, simple presence check based on keys
            $schemaKeys = array_keys($category->attributes_schema);
            $providedKeys = array_keys($validated['attributes']);
            $missingKeys = array_diff($schemaKeys, $providedKeys);
            if (! empty($missingKeys)) {
                return response()->json(['message' => 'Missing attributes: '.implode(', ', $missingKeys)], 422);
            }
        }
        // This is where we would use JSON schema validator against the category's attributes_schema

        $validated['buyer_id'] = $request->user()->id;

        $job = DelalaJob::create($validated);
        $job->status->transitionTo(Open::class);

        return response()->json([
            'message' => 'Job created successfully',
            'job' => $job->load('category'),
        ], 201);
    }

    /**
     * Show job details.
     */
    public function show(DelalaJob $job): JsonResponse
    {
        return response()->json($job->load(['category', 'buyer:id,full_name,type,created_at']));
    }
}
