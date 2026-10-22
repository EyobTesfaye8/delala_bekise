# Phase 7: Comprehensive Database Seeding & Admin Filament Panel

This guide details the **Real-World Taxonomy**, **Enterprise Demo Personas & Transactions**, and the **Filament Maker-Checker Administrative Resource** introduced in Phase 7.

---

## 1. Real-World Taxonomy & JSON Schemas (`TaxonomySeeder`)

The platform implements a 2-level category hierarchy. Leaf categories enforce write-time **Draft-07 JSON Schema** validation on job attribute payloads before submission.

### 1.1 Category Hierarchy

```
├── Real Estate (Parent, slug: 'real-estate')
│   ├── Residential Rental (Leaf, slug: 'residential-rental')
│   ├── Commercial Lease (Leaf, slug: 'commercial-lease')
│   └── Residential Sale (Leaf, slug: 'residential-sale')
└── Vehicles (Parent, slug: 'vehicles')
    ├── Passenger Cars (Leaf, slug: 'passenger-cars')
    └── Commercial Vehicles (Leaf, slug: 'commercial-vehicles')
```

### 1.2 JSON Schema Definitions

#### Residential Rental (`residential-rental`)
- `bedrooms` (`integer`, min: 0, required)
- `bathrooms` (`number`, min: 0, required)
- `furnished` (`boolean`, required)
- `backup_generator` (`boolean`, required)
- `parking` (`boolean`, required)

#### Commercial Lease (`commercial-lease`)
- `square_meters` (`number`, min: 1, required)
- `floor_number` (`integer`, required)
- `elevator` (`boolean`, required)

#### Residential Sale (`residential-sale`)
- `bedrooms` (`integer`, min: 0, required)
- `bathrooms` (`number`, min: 0, required)
- `square_meters` (`number`, min: 1, required)
- `property_type` (`string`, enum: `['villa', 'apartment', 'condominium', 'townhouse']`)
- `title_deed_verified` (`boolean`)

#### Passenger Cars (`passenger-cars`)
- `make` (`string`, minLength: 2, required)
- `model` (`string`, minLength: 1, required)
- `year` (`integer`, min: 1970, max: 2030, required)
- `transmission` (`string`, enum: `['automatic', 'manual']`, required)
- `fuel_type` (`string`, enum: `['petrol', 'diesel', 'electric', 'hybrid']`, required)
- `mileage` (`integer`, min: 0, required)

#### Commercial Vehicles (`commercial-vehicles`)
- `vehicle_type` (`string`, enum: `['pickup', 'truck', 'van', 'bus', 'trailer', 'machinery']`, required)
- `payload_capacity_tons` (`number`, min: 0, required)
- `year` (`integer`, min: 1970, max: 2030, required)
- `transmission` (`string`, enum: `['automatic', 'manual']`, required)
- `fuel_type` (`string`, enum: `['diesel', 'petrol', 'electric']`, required)
- `mileage` (`integer`, min: 0)

---

## 2. Pre-Configured Enterprise Personas (`EnterpriseDemoSeeder`)

All pre-configured test personas are seeded with password: `password123`.

| Persona | Role | Email | Phone Number | Status & Profile Details |
| :--- | :--- | :--- | :--- | :--- |
| **Senior Admin** | `admin` | `senior@delala.et` | `+251900000001` | Active, verified email/SMS. Authorized for Maker-Checker approvals. |
| **Junior Admin** | `admin` | `junior@delala.et` | `+251900000002` | Active, verified email/SMS. Initiates high-value operational actions. |
| **Verified Delala** | `delala` | `delala.verified@delala.et` | `+251911223344` | Full KYC approved (`KycApproved`), default CBE account (`1000123456789`). |
| **Unverified Delala**| `delala` | `delala.unverified@delala.et`| `+251922334455` | Pending KYC profile under review (`UnderReview`). |
| **Registered Buyer**| `buyer` | `buyer@delala.et` | `+251933445566` | Active buyer with funded escrows, live negotiations, and completed deals. |
| **Guest Buyer** | `guest` | *None* | `+251944556677` | Guest buyer session (`guest_uuid`), open job in Passenger Cars. |

---

## 3. Chart of Accounts & Balanced Double-Entry Ledger

The system initializes standard 4-digit Chart of Accounts (`srs_v2.md §5.1`):

| Code | Account Name | Type | Normal Balance | Description |
| :--- | :--- | :--- | :--- | :--- |
| `1010` | `system_chapa_holding` | Asset | Debit | Net cash settled into Chapa merchant account. |
| `1020` | `system_payout_clearing`| Asset | Debit | Cash in transit during batch bank payouts. |
| `2010` | `buyer_escrow_liability`| Liability | Credit | Unallocated buyer deposits held in trust. |
| `2020` | `delala_pending_wallet` | Liability | Credit | Locked escrow commissions in transit (95%). |
| `2030` | `delala_available_wallet`| Liability | Credit | Withdrawable broker earnings. |
| `4010` | `platform_revenue_wallet`| Equity | Credit | Platform's earned 5% gross commission. |

*Ledger Verification Command:*
```bash
php artisan delala:nightly-reconciliation
```
Verifies $\sum \text{Debits} = \sum \text{Credits}$ across all journal references.

---

## 4. Filament Maker-Checker Administrative Resource

### 4.1 Purpose & Policy (SRS §3 & AR-2)
Any administrative action that forces funds release or refund exceeding **10,000 ETB (1,000,000 Santim)** triggers the **Maker-Checker Protocol**:
1. The initiating administrator (e.g., Junior Admin) creates the action request (`AdminApprovalRequest` in `PENDING` state).
2. The request is routed to the Maker-Checker queue in the Filament Admin panel.
3. **Segregation Rule (Maker != Checker):** The initiating administrator CANNOT approve or reject their own request. A secondary administrator (e.g., Senior Admin) must independently review and sign off.

### 4.2 Filament Resource Structure
- **Resource Route:** `GET /admin/admin-approval-requests`
- **Class:** `App\Filament\Resources\AdminApprovalRequests\AdminApprovalRequestResource`
- **Navigation Label:** `Maker-Checker Approvals`
- **Icon:** `Heroicon::OutlinedShieldCheck`
- **Default Sort:** `created_at DESC` (newest pending requests appear at the top)
- **Security & Immutability:** `canCreate() => false`, `canDelete() => false`, `canDeleteAny() => false` (regulatory dual-control audit records are immutable).
- **Table Columns:**
  - `#` (ID)
  - `Action Type` (`FORCE_RELEASE` [info badge], `FORCE_REFUND` [danger badge])
  - `Amount (ETB / Santim)` (Primary formatted ETB amount, with exact Santim count displayed as secondary description)
  - `Requested By` (Relationship to Maker administrator)
  - `Status` (`PENDING` [warning], `APPROVED` [success], `REJECTED` [danger])
  - `Justification` (Operational notes submitted by Maker)
  - `Checker` (Approving administrator, toggleable)
  - `Decision At` (Timestamp of resolution, toggleable)
  - `Requested At` (Submission timestamp)
- **Filters:**
  - Status Filter (`PENDING`, `APPROVED`, `REJECTED`)
  - Action Type Filter (`FORCE_RELEASE`, `FORCE_REFUND`)
- **Row Actions:**
  - **Approve Action:** Requires modal confirmation. Triggers double-entry ledger release/refund and escrow state update. Strictly enforces `Maker != Checker` segregation using current panel authentication. Automatically hidden once a request is decided.
  - **Reject Action:** Prompts for mandatory `rejection_reason` (max 1000 characters). Updates request status to `REJECTED`. Strictly enforces `Maker != Checker` segregation. Automatically hidden once a request is decided.

---

## 5. Postman Collection & Seed Execution

### 5.1 Postman Maker-Checker Endpoints
- `GET {{baseUrl}}/admin/approvals?status=PENDING` — List pending high-value approval requests.
- `POST {{baseUrl}}/admin/approvals/{approval}/approve` — Senior Admin approval (enforces `maker != checker`).
- `POST {{baseUrl}}/admin/approvals/{approval}/reject` — Senior Admin rejection with `rejection_reason`.

### 5.2 Verification Commands

```bash
# Run all database seeds (idempotent, deterministic UUIDs)
php artisan db:seed

# Verify complete test suite
php artisan test --compact

# Code style formatting
vendor/bin/pint --format agent
```
