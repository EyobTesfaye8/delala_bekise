<?php

use App\Jobs\GarbageCollectInactiveGuestsJob;
use App\Models\Category;
use App\Models\DelalaJob;
use App\Models\EscrowTransaction;
use App\Models\OtpCode;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use App\Notifications\VerifyEmailMagicLink;
use App\States\Escrow\Funded;
use App\States\Job\Canceled;
use App\States\Job\Completed;
use App\States\Job\Contracted;
use App\States\Job\Open;
use App\States\ProposalItem\AcceptedForEscrow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('purges guest accounts inactive for 5+ days with no ongoing deals', function () {
    // 1. Inactive guest (6 days old) with NO jobs -> should be purged
    $inactiveGuest = User::factory()->create([
        'type' => 'guest',
        'created_at' => now()->subDays(6),
        'updated_at' => now()->subDays(6),
    ]);

    // 2. Inactive guest (7 days old) with ONLY completed/canceled jobs -> should be purged
    $guestWithFinishedDeals = User::factory()->create([
        'type' => 'guest',
        'created_at' => now()->subDays(7),
        'updated_at' => now()->subDays(7),
    ]);
    $category = Category::create([
        'name' => 'Test Tech',
        'slug' => 'test-tech-'.Str::random(6),
        'is_active' => true,
    ]);
    $job1 = DelalaJob::create([
        'buyer_id' => $guestWithFinishedDeals->id,
        'category_id' => $category->id,
        'title' => 'Completed job',
        'status' => Completed::$name,
    ]);
    DB::table('delala_jobs')->where('id', $job1->id)->update([
        'created_at' => now()->subDays(7),
        'updated_at' => now()->subDays(7),
    ]);

    $job2 = DelalaJob::create([
        'buyer_id' => $guestWithFinishedDeals->id,
        'category_id' => $category->id,
        'title' => 'Canceled job',
        'status' => Canceled::$name,
    ]);
    DB::table('delala_jobs')->where('id', $job2->id)->update([
        'created_at' => now()->subDays(7),
        'updated_at' => now()->subDays(7),
    ]);

    // 3. Recent guest (2 days old) -> should NOT be purged
    $recentGuest = User::factory()->create([
        'type' => 'guest',
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    // 4. Inactive guest (6 days old) with an ONGOING OPEN job -> should NOT be purged
    $guestWithOpenJob = User::factory()->create([
        'type' => 'guest',
        'created_at' => now()->subDays(6),
        'updated_at' => now()->subDays(6),
    ]);
    DelalaJob::create([
        'buyer_id' => $guestWithOpenJob->id,
        'category_id' => $category->id,
        'title' => 'Active open job',
        'status' => Open::$name,
        'created_at' => now()->subDays(6),
        'updated_at' => now()->subDays(6),
    ]);

    // 5. Inactive registered buyer (6 days old) -> should NOT be purged (guests only)
    $inactiveBuyer = User::factory()->create([
        'type' => 'buyer',
        'created_at' => now()->subDays(6),
        'updated_at' => now()->subDays(6),
    ]);

    $this->artisan('delala:purge-inactive-guests')
        ->assertSuccessful();

    $this->assertDatabaseMissing('users', ['id' => $inactiveGuest->id]);
    $this->assertDatabaseMissing('users', ['id' => $guestWithFinishedDeals->id]);
    $this->assertDatabaseHas('users', ['id' => $recentGuest->id]);
    $this->assertDatabaseHas('users', ['id' => $guestWithOpenJob->id]);
    $this->assertDatabaseHas('users', ['id' => $inactiveBuyer->id]);
});

it('does not purge inactive guest users with active funded escrow transactions', function () {
    $category = Category::create([
        'name' => 'Vehicles',
        'slug' => 'vehicles-'.Str::random(6),
        'is_active' => true,
    ]);

    $guest = User::factory()->create([
        'type' => 'guest',
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);

    $delala = User::factory()->create(['type' => 'delala']);

    $job = DelalaJob::create([
        'buyer_id' => $guest->id,
        'category_id' => $category->id,
        'title' => 'Car deal in progress',
        'status' => Contracted::$name,
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);

    $proposal = Proposal::create([
        'job_id' => $job->id,
        'delala_id' => $delala->id,
        'status' => 'accepted',
    ]);

    $item = ProposalItem::create([
        'proposal_id' => $proposal->id,
        'item_name' => 'Toyota Vitz 2015',
        'commission_amount_santim' => 500000,
        'status' => AcceptedForEscrow::$name,
    ]);

    EscrowTransaction::create([
        'uuid' => (string) Str::uuid(),
        'job_id' => $job->id,
        'proposal_item_id' => $item->id,
        'buyer_id' => $guest->id,
        'delala_id' => $delala->id,
        'commission_amount_santim' => 500000,
        'platform_fee_santim' => 25000,
        'delala_amount_santim' => 475000,
        'status' => Funded::$name,
        'chapa_tx_ref' => 'chapa_test_ref_'.Str::random(8),
    ]);

    $this->artisan('delala:purge-inactive-guests')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', ['id' => $guest->id]);
});

it('does not purge inactive guests who had recent token or OTP activity', function () {
    $guestWithToken = User::factory()->create([
        'type' => 'guest',
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);
    // Token used 1 day ago
    $guestWithToken->tokens()->create([
        'name' => 'auth_token',
        'token' => hash('sha256', Str::random(40)),
        'abilities' => ['*'],
        'last_used_at' => now()->subDay(),
        'created_at' => now()->subDays(10),
    ]);

    $guestWithRecentOtp = User::factory()->create([
        'type' => 'guest',
        'phone' => '+251911223344',
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);
    OtpCode::create([
        'phone' => '+251911223344',
        'code' => '123456',
        'purpose' => 'auth',
        'expires_at' => now()->addMinutes(10),
        'created_at' => now()->subHours(2),
    ]);

    $purged = (new GarbageCollectInactiveGuestsJob(5))->handle();

    expect($purged)->toBe(0);
    $this->assertDatabaseHas('users', ['id' => $guestWithToken->id]);
    $this->assertDatabaseHas('users', ['id' => $guestWithRecentOtp->id]);
});

it('routes dev email to honore8a@gmail.com when Mail::alwaysTo is set', function () {
    Mail::alwaysTo('honore8a@gmail.com');

    $targetUser = User::factory()->create([
        'email' => 'client.customer@example.com',
        'full_name' => 'Client Customer',
    ]);

    $deliveredTo = null;
    Event::listen(MessageSending::class, function ($event) use (&$deliveredTo) {
        $recipients = $event->message->getTo();
        if (! empty($recipients)) {
            $deliveredTo = $recipients[0]->getAddress();
        }
    });

    $targetUser->notify(new VerifyEmailMagicLink);

    expect($deliveredTo)->toBe('honore8a@gmail.com');
});
