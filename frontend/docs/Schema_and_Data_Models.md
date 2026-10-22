# Data Models & Schema - Frontend Integration Guide

**Project:** Delala Platform (Escrow-Backed Broker Marketplace)  
**SRS Reference:** `srs_v2.md` (Enterprise Production Grade v4.0)  
**Module:** 1 - Database Schema & Data Integrity  

---

## 1. Global Data Conventions

### 1.1 Currency: Santim Only (`BIGINT`)
*   All monetary amounts across the API are represented as integers in **Santim** (1 ETB = 100 Santim).
*   **Do not send floating-point numbers** (e.g. `150.50`). Send `15050` instead.
*   *Frontend Display Rule:* Always divide by 100 for display (`(amountSantim / 100).toLocaleString('en-US', { minimumFractionDigits: 2 })`).
*   *Minimum Commission Floor:* All proposal items enforce a minimum commission of **10,000 Santim (100.00 ETB)**.

### 1.2 Public Identifiers: UUIDv7
*   To prevent sequential ID enumeration (OWASP IDOR / BOLA compliance), all public-facing resource references use **UUIDv7**:
    *   Job URLs: `/api/v1/jobs/{uuid}`
    *   Escrow URLs: `/api/v1/escrow/{uuid}`
    *   Proposal Item URLs: `/api/v1/proposals/{proposal}/items/{uuid}`

### 1.3 Timestamps
*   All timestamps returned by the API are UTC strings formatted per **ISO 8601** (e.g. `2026-10-22T15:30:00Z`).
*   The frontend should format these in Ethiopian Local Time (`Africa/Addis_Ababa`, UTC+3 / EAT).

---

## 2. Core Entities & Field Specifications

### 2.1 User
Represents any actor on the platform (Guest Buyer, Registered Buyer, Delala, or Admin).

| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `integer` | Internal unique user ID. |
| `full_name` | `string` | User's legal full name (required for KYC and bank payouts). |
| `phone` | `string` | International E.164 phone number (e.g. `+251911223344`). |
| `email` | `string \| null` | Email address (null for Guests, required for Registered users). |
| `type` | `enum` | `'guest'`, `'buyer'`, `'delala'`, `'admin'`. |
| `status` | `enum` | `'unverified'` (awaiting SMS/Email verify), `'active'`, `'suspended'`. |
| `guest_uuid` | `string \| null` | UUID for guest session recovery. |
| `avatar_s3_key` | `string \| null` | S3 key for profile photo. |
| `circumvention_strike_count` | `integer` | Anti-circumvention strike count (3 strikes flags for admin review). |
| `locked_until` | `datetime \| null` | Lockout expiration if account is temporarily locked (5 failed logins). |
| `created_at` | `datetime` | Account creation timestamp. |

---

### 2.2 DelalaJob
Represents a buyer's request for goods or services.

| Field | Type | Description |
| :--- | :--- | :--- |
| `uuid` | `string (UUID)` | Public identifier for job cards and detail pages. |
| `buyer_id` | `integer` | User ID of the buyer. |
| `category_id` | `integer` | Leaf category ID. |
| `title` | `string` | Job title (e.g. "Toyota Vitz 2018 automatic in Addis"). |
| `description` | `string \| null` | Detailed description. |
| `status` | `enum` | `'draft'`, `'open'`, `'negotiating'`, `'contracted'`, `'completed'`, `'canceled'`. |
| `canceled_reason` | `string \| null` | Reason if canceled (e.g. `'EXPIRED'`, `'BUYER_CANCELED'`). |
| `location` | `string \| null` | General location text. |
| `location_region` | `string \| null` | Ethiopian Region (e.g. `'Addis Ababa'`, `'Oromia'`). |
| `location_subcity` | `string \| null` | Subcity or Zone (e.g. `'Bole'`, `'Kirkos'`). |
| `budget_min_santim` | `integer \| null`| Minimum budget in Santim. |
| `budget_max_santim` | `integer \| null`| Maximum budget in Santim. |
| `attributes` | `object (JSONB)`| Category-specific fields validated against `category.json_schema`. |
| `proposal_count` | `integer` | Count of active proposals submitted by Delalas. |

#### Job State Transitions:
```
DRAFT -> OPEN -> NEGOTIATING -> CONTRACTED -> COMPLETED
  |        |          |              |
  v        v          v              v
CANCELED CANCELED  CANCELED      CANCELED (Only if escrow refunded)
```

---

### 2.3 Category
2-level hierarchical taxonomy (e.g. `Vehicles` -> `Passenger Cars`).

| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `integer` | Category ID. |
| `parent_id` | `integer \| null` | Parent category ID (null for top-level). |
| `name` | `string` | Display name (e.g. "Passenger Cars", "Residential Rental"). |
| `slug` | `string` | URL-friendly slug. |
| `is_leaf` | `boolean` | `true` if this category accepts job posts. |
| `json_schema` | `object \| null` | **JSON Schema (Draft-07)** definition used to dynamically render input forms on the frontend. |

---

### 2.4 Proposal & ProposalItem
A Delala submits 1 Proposal containing 1 to 10 Proposal Items.

#### Proposal:
| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `integer` | Internal proposal ID. |
| `job_id` | `integer` | ID of the job being proposed on. |
| `delala_id` | `integer` | User ID of the Delala. |
| `status` | `enum` | `'pending'`, `'negotiating'`, `'accepted'`, `'rejected'`, `'withdrawn'`. |
| `cover_note` | `string \| null` | Delala's pitch note. |

#### ProposalItem:
| Field | Type | Description |
| :--- | :--- | :--- |
| `uuid` | `string (UUID)` | Public identifier for item cards. |
| `item_name` | `string` | Name of the specific asset/service (e.g. "Silver 2018 Toyota Vitz"). |
| `description` | `string \| null` | Item condition, specs, or details. |
| `text_location` | `string \| null` | Physical location where item can be inspected. |
| `estimated_item_price_santim` | `integer` | Informational estimated principal price (ETB $\times 100$). |
| `commission_amount_santim` | `integer` | **Delala's commission fee in Santim** (minimum `10000`). |
| `image_s3_keys` | `array of string`| Up to 5 image S3 keys. |
| `status` | `enum` | `'pending'`, `'accepted_for_escrow'`, `'rejected'`. |

---

### 2.5 ProposalItemPriceHistory
Tracks in-chat commission negotiations.

| Field | Type | Description |
| :--- | :--- | :--- |
| `proposal_item_id` | `integer` | ID of the item negotiated. |
| `old_amount_santim`| `integer` | Previous commission amount. |
| `new_amount_santim`| `integer` | Updated commission amount. |
| `changed_by_id` | `integer` | Delala user ID who mutated the price. |
| `created_at` | `datetime` | When the mutation occurred. |

---

### 2.6 EscrowTransaction
The core financial escrow contract holding the commission.

| Field | Type | Description |
| :--- | :--- | :--- |
| `uuid` | `string (UUID)` | Public identifier. |
| `job_id` | `integer` | Associated job. |
| `proposal_item_id` | `integer` | The specific item funded. |
| `buyer_id` | `integer` | Buyer who paid. |
| `delala_id` | `integer` | Delala who will receive payout. |
| `commission_amount_santim` | `integer` | Total commission deposited by Buyer (100%). |
| `platform_fee_santim` | `integer` | Platform cut (5%). |
| `delala_amount_santim` | `integer` | Net amount payable to Delala (95%). |
| `status` | `enum` | See Escrow State Machine below. |
| `chapa_tx_ref` | `string \| null` | Unique Chapa payment transaction reference. |
| `chapa_checkout_url` | `string \| null`| Redirect URL to Chapa payment portal. |
| `chapa_paid_at` | `datetime \| null`| When Chapa webhook confirmed payment. |
| `handover_declared_at` | `datetime \| null`| When Delala declared offline transaction concluded. |
| `auto_release_at` | `datetime \| null`| Strict 120-hour countdown deadline for auto-release. |
| `cancellation_requested_at`| `datetime \| null`| When Buyer requested pre-handover cancellation. |
| `cancellation_deadline_at` | `datetime \| null`| Strict 48-hour deadline for Delala to respond to cancellation. |
| `dispute_reason_code` | `enum \| null` | `'ITEM_NOT_AS_DESCRIBED'`, `'SELLER_NO_SHOW'`, `'DEAL_FELL_THROUGH'`. |
| `dispute_evidence_s3_keys`| `array of string`| Up to 5 uploaded buyer-to-seller transfer receipts. |

#### Escrow States:
*   `pending_payment`: Awaiting payment on Chapa.
*   `funded`: Webhook confirmed; funds held safely in escrow.
*   `cancellation_requested`: Buyer asked to cancel before handover (48h timer).
*   `handover_declared`: Delala finished deal (120h countdown running).
*   `released`: Released to Delala available wallet.
*   `disputed`: Handover disputed or cancellation rejected; funds frozen.
*   `force_released`: Admin ruled for Delala.
*   `force_refunded`: Admin ruled for Buyer (or 48h cancel timer expired).
*   `refund_failed`: Chapa refund failed (escalated to operations).

---

### 2.7 PayoutDestination
Delala's verified Ethiopian bank or Telebirr account for receiving withdrawals.

| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `integer` | Destination ID. |
| `bank_code` | `string` | Bank identifier (`'CBE'`, `'AWASH'`, `'BOA'`, `'TELEBIRR'`, etc.). |
| `bank_name` | `string` | Display name (e.g. "Commercial Bank of Ethiopia"). |
| `account_number` | `string` | Bank account number or Telebirr phone. |
| `account_holder_name` | `string` | Must match verified KYC name. |
| `is_default` | `boolean` | Primary payout destination. |
| `verified_at` | `datetime \| null` | Timestamp when Admin approved first payout match. |

---

### 2.8 Chat & ChatMessage

#### Chat:
| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `integer` | Channel ID. |
| `proposal_id` | `integer` | Associated proposal. |
| `is_read_only` | `boolean` | `true` when job is contracted to another Delala or rejected. |

#### ChatMessage:
| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | `integer` | Message ID. |
| `sender_id` | `integer` | User ID of sender. |
| `type` | `enum` | `'text'`, `'image'`, `'system'`. |
| `body` | `string \| null` | Message text. |
| `metadata` | `object \| null` | Additional payload (e.g. price change metadata). |
| `created_at` | `datetime` | Timestamp. |

*Anti-Circumvention Rule:* Before the job reaches `contracted` state, messages containing phone numbers, telegram handles, or emails are rejected with `422 Unprocessable Entity`. Verified phone numbers are automatically revealed in the chat header once escrow reaches `funded`.
