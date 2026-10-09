# Software Requirements Specification (SRS)
**Project:** Delala Platform (Informal Broker Marketplace)
**Target Architecture:** PHP / Laravel (Backend REST API), SPA/Mobile Frontend, PostgreSQL, Redis.
**Primary Integrations:** Chapa (Payments), SMS Gateway (OTP).
**Document Version:** 4.0 (Final)

---

## 1. Executive Summary & Business Context

The Delala Platform digitizes the traditional Ethiopian informal broker network. It is an escrow-backed marketplace connecting Buyers with "Delalas" (Brokers) who source items.

**Core Business Rules:**
*   **Commission Only:** The platform facilitates the negotiation and Escrow payment of the **Delala's Commission only**. The principal transaction occurs offline.
*   **Platform Revenue:** The platform charges a **5% fee** deducted from the Delala's commission payout. The Chapa gateway fee (~2%) is absorbed by the platform from this 5%.

---

## 2. System Actors & Access Control (RBAC)

| Actor | Authentication Level | Capabilities & Access |
| :--- | :--- | :--- |
| **Guest Buyer** | Phone Number + OTP. | Post jobs, receive proposals, negotiate in chat, and fund escrow. Session recovery requires a new OTP. |
| **Registered Buyer** | Phone + Email + Password. | All Guest features + persistent profile, historical activity, saved preferences, and full job history. |
| **Delala** | Phone + Email + Password + KYC. | Browse global job feed, submit multi-item proposals, negotiate, declare handover, and withdraw funds. |
| **Admin** | Internal SSO / 2FA. | Review/Approve KYC, mediate disputes, process manual payouts, and monitor system health. |

---

## 3. Core Domain Models & State Machines

To prevent orphaned records and race conditions, the system relies on strict state machines managed via the backend (e.g., `spatie/laravel-model-states`).

### 3.1 Job State Machine

*   `DRAFT`: Incomplete post, invisible to public.
*   `OPEN`: Visible in global feed. Accepting proposals.
*   `NEGOTIATING`: Buyer has replied to at least one proposal. Still accepts new proposals from other Delalas.
*   `CONTRACTED`: Escrow funded for a specific proposal item. Job is removed from feed. All other active chats become read-only.
*   `COMPLETED`: Triggered when the linked Escrow reaches `RELEASED` or `FORCE_RELEASED`. Rating/Review is enabled.
*   `CANCELED`: Triggered when Escrow reaches `FORCE_REFUNDED`, or when closed by the Buyer without a contract, or closed by Admin.

> **Crucial Concurrency Rule ("First-to-Fund Wins"):** Because `NEGOTIATING` still accepts new proposals, multiple Chapa checkout URLs might exist simultaneously. The *first* `payment.success` webhook received locks the Job to `CONTRACTED` (using `lockForUpdate()`) and voids all other `PENDING_PAYMENT` escrows. If a secondary `payment.success` webhook arrives for the same Job after it is already `CONTRACTED`, the system must immediately trigger the Chapa Refund API for the secondary buyer and notify them: "Job already awarded."

### 3.2 Proposal & Item State Machine

*Structure:* A `Proposal` contains 1..N `ProposalItems`.

*   **Proposal States:** `PENDING` (Submitted) → `NEGOTIATING` (Buyer replied) → `ACCEPTED` | `REJECTED` | `WITHDRAWN`
*   **Item States:** `PENDING` → `ACCEPTED_FOR_ESCROW` | `REJECTED`

**Transition Rules:**
*   A Delala can submit a maximum of **one active proposal per job**.
*   A Delala can `WITHDRAW` a proposal only while it is in the `PENDING` state.
*   A Buyer can explicitly `REJECT` a proposal that is in the `NEGOTIATING` state. This transitions the Proposal to `REJECTED` and the chat channel to **read-only** (not deleted, permanently retained for records).
*   Accepting one `ProposalItem` transitions that item to `ACCEPTED_FOR_ESCROW`, the parent Proposal to `ACCEPTED`, and all other items in that proposal to implicitly `REJECTED`.
*   When a Job transitions to `CONTRACTED`, all proposals not in the `ACCEPTED` state are set to `REJECTED` and their chat channels become read-only.

### 3.3 Escrow Transaction State Machine

*   `PENDING_PAYMENT`: Chapa checkout URL generated. No funds locked yet.
*   `FUNDED`: Webhook received. Funds locked in double-entry ledger.
*   `CANCELLATION_REQUESTED`: *Triggered by Buyer ONLY from the `FUNDED` state (before handover is declared).* Delala must approve or reject within 48 hours. If the timer expires with no Delala action, the system auto-refunds. Once in `HANDOVER_DECLARED`, Buyers lose the ability to cancel; they must Dispute instead.
*   `HANDOVER_DECLARED`: Delala triggered "Complete". Starts the 120-hour Auto-Release timer.
*   `DISPUTED`: Buyer rejected handover within the 120-hour window. Escrow is frozen pending Admin review.
*   `RELEASED`: Funds moved to Delala's Available Wallet (via Buyer's explicit accept or the 120-hour timer expiry). Triggers Job → `COMPLETED`.
*   `FORCE_RELEASED`: Admin ruled in favor of Delala. Triggers Job → `COMPLETED`.
*   `FORCE_REFUNDED`: Admin ruled in favor of Buyer, OR system auto-refunded from `CANCELLATION_REQUESTED`. Triggers Job → `CANCELED`.
*   `REFUND_FAILED`: System attempted a Chapa refund (from any path) but received a 5xx response. See §6 AR-5 for the recovery path.

---

## 4. Financial & Ledger Specification (Double-Entry)

### 4.1 Chart of Accounts

The system utilizes a strict double-entry ledger. Every journal entry must sum to zero.

*   **`system_chapa_holding`** (Asset): Represents actual ETB sitting in the Chapa merchant account.
*   **`buyer_escrow_liability`** (Liability): Temporary holding account for Buyer deposits before allocation.
*   **`platform_revenue_wallet`** (Equity): The platform's earned 5% fees.
*   **`delala_pending_wallet`** (Liability): 95% of commission held during the `CONTRACTED`/`HANDOVER_DECLARED`/`DISPUTED` states.
*   **`delala_available_wallet`** (Liability): Funds cleared for withdrawal by the Delala.

### 4.2 Standard Journal Entries

| Event | Account | Dr / Cr | Amount |
| :--- | :--- | :--- | :--- |
| **A: Webhook Received** | `system_chapa_holding` | **Debit** | 10,000 ETB |
| | `buyer_escrow_liability` | **Credit** | 10,000 ETB |
| **A: Provisional Allocation** | `buyer_escrow_liability` | **Debit** | 10,000 ETB |
| | `delala_pending_wallet` | **Credit** | 9,500 ETB |
| | `platform_revenue_wallet` | **Credit** | 500 ETB |
| **B: Escrow Released** | `delala_pending_wallet` | **Debit** | 9,500 ETB |
| | `delala_available_wallet` | **Credit** | 9,500 ETB |
| **C: Any Refund Path** | `delala_pending_wallet` | **Debit** | 9,500 ETB |
| | `platform_revenue_wallet` | **Debit** | 500 ETB |
| | `system_chapa_holding` | **Credit** | 10,000 ETB |
| **D: Delala Withdrawal (PAID)** | `delala_available_wallet` | **Debit** | Amount |
| | `system_chapa_holding` | **Credit** | Amount |

> **Note on Flow C (Refunds):** The platform absorbs the ~2% Chapa transaction fee as an operational loss. The ledger reverses the exact gross amount (10,000 ETB) to maintain balance parity. Flow C applies to `FORCE_REFUND`, auto-refunds from `CANCELLATION_REQUESTED` expiry, and the "Job already awarded" secondary-buyer refund path.

### 4.3 Delala Payout Flow

*   **Registration:** Delalas must register payout details (Bank Name + Account Number, or Telebirr Number) before their first withdrawal.
*   **Minimum Withdrawal:** 500 ETB.
*   **Processing (V1 Manual):** All withdrawal requests generate a `PayoutRequest` record in the Admin Payout Queue (§6 AR-4). Admin batches these, exports a CSV to the bank, processes payment offline, then marks the request `PAID` in the system — triggering Journal Entry D.

---

## 5. Functional Requirements (FR)

### 5.1 Guest & Registered Buyer Lifecycle

*   **FR-1.1 Guest Session Initiation:** The system shall authenticate Guest Buyers by validating a 6-digit SMS OTP against a Phone Number, returning an ephemeral API token (Laravel Sanctum) valid for 24 hours.
*   **FR-1.2 Guest Session Recovery:** If a Guest's token expires, re-entering their phone number and receiving a new OTP must map the new session back to their existing Guest UUID.
*   **FR-1.3 Direct Registration:** A new user can register directly with `Full Name`, `Phone Number`, `Email`, and `Password`. The account remains `UNVERIFIED` until both the SMS OTP and Email magic-link are validated.
*   **FR-1.4 Guest-to-Registered Migration:** A logged-in Guest Buyer may upgrade their account by providing an Email and Password. The system must attach these credentials to the existing `user_id` to ensure active Jobs, Chats, and Escrow Transactions remain unbroken.
*   **FR-1.5 Migration Collision:** If a Guest attempts to upgrade using an Email already belonging to a Registered user, the system must reject the request (`409 Conflict`).

### 5.2 Delala Onboarding & KYC

*   **FR-2.1 KYC State Machine:** A Delala's identity profile transitions through: `PENDING` → `UNDER_REVIEW` → (`APPROVED` | `REJECTED`).
*   **FR-2.2 Feature Lock:** Delalas cannot view the Job feed, submit proposals, or initiate chats until their KYC status is `APPROVED`.
*   **FR-2.3 KYC Artifacts:** Delala registration requires uploading a Government ID scan (Kebele ID or Passport) and a live selfie, both stored in private AWS S3.

### 5.3 Job Feed & Category Taxonomy

*   **FR-3.1 Feed Rules:** The global Delala feed exposes `OPEN` and `NEGOTIATING` jobs, queryable by Category, Location, and Budget Range. The feed displays the current `proposal_count` per job.
*   **FR-3.2 Pagination:** The job feed must use cursor-based pagination (`next_cursor`) for real-time stability. Admin queues (KYC, Disputes, Payouts) use standard offset-based pagination: `{"data": [], "meta": {"current_page": 1, "last_page": 5, "total": 50}}`.
*   **FR-3.3 Visibility Constraints:** Delalas cannot see jobs they have already submitted an active proposal on in the main feed.
*   **FR-3.4 Category Schema Validation:** Categories are managed via Admin CRUD. Each category record stores a JSON Schema draft-07 definition. Write operations to a job's `attributes` JSONB field must be validated against the parent category's schema at request-time.

### 5.4 Real-Time Chat & Proposals

*   **FR-4.1 Chat Instantiation:** Chat channels are created *only* when a Buyer explicitly replies to a Delala's proposal. Unsolicited DMs from Delalas are prohibited.
*   **FR-4.2 Message Types:** V1 supports text messages and injected System Event messages only (e.g., "Commission updated from X to Y ETB"). Image sharing is handled via Proposal Item attachments, not the chat stream.
*   **FR-4.3 Proposal Mutability:** During an active chat, the Delala may update a `ProposalItem`'s `commission_amount`. This mutation must: (a) trigger a WebSocket broadcast to the Buyer, and (b) write an immutable audit log entry. The backend stores all amounts as integers in Santim (ETB subunits).
*   **FR-4.4 Chat Lifecycle:** Channels are permanently retained (never deleted). A channel transitions to read-only when the parent Proposal is `REJECTED`, `WITHDRAWN`, or `ACCEPTED`, or when the parent Job reaches `CONTRACTED` or `CANCELED`.

### 5.5 File Upload Constraints

*   **FR-5.1 Pre-signed Uploads:** All files (KYC documents, dispute evidence, proposal images) are uploaded directly to AWS S3 via short-lived pre-signed PUT URLs requested from the API. The API receives and stores only the resulting S3 object key.
*   **FR-5.2 KYC & Evidence:** Accepted formats: JPEG, PNG, PDF. Max 5MB per file.
*   **FR-5.3 Proposal Images:** Accepted formats: JPEG, PNG only. Max 5MB per image. Max 5 images per `ProposalItem`. Frontend must compress images client-side before upload.
*   **FR-5.4 Privacy:** KYC documents and dispute evidence must reside in a **private** S3 bucket. They are exposed exclusively to Admins via pre-signed GET URLs valid for 15 minutes.

---

## 6. Administrative Requirements (Admin Panel)

The back-office (Laravel Filament or Nova) must support the following operational queues. All lists use offset-based pagination.

*   **AR-1 KYC Queue:** Paginated list of `UNDER_REVIEW` Delalas. Admin views 15-minute pre-signed S3 URLs of documents. Actions: `APPROVE` or `REJECT` (rejection requires a mandatory reason field, which is included in the notification to the Delala).
*   **AR-2 Dispute Queue:** Paginated list of `DISPUTED` escrows. Displays full chat transcript, proposal items, and Delala-uploaded dispute evidence (via pre-signed URLs). Actions: `FORCE_RELEASE` or `FORCE_REFUND`.
*   **AR-3 User Moderation:** Admin can suspend Buyers or Delalas. Suspending a Delala automatically sets all their `PENDING` proposals to `WITHDRAWN`. Suspended users cannot authenticate until reinstated.
*   **AR-4 Payout Queue:** Paginated list of pending `PayoutRequest` records showing Delala name, bank details, and amount. Admin bulk-selects, exports a CSV, processes offline, and marks selected requests as `PAID` (triggering Journal Entry D).
*   **AR-5 Refund Recovery Queue:** Paginated list of escrows in `REFUND_FAILED` state. Each entry shows the failed Chapa API response. Admin can trigger a manual retry (re-invoking the Chapa Refund API) or escalate to engineering. The system sends an internal alert (e.g., Slack webhook) immediately when any escrow enters `REFUND_FAILED`.
*   **AR-6 Financial Dashboard:** Read-only view of `platform_revenue_wallet` balance, total `delala_pending_wallet` exposure, and a paginated Chapa reconciliation log (all `system_chapa_holding` debits/credits).

---

## 7. Notifications & Communications Matrix

All state transitions dispatch asynchronous Laravel queued jobs to send notifications. All notification jobs must be idempotent.

| Event | Target Actor | Channels | Payload / Action |
| :--- | :--- | :--- | :--- |
| **KYC Approved** | Delala | SMS, Email, In-App | "Your identity has been verified. You can now browse jobs." |
| **KYC Rejected** | Delala | SMS, Email, In-App | "Your KYC was rejected: [Admin Reason]. Please resubmit." |
| **Proposal Received** | Buyer | Push, In-App | "New proposal from [Delala Name] on your job [Job Title]." |
| **Chat Message Received** | Buyer or Delala | Push, In-App | "[Sender Name]: [Message preview]." |
| **Commission Updated** | Buyer | Push, In-App | "[Delala] updated the commission to [New Amount] ETB." |
| **Escrow Funded** | Delala | Push, In-App | "Buyer funded [Amount] ETB in escrow. You may begin work." |
| **Cancellation Requested** | Delala | SMS, Push, In-App | "Buyer has requested a cancellation. Approve or reject within 48 hrs." |
| **Cancellation Approved** | Buyer | Push, In-App | "Delala approved your cancellation. Refund is being processed." |
| **Cancellation Rejected** | Buyer | Push, In-App | "Delala rejected your cancellation request." |
| **Handover Declared** | Buyer | SMS, Push, In-App | "Delala has declared the handover complete. Accept or Dispute within 5 days." |
| **Timer Warning (96 hr)** | Buyer | SMS, Push | "24 hours remaining before your escrow is auto-released. Dispute now if needed." |
| **Escrow Released** | Delala | Push, In-App | "[Amount] ETB has been moved to your Available Wallet." |
| **Dispute Opened** | Delala | SMS, Push, In-App | "A dispute has been raised. Upload your documentary evidence in the app." |
| **Admin Decision: Force Released** | Delala | Push, In-App | "Admin resolved the dispute in your favor. Funds moved to your wallet." |
| **Admin Decision: Force Released** | Buyer | Push, In-App | "Admin resolved the dispute in favor of the Delala." |
| **Admin Decision: Force Refunded** | Buyer | SMS, Push, In-App | "Admin resolved the dispute in your favor. Your refund is being processed." |
| **Admin Decision: Force Refunded** | Delala | Push, In-App | "Admin resolved the dispute in favor of the Buyer." |
| **Payout Processed (PAID)** | Delala | Push, In-App | "Your withdrawal of [Amount] ETB has been processed." |

---

## 8. Trust, Profiles & Data Retention

*   **FR-6.1 Rating System:** Upon Escrow transitioning to `RELEASED` or `FORCE_RELEASED`, the Buyer is prompted to rate the Delala (1–5 stars + optional text). Ratings are not triggered by `FORCE_REFUNDED`.
*   **FR-6.2 Delala Public Profile:** Delalas have a public-facing profile showing: Name, Avatar (from KYC selfie), Average Star Rating, total `COMPLETED` deals, and active Categories.
*   **FR-6.3 Data Retention:** Financial ledger entries and Escrow records are immutable and retained indefinitely. Account deletion requests act as soft-deletes, anonymizing PII (`name`, `email`, `phone` → SHA-256 hash) while retaining all foreign keys to preserve ledger integrity.

---

## 9. External Integrations & Failure Modes

| Integration | Primary Purpose | Failure Scenario | Required Mitigation |
| :--- | :--- | :--- | :--- |
| **Chapa API** | Checkout URLs & Refunds. | API timeout during checkout link generation. | Return `503 Service Unavailable`. Do **NOT** create an `EscrowTransaction` record. |
| **Chapa Webhook** | Payment confirmation. | Webhook arrives while platform is down. | Relies on Chapa's automated retry schedule (up to 72 hours). Endpoint must use `tx_ref` as idempotency key. |
| **SMS Gateway** | OTP & Alerts. | Provider drops SMS or delays > 2 mins. | Allow resend after 60s. If failure rate exceeds 15% in a 5-minute rolling window, route to secondary SMS provider. |
| **AWS S3** | Image/KYC/Evidence storage. | Upload fails during proposal or KYC submission. | Reject the submission cleanly. Do not store partial DB records referencing missing S3 keys. |

---

## 10. Non-Functional Requirements & Security Standards

### 10.1 Security & Fraud Prevention

*   **NFR-1.1 SMS Toll Fraud Protection:** The `/api/v1/auth/send-otp` endpoint must rate-limit by IP address (max 10 req/hr) and Phone Number (max 3 req/hr), protected by invisible reCAPTCHA v3.
*   **NFR-1.2 Brute-Force Protection:** Passwords hashed via `Bcrypt` or `Argon2id`. The login endpoint enforces a 15-minute lockout after 5 consecutive failed attempts per account.
*   **NFR-1.3 Webhook Cryptography:** All Chapa webhooks must be verified by computing an HMAC SHA256 of the raw request body using the Chapa Secret Key and comparing it against the `x-chapa-signature` header. Requests failing verification receive `401` immediately.
*   **NFR-1.4 Document Privacy:** KYC artifacts and dispute evidence are stored in a private S3 bucket and exposed exclusively via 15-minute pre-signed GET URLs generated server-side.

### 10.2 Data Integrity & Concurrency

*   **NFR-2.1 Webhook Idempotency:** The Chapa webhook handler uses `tx_ref` as an idempotency key. Duplicate `payment.success` events must return `200 OK` without re-executing ledger entries.
*   **NFR-2.2 Transactional Locking:** Every action that mutates a wallet balance or Escrow state must be wrapped in `DB::transaction()` with pessimistic locking (`->lockForUpdate()`) to prevent race conditions between concurrent HTTP requests and scheduled jobs.

### 10.3 API & Data Standards

*   **NFR-3.1 Versioning:** All routes prefixed with `/api/v1/`.
*   **NFR-3.2 Timestamps:** All dates transmitted in ISO 8601 UTC (e.g., `2026-10-09T14:20:00Z`). Clients localize to `Africa/Addis_Ababa` (EAT, UTC+3).
*   **NFR-3.3 Error Envelope:** All `4xx`/`5xx` responses follow: `{"message": "Human-readable summary", "errors": {"field": ["Validation detail"]}}`.
*   **NFR-3.4 Asynchronous Offloading:** Chapa API calls, SMS dispatch, email dispatch, and WebSocket broadcasts must never block the HTTP request lifecycle. All must be dispatched to a Redis-backed queue.

### 10.4 Performance

*   **NFR-4.1 API Latency:** 95th percentile of standard read/write endpoints must respond in < 250ms.
*   **NFR-4.2 Real-Time:** Chat messages and proposal mutations must be delivered via WebSockets (Laravel Reverb) over authenticated private channels. HTTP polling is strictly forbidden.

---

## 11. Assumptions & Architectural Trade-Offs

1.  **Central Escrow (vs. Chapa Split Payments):** Funds are absorbed into a platform holding wallet, not split via Chapa subaccounts. *Trade-off:* Full control over the 120-hour timer and dispute mediation, at the cost of increased regulatory responsibility as a central escrow agent.
2.  **Refund Fee Leakage:** On `FORCE_REFUND`, the Chapa processing fee is not recovered. *Trade-off:* Platform absorbs this as the cost of maintaining buyer trust.
3.  **NBE Payment Caps:** Ethiopian National Bank limits (e.g., ~300,000 ETB/day aggregate) may cause Chapa to reject large commissions. *Trade-off:* V1 accepts standard gateway failures on cap breaches rather than building installment escrow states.
4.  **No Seller Integration:** The system trusts that the physical transaction occurs offline. *Trade-off:* Dispute resolution relies entirely on human verification of offline banking evidence; the platform has zero on-chain visibility into the asset.
5.  **V1 Manual Payouts:** Delala withdrawals are processed manually by Admin via CSV export. *Trade-off:* Eliminates the complexity of automated bank transfer APIs in V1, at the cost of Admin operational overhead.