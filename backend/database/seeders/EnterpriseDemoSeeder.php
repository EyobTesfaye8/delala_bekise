<?php

namespace Database\Seeders;

use App\Models\AdminApprovalRequest;
use App\Models\Category;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\DelalaJob;
use App\Models\EscrowTransaction;
use App\Models\KycProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\PayoutDestination;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\ProposalItemPriceHistory;
use App\Models\Rating;
use App\Models\User;
use App\States\Escrow\Disputed;
use App\States\Escrow\Funded;
use App\States\Escrow\Released;
use App\States\Job\Completed;
use App\States\Job\Contracted;
use App\States\Job\Negotiating as JobNegotiating;
use App\States\Job\Open as JobOpen;
use App\States\Kyc\Approved as KycApproved;
use App\States\Kyc\UnderReview as KycUnderReview;
use App\States\Proposal\Accepted as ProposalAccepted;
use App\States\Proposal\Negotiating as ProposalNegotiating;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EnterpriseDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password123');

        // ==========================================
        // 1. Pre-Configured Test Personas
        // ==========================================

        // Senior Admin
        $seniorAdmin = User::updateOrCreate(
            ['email' => 'senior@delala.et'],
            [
                'full_name' => 'Senior Administrator',
                'phone' => '+251900000001',
                'password' => $defaultPassword,
                'type' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        // Junior Admin
        $juniorAdmin = User::updateOrCreate(
            ['email' => 'junior@delala.et'],
            [
                'full_name' => 'Junior Administrator',
                'phone' => '+251900000002',
                'password' => $defaultPassword,
                'type' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        // Verified Delala
        $verifiedDelala = User::updateOrCreate(
            ['email' => 'delala.verified@delala.et'],
            [
                'full_name' => 'Abebe Kebede',
                'phone' => '+251911223344',
                'password' => $defaultPassword,
                'type' => 'delala',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        // Approved KYC Profile for Verified Delala
        KycProfile::updateOrCreate(
            ['user_id' => $verifiedDelala->id],
            [
                'status' => KycApproved::class,
                'id_document_s3_key' => 'kyc/verified_delala_id.jpg',
                'id_document_back_s3_key' => 'kyc/verified_delala_id_back.jpg',
                'selfie_s3_key' => 'kyc/verified_delala_selfie.jpg',
                'document_type' => 'national_id',
                'reviewed_by' => $seniorAdmin->id,
                'reviewed_at' => now()->subDays(10),
            ]
        );

        // Default CBE Payout Destination for Verified Delala
        PayoutDestination::updateOrCreate(
            [
                'delala_id' => $verifiedDelala->id,
                'account_number' => '1000123456789',
            ],
            [
                'bank_code' => 'CBE',
                'bank_name' => 'Commercial Bank of Ethiopia',
                'account_holder_name' => 'Abebe Kebede',
                'telebirr_number' => null,
                'is_default' => true,
                'verified_at' => now()->subDays(9),
            ]
        );

        // Unverified Delala
        $unverifiedDelala = User::updateOrCreate(
            ['email' => 'delala.unverified@delala.et'],
            [
                'full_name' => 'Chala Bekele',
                'phone' => '+251922334455',
                'password' => $defaultPassword,
                'type' => 'delala',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        // Pending / Under Review KYC for Unverified Delala
        KycProfile::updateOrCreate(
            ['user_id' => $unverifiedDelala->id],
            [
                'status' => KycUnderReview::class,
                'id_document_s3_key' => 'kyc/unverified_delala_id.jpg',
                'id_document_back_s3_key' => 'kyc/unverified_delala_id_back.jpg',
                'selfie_s3_key' => 'kyc/unverified_delala_selfie.jpg',
                'document_type' => 'kebele_id',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );

        // Registered Buyer
        $registeredBuyer = User::updateOrCreate(
            ['email' => 'buyer@delala.et'],
            [
                'full_name' => 'Sara Haile',
                'phone' => '+251933445566',
                'password' => $defaultPassword,
                'type' => 'buyer',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        // Guest Buyer
        User::updateOrCreate(
            ['phone' => '+251944556677'],
            [
                'full_name' => 'Guest Buyer',
                'email' => null,
                'password' => $defaultPassword,
                'type' => 'guest',
                'status' => 'active',
                'guest_uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
                'phone_verified_at' => now(),
            ]
        );

        // ==========================================
        // 2. System Ledger Accounts (Numeric & Aliases)
        // ==========================================
        $ledgerAccounts = [
            // Standard Chart of Accounts (CoA) Codes (srs_v2.md §5.1)
            '1010' => ['name' => 'System Chapa Holding (1010)', 'type' => 'asset'],
            '1020' => ['name' => 'System Payout Clearing (1020)', 'type' => 'asset'],
            '2010' => ['name' => 'Buyer Escrow Liability (2010)', 'type' => 'liability'],
            '2020' => ['name' => 'Delala Pending Wallet (2020)', 'type' => 'liability'],
            '2030' => ['name' => 'Delala Available Wallet (2030)', 'type' => 'liability'],
            '4010' => ['name' => 'Platform Revenue Wallet (4010)', 'type' => 'equity'],

            // Backward-Compatible String Keys for LedgerService
            'system_chapa_holding' => ['name' => 'System Chapa Holding', 'type' => 'asset'],
            'system_payout_clearing' => ['name' => 'System Payout Clearing', 'type' => 'asset'],
            'payout_clearing_account' => ['name' => 'Payout Clearing Account', 'type' => 'asset'],
            'buyer_escrow_liability' => ['name' => 'Buyer Escrow Liability', 'type' => 'liability'],
            'delala_pending_wallet' => ['name' => 'Delala Pending Wallet', 'type' => 'liability'],
            'platform_revenue_wallet' => ['name' => 'Platform Revenue Wallet', 'type' => 'equity'],
        ];

        foreach ($ledgerAccounts as $code => $data) {
            LedgerAccount::firstOrCreate(
                ['code' => $code],
                ['name' => $data['name'], 'type' => $data['type'], 'user_id' => null]
            );
        }

        // Verified Delala's Available Wallet Account
        $delalaAvailableAccount = LedgerAccount::firstOrCreate(
            ['code' => 'delala_available_wallet_'.$verifiedDelala->id],
            [
                'name' => 'Available Wallet - Abebe Kebede',
                'type' => 'liability',
                'user_id' => $verifiedDelala->id,
            ]
        );

        // ==========================================
        // 3. Categories Lookup
        // ==========================================
        $residentialRentalCat = Category::where('slug', 'residential-rental')->first();
        $commercialLeaseCat = Category::where('slug', 'commercial-lease')->first();
        $passengerCarsCat = Category::where('slug', 'passenger-cars')->first();
        $residentialSaleCat = Category::where('slug', 'residential-sale')->first();

        // ==========================================
        // 4. Sample Transaction 1: Live Job & Multi-Item Proposal & Chat Negotiation
        // ==========================================
        if ($residentialRentalCat) {
            $job1 = DelalaJob::updateOrCreate(
                [
                    'buyer_id' => $registeredBuyer->id,
                    'title' => 'Furnished 2BR Apartment near Bole Atlas',
                ],
                [
                    'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0001',
                    'category_id' => $residentialRentalCat->id,
                    'description' => 'Looking for a secure, modern 2-bedroom furnished apartment with 24/7 standby generator, underground parking, and fiber connectivity.',
                    'status' => JobNegotiating::class,
                    'location' => 'Bole Atlas, Addis Ababa',
                    'location_region' => 'Addis Ababa',
                    'location_subcity' => 'Bole',
                    'budget_min_santim' => 3500000, // 35,000 ETB
                    'budget_max_santim' => 5000000, // 50,000 ETB
                    'budget_min' => 35000.0,
                    'budget_max' => 50000.0,
                    'proposal_count' => 1,
                    'attributes' => [
                        'bedrooms' => 2,
                        'bathrooms' => 2,
                        'furnished' => true,
                        'backup_generator' => true,
                        'parking' => true,
                    ],
                ]
            );

            // Delala Multi-Item Proposal
            $proposal1 = Proposal::updateOrCreate(
                [
                    'job_id' => $job1->id,
                    'delala_id' => $verifiedDelala->id,
                ],
                [
                    'status' => ProposalNegotiating::class,
                    'cover_note' => 'Greetings Sara! I have inspected two prime luxury units in Bole Atlas matching all your criteria with automatic generators.',
                ]
            );

            // Proposal Item 1 (Atlas Corner)
            $item1 = ProposalItem::updateOrCreate(
                [
                    'proposal_id' => $proposal1->id,
                    'item_name' => 'Modern 2BR Atlas Corner Apartment',
                ],
                [
                    'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0002',
                    'description' => '3rd floor, panoramic balcony, 100% backup generator coverage, 2 underground parking bays.',
                    'text_location' => 'Bole Atlas behind Sapphire Hotel',
                    'estimated_item_price_santim' => 4200000, // 42,000 ETB/mo rent
                    'commission_amount_santim' => 150000,    // 1,500 ETB commission
                    'status' => 'pending',
                ]
            );

            // Proposal Item 2 (Edna Mall area)
            ProposalItem::updateOrCreate(
                [
                    'proposal_id' => $proposal1->id,
                    'item_name' => 'Spacious 2BR Suite near Edna Mall',
                ],
                [
                    'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0003',
                    'description' => 'Newly finished residential complex, soundproof silent generator, biometric building access.',
                    'text_location' => 'Bole Medhanialem',
                    'estimated_item_price_santim' => 4500000,
                    'commission_amount_santim' => 180000,
                    'status' => 'pending',
                ]
            );

            // Price negotiation history entry
            ProposalItemPriceHistory::firstOrCreate(
                [
                    'proposal_item_id' => $item1->id,
                    'changed_by_id' => $verifiedDelala->id,
                ],
                [
                    'old_amount_santim' => 165000,
                    'new_amount_santim' => 150000,
                    'created_at' => now()->subHours(2),
                ]
            );

            // Chat Channel & In-Chat Messages
            $chat1 = Chat::firstOrCreate(
                ['proposal_id' => $proposal1->id],
                [
                    'buyer_id' => $registeredBuyer->id,
                    'delala_id' => $verifiedDelala->id,
                    'is_read_only' => false,
                ]
            );

            ChatMessage::firstOrCreate(
                ['chat_id' => $chat1->id, 'sender_id' => $registeredBuyer->id, 'body' => 'Hello Abebe, does the Atlas apartment generator fuel get billed separately or included in HOA fee?'],
                ['type' => 'text', 'created_at' => now()->subHours(4)]
            );

            ChatMessage::firstOrCreate(
                ['chat_id' => $chat1->id, 'sender_id' => $verifiedDelala->id, 'body' => 'Hello Sara! Generator diesel maintenance is 100% included in the building service fee. Water pump is also on generator line.'],
                ['type' => 'text', 'created_at' => now()->subHours(3)]
            );

            ChatMessage::firstOrCreate(
                ['chat_id' => $chat1->id, 'sender_id' => $registeredBuyer->id, 'body' => 'Excellent! Can we round the commission fee to 1,500 ETB? If so, I am ready to inspect tomorrow afternoon.'],
                ['type' => 'text', 'created_at' => now()->subHours(2)->subMinutes(15)]
            );

            ChatMessage::firstOrCreate(
                ['chat_id' => $chat1->id, 'sender_id' => $verifiedDelala->id, 'body' => 'Agreed! I have updated Item 1 commission to 1,500 ETB. Looking forward to showing you the apartment.'],
                ['type' => 'text', 'created_at' => now()->subHours(2)]
            );
        }

        // ==========================================
        // 5. Sample Transaction 2: Open Job (Guest Buyer, Passenger Cars)
        // ==========================================
        if ($passengerCarsCat) {
            $guestUser = User::where('phone', '+251944556677')->first();
            if ($guestUser) {
                DelalaJob::updateOrCreate(
                    [
                        'buyer_id' => $guestUser->id,
                        'title' => 'Toyota Vitz 2019 Automatic in Addis',
                    ],
                    [
                        'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0004',
                        'category_id' => $passengerCarsCat->id,
                        'description' => 'Looking for a silver or white Toyota Vitz (2018-2020), automatic transmission, original factory paint, below 50k mileage.',
                        'status' => JobOpen::class,
                        'location' => 'Addis Ababa',
                        'location_region' => 'Addis Ababa',
                        'location_subcity' => 'Kirkos',
                        'budget_min_santim' => 190000000, // 1.9M ETB
                        'budget_max_santim' => 240000000, // 2.4M ETB
                        'budget_min' => 1900000.0,
                        'budget_max' => 2400000.0,
                        'proposal_count' => 0,
                        'attributes' => [
                            'make' => 'Toyota',
                            'model' => 'Vitz',
                            'year' => 2019,
                            'transmission' => 'automatic',
                            'fuel_type' => 'petrol',
                            'mileage' => 42000,
                        ],
                    ]
                );
            }
        }

        // ==========================================
        // 6. Sample Transaction 3: Active Funded Escrow Transaction (Commercial Lease)
        // ==========================================
        if ($commercialLeaseCat) {
            $job3 = DelalaJob::updateOrCreate(
                [
                    'buyer_id' => $registeredBuyer->id,
                    'title' => 'Prime 120sqm Commercial Office Suite',
                ],
                [
                    'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0005',
                    'category_id' => $commercialLeaseCat->id,
                    'description' => 'Commercial floor space needed for IT consulting office on Bole Road.',
                    'status' => Contracted::class,
                    'location' => 'Bole Road, Mega Building area',
                    'location_region' => 'Addis Ababa',
                    'location_subcity' => 'Bole',
                    'budget_min_santim' => 6000000,
                    'budget_max_santim' => 9000000,
                    'budget_min' => 60000.0,
                    'budget_max' => 90000.0,
                    'proposal_count' => 1,
                    'attributes' => [
                        'square_meters' => 120,
                        'floor_number' => 3,
                        'elevator' => true,
                    ],
                ]
            );

            $proposal3 = Proposal::updateOrCreate(
                ['job_id' => $job3->id, 'delala_id' => $verifiedDelala->id],
                ['status' => ProposalAccepted::class, 'cover_note' => 'Exclusive office listing in Mega Center with dedicated transformer.']
            );

            $item3 = ProposalItem::updateOrCreate(
                ['proposal_id' => $proposal3->id, 'item_name' => '120 sqm 3rd Floor Office Space'],
                [
                    'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0006',
                    'description' => 'Partitioned into 4 executive rooms, reception area, server room with cooling.',
                    'text_location' => 'Bole Mega Center, 3rd Floor',
                    'estimated_item_price_santim' => 7500000,
                    'commission_amount_santim' => 250000, // 2,500 ETB
                    'status' => 'accepted_for_escrow',
                ]
            );

            $escrowFunded = EscrowTransaction::updateOrCreate(
                ['proposal_item_id' => $item3->id],
                [
                    'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0007',
                    'job_id' => $job3->id,
                    'buyer_id' => $registeredBuyer->id,
                    'delala_id' => $verifiedDelala->id,
                    'commission_amount_santim' => 250000, // 2,500 ETB
                    'platform_fee_santim' => 12500,       // 125 ETB (5%)
                    'delala_amount_santim' => 237500,     // 2,375 ETB (95%)
                    'status' => Funded::class,
                    'chapa_tx_ref' => 'TX_DEMO_FUNDED_101',
                    'chapa_paid_at' => now()->subHours(6),
                ]
            );

            // Double-Entry Ledger Journal for Flow A (JNL-1A & JNL-1B)
            $chapaHoldingAcc = LedgerAccount::where('code', 'system_chapa_holding')->first();
            $buyerLiabAcc = LedgerAccount::where('code', 'buyer_escrow_liability')->first();
            $delalaPendingAcc = LedgerAccount::where('code', 'delala_pending_wallet')->first();
            $platformRevAcc = LedgerAccount::where('code', 'platform_revenue_wallet')->first();

            if ($chapaHoldingAcc && $buyerLiabAcc && $delalaPendingAcc && $platformRevAcc) {
                // JNL-1A: Chapa Webhook Received
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'FND_DEMO_101_A', 'ledger_account_id' => $chapaHoldingAcc->id],
                    [
                        'escrow_transaction_id' => $escrowFunded->id,
                        'direction' => 'debit',
                        'amount_santim' => 250000,
                        'description' => 'Escrow funded deposit - '.$escrowFunded->uuid,
                    ]
                );
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'FND_DEMO_101_A', 'ledger_account_id' => $buyerLiabAcc->id],
                    [
                        'escrow_transaction_id' => $escrowFunded->id,
                        'direction' => 'credit',
                        'amount_santim' => 250000,
                        'description' => 'Escrow funded deposit - '.$escrowFunded->uuid,
                    ]
                );

                // JNL-1B: Provisional Split
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'ALLOC_DEMO_101_B', 'ledger_account_id' => $buyerLiabAcc->id],
                    [
                        'escrow_transaction_id' => $escrowFunded->id,
                        'direction' => 'debit',
                        'amount_santim' => 250000,
                        'description' => 'Provisional allocation - '.$escrowFunded->uuid,
                    ]
                );
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'ALLOC_DEMO_101_B', 'ledger_account_id' => $delalaPendingAcc->id],
                    [
                        'escrow_transaction_id' => $escrowFunded->id,
                        'direction' => 'credit',
                        'amount_santim' => 237500,
                        'description' => 'Provisional delala commission - '.$escrowFunded->uuid,
                    ]
                );
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'ALLOC_DEMO_101_B', 'ledger_account_id' => $platformRevAcc->id],
                    [
                        'escrow_transaction_id' => $escrowFunded->id,
                        'direction' => 'credit',
                        'amount_santim' => 12500,
                        'description' => 'Provisional platform 5% fee - '.$escrowFunded->uuid,
                    ]
                );
            }
        }

        // ==========================================
        // 7. Sample Transaction 4: Completed Deal with Double-Blind Ratings
        // ==========================================
        if ($passengerCarsCat) {
            $job4 = DelalaJob::updateOrCreate(
                [
                    'buyer_id' => $registeredBuyer->id,
                    'title' => 'Hyundai Tucson 2021 AWD Low Mileage',
                ],
                [
                    'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0008',
                    'category_id' => $passengerCarsCat->id,
                    'description' => 'Purchased a Hyundai Tucson 2021 through verified broker Abebe.',
                    'status' => Completed::class,
                    'location' => 'Bole, Addis Ababa',
                    'location_region' => 'Addis Ababa',
                    'location_subcity' => 'Bole',
                    'budget_min_santim' => 450000000,
                    'budget_max_santim' => 520000000,
                    'budget_min' => 4500000.0,
                    'budget_max' => 5200000.0,
                    'proposal_count' => 1,
                    'attributes' => [
                        'make' => 'Hyundai',
                        'model' => 'Tucson',
                        'year' => 2021,
                        'transmission' => 'automatic',
                        'fuel_type' => 'petrol',
                        'mileage' => 28000,
                    ],
                ]
            );

            $proposal4 = Proposal::updateOrCreate(
                ['job_id' => $job4->id, 'delala_id' => $verifiedDelala->id],
                ['status' => ProposalAccepted::class, 'cover_note' => 'One owner Tucson with complete dealer service book at Marathon Motors.']
            );

            $item4 = ProposalItem::updateOrCreate(
                ['proposal_id' => $proposal4->id, 'item_name' => '2021 Hyundai Tucson GLS AWD'],
                [
                    'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0009',
                    'description' => 'Pristine condition, leather seats, panoramic sunroof, mechanic inspection report certified.',
                    'text_location' => 'CMC Michael, Addis Ababa',
                    'estimated_item_price_santim' => 490000000,
                    'commission_amount_santim' => 400000, // 4,000 ETB
                    'status' => 'accepted_for_escrow',
                ]
            );

            $escrowCompleted = EscrowTransaction::updateOrCreate(
                ['proposal_item_id' => $item4->id],
                [
                    'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0010',
                    'job_id' => $job4->id,
                    'buyer_id' => $registeredBuyer->id,
                    'delala_id' => $verifiedDelala->id,
                    'commission_amount_santim' => 400000, // 4,000 ETB
                    'platform_fee_santim' => 20000,       // 200 ETB (5%)
                    'delala_amount_santim' => 380000,     // 3,800 ETB (95%)
                    'status' => Released::class,
                    'chapa_tx_ref' => 'TX_DEMO_COMPL_202',
                    'chapa_paid_at' => now()->subDays(5),
                    'handover_declared_at' => now()->subDays(4),
                    'resolved_at' => now()->subDays(3),
                    'resolved_by' => $registeredBuyer->id,
                    'admin_resolution_note' => 'Buyer released funds after inspection and handover completed.',
                ]
            );

            // Record Balanced Ledger entries for Completed Escrow:
            // Flow A: Webhook + Allocation
            if ($chapaHoldingAcc && $buyerLiabAcc && $delalaPendingAcc && $platformRevAcc) {
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'FND_DEMO_202_A', 'ledger_account_id' => $chapaHoldingAcc->id],
                    [
                        'escrow_transaction_id' => $escrowCompleted->id,
                        'direction' => 'debit',
                        'amount_santim' => 400000,
                        'description' => 'Escrow funded deposit - '.$escrowCompleted->uuid,
                    ]
                );
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'FND_DEMO_202_A', 'ledger_account_id' => $buyerLiabAcc->id],
                    [
                        'escrow_transaction_id' => $escrowCompleted->id,
                        'direction' => 'credit',
                        'amount_santim' => 400000,
                        'description' => 'Escrow funded deposit - '.$escrowCompleted->uuid,
                    ]
                );

                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'ALLOC_DEMO_202_B', 'ledger_account_id' => $buyerLiabAcc->id],
                    [
                        'escrow_transaction_id' => $escrowCompleted->id,
                        'direction' => 'debit',
                        'amount_santim' => 400000,
                        'description' => 'Provisional allocation - '.$escrowCompleted->uuid,
                    ]
                );
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'ALLOC_DEMO_202_B', 'ledger_account_id' => $delalaPendingAcc->id],
                    [
                        'escrow_transaction_id' => $escrowCompleted->id,
                        'direction' => 'credit',
                        'amount_santim' => 380000,
                        'description' => 'Provisional delala commission - '.$escrowCompleted->uuid,
                    ]
                );
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'ALLOC_DEMO_202_B', 'ledger_account_id' => $platformRevAcc->id],
                    [
                        'escrow_transaction_id' => $escrowCompleted->id,
                        'direction' => 'credit',
                        'amount_santim' => 20000,
                        'description' => 'Provisional platform 5% fee - '.$escrowCompleted->uuid,
                    ]
                );

                // Flow B (JNL-2): Release from Delala Pending to Delala Available
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'REL_DEMO_202_C', 'ledger_account_id' => $delalaPendingAcc->id],
                    [
                        'escrow_transaction_id' => $escrowCompleted->id,
                        'direction' => 'debit',
                        'amount_santim' => 380000,
                        'description' => 'Escrow released - '.$escrowCompleted->uuid,
                    ]
                );
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'REL_DEMO_202_C', 'ledger_account_id' => $delalaAvailableAccount->id],
                    [
                        'escrow_transaction_id' => $escrowCompleted->id,
                        'direction' => 'credit',
                        'amount_santim' => 380000,
                        'description' => 'Escrow released into available wallet - '.$escrowCompleted->uuid,
                    ]
                );
            }

            // Double-Blind Mutual Ratings (FR-6.1)
            // Buyer -> Delala
            Rating::firstOrCreate(
                [
                    'escrow_transaction_id' => $escrowCompleted->id,
                    'rater_id' => $registeredBuyer->id,
                ],
                [
                    'buyer_id' => $registeredBuyer->id,
                    'delala_id' => $verifiedDelala->id,
                    'ratee_id' => $verifiedDelala->id,
                    'stars' => 5,
                    'review' => 'Abebe was exceptional. He facilitated complete mechanical diagnostics and title deed verification at the transport bureau.',
                    'is_revealed' => true,
                    'revealed_at' => now()->subDays(2),
                ]
            );

            // Delala -> Buyer
            Rating::firstOrCreate(
                [
                    'escrow_transaction_id' => $escrowCompleted->id,
                    'rater_id' => $verifiedDelala->id,
                ],
                [
                    'buyer_id' => $registeredBuyer->id,
                    'delala_id' => $verifiedDelala->id,
                    'ratee_id' => $registeredBuyer->id,
                    'stars' => 5,
                    'review' => 'Sara was punctual, communicative, and released escrow immediately following physical car delivery.',
                    'is_revealed' => true,
                    'revealed_at' => now()->subDays(2),
                ]
            );
        }

        // ==========================================
        // 8. Sample Transaction 5: High-Value Disputed Deal with Pending Maker-Checker Approval Request
        // ==========================================
        if ($residentialSaleCat) {
            $job5 = DelalaJob::updateOrCreate(
                [
                    'buyer_id' => $registeredBuyer->id,
                    'title' => 'Diplomatic Villa in Old Airport Area',
                ],
                [
                    'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0011',
                    'category_id' => $residentialSaleCat->id,
                    'description' => 'High-value residential acquisition in Old Airport diplomatic quarter.',
                    'status' => Contracted::class,
                    'location' => 'Old Airport, Addis Ababa',
                    'location_region' => 'Addis Ababa',
                    'location_subcity' => 'Nifas Silk-Lafto',
                    'budget_min_santim' => 9000000000,
                    'budget_max_santim' => 15000000000,
                    'budget_min' => 90000000.0,
                    'budget_max' => 150000000.0,
                    'proposal_count' => 1,
                    'attributes' => [
                        'bedrooms' => 5,
                        'bathrooms' => 4,
                        'square_meters' => 650,
                        'property_type' => 'villa',
                        'title_deed_verified' => true,
                    ],
                ]
            );

            $proposal5 = Proposal::updateOrCreate(
                ['job_id' => $job5->id, 'delala_id' => $verifiedDelala->id],
                ['status' => ProposalAccepted::class, 'cover_note' => 'Exclusive representation for European embassy standard residence.']
            );

            $item5 = ProposalItem::updateOrCreate(
                ['proposal_id' => $proposal5->id, 'item_name' => '650 sqm Modern Luxury Villa with Garden'],
                [
                    'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0012',
                    'description' => 'Security quarters, backup power plant, swimming pool, authentic digital Carta.',
                    'text_location' => 'Old Airport near ICS',
                    'estimated_item_price_santim' => 12000000000,
                    'commission_amount_santim' => 1500000, // 15,000 ETB (> 10,000 ETB Maker-Checker threshold)
                    'status' => 'accepted_for_escrow',
                ]
            );

            $disputedEscrow = EscrowTransaction::updateOrCreate(
                ['proposal_item_id' => $item5->id],
                [
                    'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3d0013',
                    'job_id' => $job5->id,
                    'buyer_id' => $registeredBuyer->id,
                    'delala_id' => $verifiedDelala->id,
                    'commission_amount_santim' => 1500000, // 15,000 ETB
                    'platform_fee_santim' => 75000,        // 750 ETB (5%)
                    'delala_amount_santim' => 1425000,     // 14,250 ETB (95%)
                    'status' => Disputed::class,
                    'chapa_tx_ref' => 'TX_DEMO_DISP_303',
                    'chapa_paid_at' => now()->subDays(2),
                    'handover_declared_at' => now()->subDay(),
                    'dispute_reason_code' => 'SELLER_NO_SHOW',
                    'dispute_evidence_s3_keys' => ['evidence/cbe_wire_proof_303.pdf'],
                ]
            );

            // Flow A balanced entries
            if ($chapaHoldingAcc && $buyerLiabAcc && $delalaPendingAcc && $platformRevAcc) {
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'FND_DEMO_303_A', 'ledger_account_id' => $chapaHoldingAcc->id],
                    [
                        'escrow_transaction_id' => $disputedEscrow->id,
                        'direction' => 'debit',
                        'amount_santim' => 1500000,
                        'description' => 'Escrow funded deposit - '.$disputedEscrow->uuid,
                    ]
                );
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'FND_DEMO_303_A', 'ledger_account_id' => $buyerLiabAcc->id],
                    [
                        'escrow_transaction_id' => $disputedEscrow->id,
                        'direction' => 'credit',
                        'amount_santim' => 1500000,
                        'description' => 'Escrow funded deposit - '.$disputedEscrow->uuid,
                    ]
                );
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'ALLOC_DEMO_303_B', 'ledger_account_id' => $buyerLiabAcc->id],
                    [
                        'escrow_transaction_id' => $disputedEscrow->id,
                        'direction' => 'debit',
                        'amount_santim' => 1500000,
                        'description' => 'Provisional allocation - '.$disputedEscrow->uuid,
                    ]
                );
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'ALLOC_DEMO_303_B', 'ledger_account_id' => $delalaPendingAcc->id],
                    [
                        'escrow_transaction_id' => $disputedEscrow->id,
                        'direction' => 'credit',
                        'amount_santim' => 1425000,
                        'description' => 'Provisional delala commission - '.$disputedEscrow->uuid,
                    ]
                );
                LedgerEntry::firstOrCreate(
                    ['transaction_ref' => 'ALLOC_DEMO_303_B', 'ledger_account_id' => $platformRevAcc->id],
                    [
                        'escrow_transaction_id' => $disputedEscrow->id,
                        'direction' => 'credit',
                        'amount_santim' => 75000,
                        'description' => 'Provisional platform 5% fee - '.$disputedEscrow->uuid,
                    ]
                );
            }

            // High-Value Maker-Checker Admin Approval Request (> 10,000 ETB)
            // Initiated by Junior Admin -> Awaiting Senior Admin approval
            AdminApprovalRequest::updateOrCreate(
                [
                    'entity_type' => EscrowTransaction::class,
                    'entity_id' => $disputedEscrow->id,
                ],
                [
                    'action_type' => 'FORCE_RELEASE',
                    'amount_santim' => 1425000, // 14,250 ETB
                    'payload' => [
                        'escrow_uuid' => $disputedEscrow->uuid,
                        'reason' => 'Contract executed and validated with Land Bureau transfer receipt.',
                    ],
                    'requested_by_id' => $juniorAdmin->id,
                    'approved_by_id' => null,
                    'status' => 'PENDING',
                    'justification_note' => 'Delala Abebe submitted authenticated bill of sale signed before the Addis Ababa Land Management Office. Proof of settlement verified.',
                    'rejection_reason' => null,
                    'approved_at' => null,
                ]
            );
        }
    }
}
