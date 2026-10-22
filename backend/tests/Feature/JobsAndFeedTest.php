<?php

use App\Models\Category;
use App\Models\DelalaJob;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->leafCategory = Category::factory()->create([
        'name' => 'Apartments for Rent',
        'is_leaf' => true,
        'json_schema' => [
            'type' => 'object',
            'required' => ['bedrooms'],
            'properties' => [
                'bedrooms' => ['type' => 'integer', 'minimum' => 1],
                'furnished' => ['type' => 'boolean'],
            ],
        ],
    ]);

    $this->parentCategory = Category::factory()->create([
        'name' => 'Real Estate',
        'is_leaf' => false,
    ]);
});

it('allows guests and authenticated users to list open jobs with cursor pagination', function () {
    DelalaJob::factory()->count(15)->create([
        'category_id' => $this->leafCategory->id,
        'status' => 'open',
    ]);

    $response = $this->getJson('/api/v1/jobs');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'next_cursor',
            'prev_cursor',
            'per_page',
        ]);

    expect(count($response->json('data')))->toBeLessThanOrEqual(15);
});

it('filters jobs by category, budget range, and search keyword', function () {
    $job1 = DelalaJob::factory()->create([
        'category_id' => $this->leafCategory->id,
        'title' => 'Luxury 3 Bedroom Apartment in Bole',
        'budget_min_santim' => 1000000,
        'budget_max_santim' => 2000000,
        'status' => 'open',
    ]);

    $job2 = DelalaJob::factory()->create([
        'category_id' => $this->leafCategory->id,
        'title' => 'Studio in Piassa',
        'budget_min_santim' => 300000,
        'budget_max_santim' => 500000,
        'status' => 'open',
    ]);

    $searchResponse = $this->getJson('/api/v1/jobs?q=Bole');
    $searchResponse->assertOk();
    $titles = collect($searchResponse->json('data'))->pluck('title');
    expect($titles)->toContain('Luxury 3 Bedroom Apartment in Bole')
        ->not->toContain('Studio in Piassa');

    $budgetResponse = $this->getJson('/api/v1/jobs?min_budget=800000');
    $budgetResponse->assertOk();
    $budgetTitles = collect($budgetResponse->json('data'))->pluck('title');
    expect($budgetTitles)->toContain('Luxury 3 Bedroom Apartment in Bole')
        ->not->toContain('Studio in Piassa');
});

it('excludes jobs from feed if the authenticated delala has already submitted a proposal (FR-3.2)', function () {
    $delala = User::factory()->create(['type' => 'delala']);
    Sanctum::actingAs($delala);

    $jobWithProposal = DelalaJob::factory()->create([
        'category_id' => $this->leafCategory->id,
        'status' => 'open',
    ]);

    $jobWithoutProposal = DelalaJob::factory()->create([
        'category_id' => $this->leafCategory->id,
        'status' => 'open',
    ]);

    Proposal::factory()->create([
        'job_id' => $jobWithProposal->id,
        'delala_id' => $delala->id,
        'status' => 'pending',
    ]);

    $response = $this->getJson('/api/v1/jobs');
    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->not->toContain($jobWithProposal->id)
        ->toContain($jobWithoutProposal->id);
});

it('rejects posting jobs to non-leaf categories', function () {
    $buyer = User::factory()->create(['type' => 'buyer']);
    Sanctum::actingAs($buyer);

    $response = $this->postJson('/api/v1/jobs', [
        'category_id' => $this->parentCategory->id,
        'title' => 'Commercial Property Needed',
        'budget_min_santim' => 500000,
        'budget_max_santim' => 1000000,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Jobs must be posted under a subcategory (leaf category).',
        ]);
});

it('validates attributes against category json schema on job post (FR-3.2)', function () {
    $buyer = User::factory()->create(['type' => 'buyer']);
    Sanctum::actingAs($buyer);

    // Missing required field 'bedrooms'
    $responseInvalid = $this->postJson('/api/v1/jobs', [
        'category_id' => $this->leafCategory->id,
        'title' => 'Need 2 Bed in Bole',
        'attributes' => [
            'furnished' => true,
        ],
    ]);

    $responseInvalid->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);

    // Valid attributes matching schema
    $responseValid = $this->postJson('/api/v1/jobs', [
        'category_id' => $this->leafCategory->id,
        'title' => 'Need 2 Bed in Bole',
        'budget_min_santim' => 500000,
        'budget_max_santim' => 1000000,
        'attributes' => [
            'bedrooms' => 2,
            'furnished' => true,
        ],
    ]);

    $responseValid->assertCreated()
        ->assertJsonPath('job.title', 'Need 2 Bed in Bole');
});

it('limits guest buyers to at most 2 active open jobs (SRS Trade-off 1)', function () {
    $guestBuyer = User::factory()->create(['type' => 'guest']);
    Sanctum::actingAs($guestBuyer);

    DelalaJob::factory()->count(2)->create([
        'buyer_id' => $guestBuyer->id,
        'category_id' => $this->leafCategory->id,
        'status' => 'open',
    ]);

    $response = $this->postJson('/api/v1/jobs', [
        'category_id' => $this->leafCategory->id,
        'title' => 'Third Job Attempt',
        'attributes' => ['bedrooms' => 1],
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'error_code' => 'GUEST_JOB_LIMIT_REACHED',
        ]);
});
