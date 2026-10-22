<?php

use App\Filament\Resources\AdminApprovalRequests\AdminApprovalRequestResource;
use App\Filament\Resources\AdminApprovalRequests\Pages\ListAdminApprovalRequests;
use App\Models\AdminApprovalRequest;
use App\Models\Category;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\DelalaJob;
use App\Models\EscrowTransaction;
use App\Models\KycProfile;
use App\Models\LedgerAccount;
use App\Models\PayoutDestination;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\ProposalItemPriceHistory;
use App\Models\Rating;
use App\Models\User;
use App\Services\JsonSchemaValidator;
use App\States\Escrow\Disputed;
use App\States\Escrow\ForceRefunded;
use App\States\Escrow\ForceReleased;
use App\States\Escrow\Funded;
use App\States\Escrow\Released;
use App\States\Job\Canceled as JobCanceled;
use App\States\Kyc\Approved as KycApproved;
use App\States\Kyc\UnderReview as KycUnderReview;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('seeds real-world 2-level taxonomy with Draft-07 JSON schemas via TaxonomySeeder', function () {
    $this->seed(TaxonomySeeder::class);

    // 1. Verify Top-Level Parent Categories
    $realEstate = Category::where('slug', 'real-estate')->first();
    expect($realEstate)->not->toBeNull()
        ->and($realEstate->is_leaf)->toBeFalse()
        ->and($realEstate->parent_id)->toBeNull();

    $vehicles = Category::where('slug', 'vehicles')->first();
    expect($vehicles)->not->toBeNull()
        ->and($vehicles->is_leaf)->toBeFalse()
        ->and($vehicles->parent_id)->toBeNull();

    // 2. Verify Real Estate Leaf Categories
    $residentialRental = Category::where('slug', 'residential-rental')->first();
    expect($residentialRental)->not->toBeNull()
        ->and($residentialRental->is_leaf)->toBeTrue()
        ->and($residentialRental->parent_id)->toBe($realEstate->id)
        ->and($residentialRental->json_schema)->toHaveKeys(['$schema', 'properties', 'required'])
        ->and($residentialRental->json_schema['properties'])->toHaveKeys([
            'bedrooms', 'bathrooms', 'furnished', 'backup_generator', 'parking',
        ]);

    $commercialLease = Category::where('slug', 'commercial-lease')->first();
    expect($commercialLease)->not->toBeNull()
        ->and($commercialLease->is_leaf)->toBeTrue()
        ->and($commercialLease->parent_id)->toBe($realEstate->id)
        ->and($commercialLease->json_schema['properties'])->toHaveKeys([
            'square_meters', 'floor_number', 'elevator',
        ]);

    $residentialSale = Category::where('slug', 'residential-sale')->first();
    expect($residentialSale)->not->toBeNull()
        ->and($residentialSale->is_leaf)->toBeTrue()
        ->and($residentialSale->parent_id)->toBe($realEstate->id)
        ->and($residentialSale->json_schema['properties'])->toHaveKeys([
            'bedrooms', 'bathrooms', 'square_meters',
        ]);

    // 3. Verify Vehicles Leaf Categories
    $passengerCars = Category::where('slug', 'passenger-cars')->first();
    expect($passengerCars)->not->toBeNull()
        ->and($passengerCars->is_leaf)->toBeTrue()
        ->and($passengerCars->parent_id)->toBe($vehicles->id)
        ->and($passengerCars->json_schema['properties'])->toHaveKeys([
            'make', 'model', 'year', 'transmission', 'fuel_type', 'mileage',
        ]);

    $commercialVehicles = Category::where('slug', 'commercial-vehicles')->first();
    expect($commercialVehicles)->not->toBeNull()
        ->and($commercialVehicles->is_leaf)->toBeTrue()
        ->and($commercialVehicles->parent_id)->toBe($vehicles->id)
        ->and($commercialVehicles->json_schema['properties'])->toHaveKeys([
            'vehicle_type', 'payload_capacity_tons', 'year', 'transmission', 'fuel_type',
        ]);

    // 4. Test Draft-07 JSON Schema Validation against the seeded schemas
    $validator = app(JsonSchemaValidator::class);

    // 4a. Residential Rental
    $validRental = [
        'bedrooms' => 2,
        'bathrooms' => 1.5,
        'furnished' => true,
        'backup_generator' => true,
        'parking' => true,
    ];
    expect($validator->validate($residentialRental->json_schema, $validRental))->toBeEmpty();

    $invalidRental = [
        'bedrooms' => 'three',
        'bathrooms' => 2,
        'furnished' => true,
        'parking' => true,
    ];
    expect($validator->validate($residentialRental->json_schema, $invalidRental))->not->toBeEmpty();

    // 4b. Commercial Lease
    $validLease = [
        'square_meters' => 120.5,
        'floor_number' => 3,
        'elevator' => true,
    ];
    expect($validator->validate($commercialLease->json_schema, $validLease))->toBeEmpty();

    $invalidLease = [
        'square_meters' => -5,
        'floor_number' => 'third',
    ];
    expect($validator->validate($commercialLease->json_schema, $invalidLease))->not->toBeEmpty();

    // 4c. Residential Sale
    $validSale = [
        'bedrooms' => 4,
        'bathrooms' => 3,
        'square_meters' => 350.0,
        'property_type' => 'villa',
        'title_deed_verified' => true,
    ];
    expect($validator->validate($residentialSale->json_schema, $validSale))->toBeEmpty();

    $invalidSale = [
        'bedrooms' => 4,
        'bathrooms' => 3,
        'square_meters' => 350.0,
        'property_type' => 'castle', // invalid enum
    ];
    expect($validator->validate($residentialSale->json_schema, $invalidSale))->not->toBeEmpty();

    // 4d. Passenger Cars
    $validCar = [
        'make' => 'Toyota',
        'model' => 'Corolla',
        'year' => 2021,
        'transmission' => 'automatic',
        'fuel_type' => 'petrol',
        'mileage' => 35000,
    ];
    expect($validator->validate($passengerCars->json_schema, $validCar))->toBeEmpty();

    $invalidCar = [
        'make' => 'T', // minLength 2
        'model' => 'Corolla',
        'year' => 1960, // min 1970
        'transmission' => 'flying', // invalid enum
        'fuel_type' => 'solar', // invalid enum
        'mileage' => -100,
    ];
    expect($validator->validate($passengerCars->json_schema, $invalidCar))->not->toBeEmpty();

    // 4e. Commercial Vehicles
    $validCommVehicle = [
        'vehicle_type' => 'truck',
        'payload_capacity_tons' => 12.5,
        'year' => 2022,
        'transmission' => 'manual',
        'fuel_type' => 'diesel',
        'mileage' => 50000,
    ];
    expect($validator->validate($commercialVehicles->json_schema, $validCommVehicle))->toBeEmpty();

    $invalidCommVehicle = [
        'vehicle_type' => 'airplane', // invalid enum
        'payload_capacity_tons' => -1,
    ];
    expect($validator->validate($commercialVehicles->json_schema, $invalidCommVehicle))->not->toBeEmpty();
});

it('seeds all enterprise personas, system ledger accounts, and demo transactions cleanly and idempotently', function () {
    // 1. Run full DatabaseSeeder cleanly
    $this->seed(DatabaseSeeder::class);

    // Run again to prove 100% idempotency
    $this->seed(DatabaseSeeder::class);

    // 2. Verify Pre-Configured Personas & Passwords
    $seniorAdmin = User::where('email', 'senior@delala.et')->first();
    expect($seniorAdmin)->not->toBeNull()
        ->and($seniorAdmin->isAdmin())->toBeTrue()
        ->and($seniorAdmin->phone)->toBe('+251900000001')
        ->and(Hash::check('password123', $seniorAdmin->password))->toBeTrue();

    $juniorAdmin = User::where('email', 'junior@delala.et')->first();
    expect($juniorAdmin)->not->toBeNull()
        ->and($juniorAdmin->isAdmin())->toBeTrue()
        ->and($juniorAdmin->phone)->toBe('+251900000002')
        ->and(Hash::check('password123', $juniorAdmin->password))->toBeTrue();

    $verifiedDelala = User::where('email', 'delala.verified@delala.et')->first();
    expect($verifiedDelala)->not->toBeNull()
        ->and($verifiedDelala->isDelala())->toBeTrue()
        ->and($verifiedDelala->phone)->toBe('+251911223344')
        ->and(Hash::check('password123', $verifiedDelala->password))->toBeTrue();

    // Verify Delala Approved KYC
    $kycProfile = KycProfile::where('user_id', $verifiedDelala->id)->first();
    expect($kycProfile)->not->toBeNull()
        ->and($kycProfile->status)->toBeInstanceOf(KycApproved::class)
        ->and($kycProfile->reviewed_by)->toBe($seniorAdmin->id);

    // Verify Delala Default CBE Payout Destination
    $payoutDest = PayoutDestination::where('delala_id', $verifiedDelala->id)->where('is_default', true)->first();
    expect($payoutDest)->not->toBeNull()
        ->and($payoutDest->bank_code)->toBe('CBE')
        ->and($payoutDest->account_number)->toBe('1000123456789')
        ->and($payoutDest->verified_at)->not->toBeNull();

    $unverifiedDelala = User::where('email', 'delala.unverified@delala.et')->first();
    expect($unverifiedDelala)->not->toBeNull()
        ->and($unverifiedDelala->isDelala())->toBeTrue();
    $unverifiedKyc = KycProfile::where('user_id', $unverifiedDelala->id)->first();
    expect($unverifiedKyc)->not->toBeNull()
        ->and($unverifiedKyc->status)->toBeInstanceOf(KycUnderReview::class);

    $registeredBuyer = User::where('email', 'buyer@delala.et')->first();
    expect($registeredBuyer)->not->toBeNull()
        ->and($registeredBuyer->isBuyer())->toBeTrue()
        ->and($registeredBuyer->phone)->toBe('+251933445566')
        ->and(Hash::check('password123', $registeredBuyer->password))->toBeTrue();

    $guestBuyer = User::where('phone', '+251944556677')->first();
    expect($guestBuyer)->not->toBeNull()
        ->and($guestBuyer->isGuest())->toBeTrue()
        ->and($guestBuyer->email)->toBeNull();

    // 3. Verify System Ledger Accounts (Numeric & Aliases)
    $numericAccounts = ['1010', '1020', '2010', '2020', '2030', '4010'];
    foreach ($numericAccounts as $code) {
        expect(LedgerAccount::where('code', $code)->exists())->toBeTrue();
    }
    expect(LedgerAccount::where('code', 'delala_available_wallet_'.$verifiedDelala->id)->exists())->toBeTrue();

    // 4. Verify Live Jobs, Multi-Item Proposals, and Chats
    expect(DelalaJob::count())->toBeGreaterThanOrEqual(4);
    expect(Proposal::count())->toBeGreaterThanOrEqual(3);
    expect(ProposalItem::count())->toBeGreaterThanOrEqual(4);
    expect(ProposalItemPriceHistory::count())->toBeGreaterThanOrEqual(1);
    expect(Chat::count())->toBeGreaterThanOrEqual(1);
    expect(ChatMessage::count())->toBeGreaterThanOrEqual(4);

    // 5. Verify Active Funded Escrow and Completed Escrow
    expect(EscrowTransaction::whereState('status', Funded::class)->exists())->toBeTrue();
    expect(EscrowTransaction::whereState('status', Released::class)->exists())->toBeTrue();

    // 6. Verify Double-Blind Mutual Ratings
    $revealedRatings = Rating::where('is_revealed', true)->get();
    expect($revealedRatings->count())->toBeGreaterThanOrEqual(2);

    // 7. Verify High-Value Disputed Escrow with Maker-Checker AdminApprovalRequest
    $pendingApproval = AdminApprovalRequest::where('status', 'PENDING')->first();
    expect($pendingApproval)->not->toBeNull()
        ->and($pendingApproval->amount_santim)->toBeGreaterThan(1000000)
        ->and($pendingApproval->requested_by_id)->toBe($juniorAdmin->id)
        ->and($pendingApproval->action_type)->toBe('FORCE_RELEASE');

    // 8. Prove Nightly Ledger Reconciliation runs and is 100% balanced
    $this->artisan('delala:nightly-reconciliation')
        ->expectsOutputToContain('Nightly ledger reconciliation balanced.')
        ->assertSuccessful();
});

it('restricts Filament Admin Approval routes to authenticated administrators', function () {
    $this->seed(DatabaseSeeder::class);

    $buyer = User::where('email', 'buyer@delala.et')->first();
    $seniorAdmin = User::where('email', 'senior@delala.et')->first();

    // 1. Guest redirect or forbidden
    $guestResponse = $this->get('/admin/admin-approval-requests');
    expect($guestResponse->status())->toBeIn([302, 403]);

    // 2. Non-admin user forbidden
    $buyerResponse = $this->actingAs($buyer)->get('/admin/admin-approval-requests');
    expect($buyerResponse->status())->toBe(403);

    // 3. Admin user allowed
    $adminResponse = $this->actingAs($seniorAdmin)->get('/admin/admin-approval-requests');
    $adminResponse->assertOk();
    $adminResponse->assertSee('Maker-Checker Approvals');
});

it('renders the Filament Admin Approval resource list page and table components via Livewire', function () {
    $this->seed(DatabaseSeeder::class);

    $seniorAdmin = User::where('email', 'senior@delala.et')->first();
    $pendingApproval = AdminApprovalRequest::where('status', 'PENDING')->first();

    Livewire::actingAs($seniorAdmin)
        ->test(ListAdminApprovalRequests::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$pendingApproval]);
});

it('enforces Maker != Checker rule in Filament: prevents Maker from approving their own request and allows Checker to approve', function () {
    $this->seed(DatabaseSeeder::class);

    $juniorAdmin = User::where('email', 'junior@delala.et')->first();
    $seniorAdmin = User::where('email', 'senior@delala.et')->first();
    $pendingApproval = AdminApprovalRequest::where('status', 'PENDING')->first();

    expect($pendingApproval->requested_by_id)->toBe($juniorAdmin->id);

    // 1. Junior Admin (the MAKER) attempts to approve their own request via Filament Table Action
    Livewire::actingAs($juniorAdmin)
        ->test(ListAdminApprovalRequests::class)
        ->callTableAction('approve', $pendingApproval);

    // Policy violation: Status must remain PENDING
    expect($pendingApproval->fresh()->status)->toBe('PENDING')
        ->and($pendingApproval->fresh()->approved_by_id)->toBeNull();

    // 2. Senior Admin (the CHECKER) approves the request via Filament Table Action
    Livewire::actingAs($seniorAdmin)
        ->test(ListAdminApprovalRequests::class)
        ->callTableAction('approve', $pendingApproval);

    // Verification: Request approved, approver recorded, and escrow state transitioned to ForceReleased
    $updatedApproval = $pendingApproval->fresh();
    expect($updatedApproval->status)->toBe('APPROVED')
        ->and($updatedApproval->approved_by_id)->toBe($seniorAdmin->id)
        ->and($updatedApproval->approved_at)->not->toBeNull();

    $escrow = EscrowTransaction::find($pendingApproval->entity_id);
    expect($escrow->status)->toBeInstanceOf(ForceReleased::class)
        ->and($escrow->resolved_by)->toBe($seniorAdmin->id);
});

it('enforces Maker != Checker rule in Filament: prevents Maker from rejecting and allows Checker to reject with reason', function () {
    $this->seed(DatabaseSeeder::class);

    $juniorAdmin = User::where('email', 'junior@delala.et')->first();
    $seniorAdmin = User::where('email', 'senior@delala.et')->first();
    $pendingApproval = AdminApprovalRequest::where('status', 'PENDING')->first();

    // 1. Junior Admin (the MAKER) attempts to reject their own request
    Livewire::actingAs($juniorAdmin)
        ->test(ListAdminApprovalRequests::class)
        ->callTableAction('reject', $pendingApproval, [
            'rejection_reason' => 'Changed my mind.',
        ]);

    expect($pendingApproval->fresh()->status)->toBe('PENDING');

    // 2. Senior Admin (the CHECKER) rejects the request with a valid reason
    Livewire::actingAs($seniorAdmin)
        ->test(ListAdminApprovalRequests::class)
        ->callTableAction('reject', $pendingApproval, [
            'rejection_reason' => 'Documentation provided is missing authorized land agency seal.',
        ]);

    $rejectedApproval = $pendingApproval->fresh();
    expect($rejectedApproval->status)->toBe('REJECTED')
        ->and($rejectedApproval->approved_by_id)->toBe($seniorAdmin->id)
        ->and($rejectedApproval->rejection_reason)->toBe('Documentation provided is missing authorized land agency seal.');
});

it('enforces Maker != Checker rule in Filament: allows Checker to approve FORCE_REFUND and transitions escrow, ledger, and job', function () {
    $this->seed(DatabaseSeeder::class);

    $juniorAdmin = User::where('email', 'junior@delala.et')->first();
    $seniorAdmin = User::where('email', 'senior@delala.et')->first();
    $disputedEscrow = EscrowTransaction::whereState('status', Disputed::class)->first();

    // Junior Admin queues a high-value FORCE_REFUND request
    $refundApproval = AdminApprovalRequest::create([
        'action_type' => 'FORCE_REFUND',
        'entity_type' => EscrowTransaction::class,
        'entity_id' => $disputedEscrow->id,
        'amount_santim' => $disputedEscrow->commission_amount_santim,
        'requested_by_id' => $juniorAdmin->id,
        'status' => 'PENDING',
        'justification_note' => 'Seller failed verification inspection. Refunding buyer deposit in full.',
    ]);

    Http::fake([
        'https://api.chapa.co/v1/transaction/refund' => Http::response(['status' => 'success'], 200),
    ]);

    // 1. Junior Admin (Maker) cannot approve
    Livewire::actingAs($juniorAdmin)
        ->test(ListAdminApprovalRequests::class)
        ->callTableAction('approve', $refundApproval);

    expect($refundApproval->fresh()->status)->toBe('PENDING');

    // 2. Senior Admin (Checker) approves
    Livewire::actingAs($seniorAdmin)
        ->test(ListAdminApprovalRequests::class)
        ->callTableAction('approve', $refundApproval);

    $updatedApproval = $refundApproval->fresh();
    expect($updatedApproval->status)->toBe('APPROVED')
        ->and($updatedApproval->approved_by_id)->toBe($seniorAdmin->id)
        ->and($updatedApproval->approved_at)->not->toBeNull();

    $updatedEscrow = $disputedEscrow->fresh();
    expect($updatedEscrow->status)->toBeInstanceOf(ForceRefunded::class)
        ->and($updatedEscrow->resolved_by)->toBe($seniorAdmin->id)
        ->and($updatedEscrow->job->fresh()->status)->toBeInstanceOf(JobCanceled::class);
});

it('hides approve and reject actions on an already resolved approval request in Filament', function () {
    $this->seed(DatabaseSeeder::class);

    $seniorAdmin = User::where('email', 'senior@delala.et')->first();
    $pendingApproval = AdminApprovalRequest::where('status', 'PENDING')->first();

    // 1. Approve once via Filament
    Livewire::actingAs($seniorAdmin)
        ->test(ListAdminApprovalRequests::class)
        ->callTableAction('approve', $pendingApproval)
        ->assertTableActionHidden('approve', $pendingApproval)
        ->assertTableActionHidden('reject', $pendingApproval);

    expect($pendingApproval->fresh()->status)->toBe('APPROVED');
});

it('enforces that AdminApprovalRequest cannot be created or deleted via Filament resource policies', function () {
    $pendingApproval = new AdminApprovalRequest(['status' => 'PENDING']);

    expect(AdminApprovalRequestResource::canCreate())->toBeFalse()
        ->and(AdminApprovalRequestResource::canDelete($pendingApproval))->toBeFalse()
        ->and(AdminApprovalRequestResource::canDeleteAny())->toBeFalse();
});
