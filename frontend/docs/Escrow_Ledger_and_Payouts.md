# Module 7 & 8: Escrow State Machine, Double-Entry Ledger & Maker-Checker Governance

This document details the frontend and administrative API specifications for **Escrow Lifecycle & Double-Entry Ledger (Module 7)** and **Broker Payouts & Back-Office Maker-Checker Governance (Module 8)** for the Delala Platform.

---

## 1. Escrow Actions & Settlement (Module 7)

### 1.1 Accept Proposal Item & Initiate Escrow
- **Endpoint**: `POST /api/v1/proposals/{proposal}/items/{item}/accept`
- **Auth**: Required (`auth:sanctum`). Role: `buyer`.
- **Description**: Creates pending escrow record, splits 95% Delala Amount and 5% Platform Fee, and returns Chapa payment checkout URL.
- **Success Response (200 OK)**:
```json
{
  "message": "Item accepted, please proceed to payment.",
  "checkout_url": "https://checkout.chapa.co/checkout/tx_66ef12..."
}
```

---

### 1.2 Declare Handover Complete (Broker)
- **Endpoint**: `POST /api/v1/escrow/{escrow}/handover`
- **Auth**: Required (`auth:sanctum`). Role: `delala` (must be the assigned broker).
- **Description**: Transitions escrow from `funded` to `handover_declared` and initiates the 120-hour auto-release countdown timer (FR-5.1).
- **Success Response (200 OK)**:
```json
{
  "message": "Handover declared. Buyer has 120 hours to review."
}
```

---

### 1.3 Release Funds (Buyer)
- **Endpoint**: `POST /api/v1/escrow/{escrow}/release`
- **Auth**: Required (`auth:sanctum`). Role: `buyer` (must be the job owner).
- **Description**: Transitions escrow from `handover_declared` to `released`, completes job, and records double-entry ledger journal **JNL-2** (moving funds from `delala_pending_wallet` to `delala_available_wallet_{delala_id}`).
- **Success Response (200 OK)**:
```json
{
  "message": "Funds released successfully."
}
```

---

### 1.4 Pre-Handover Cancellation Workflow (SRS §4.3 & §7.5)
Enables pre-handover cancellation while escrow is in `funded` state.

#### 1.4.1 Buyer Requests Cancellation
- **Endpoint**: `POST /api/v1/escrow/{escrow}/cancel-request`
- **Auth**: Required (`auth:sanctum`). Buyer only.
- **Request Body**:
```json
{
  "reason": "Found alternative property offline."
}
```
- **Success Response (200 OK)**:
```json
{
  "message": "Cancellation requested. Delala has 48 hours to respond.",
  "escrow": {
    "id": 12,
    "status": "cancellation_requested",
    "cancellation_requested_at": "2026-10-22T17:00:00Z",
    "cancellation_deadline_at": "2026-10-24T17:00:00Z"
  }
}
```

#### 1.4.2 Broker Approves Cancellation (Immediate Refund)
- **Endpoint**: `POST /api/v1/escrow/{escrow}/cancel-approve`
- **Auth**: Required (`auth:sanctum`). Delala only.
- **Description**: Transitions escrow to `force_refunded`, cancels job, triggers Chapa refund API, and executes JNL-3 double-entry ledger reversal.
- **Success Response (200 OK)**:
```json
{
  "message": "Cancellation approved. Escrow refunded and job canceled."
}
```

#### 1.4.3 Broker Rejects Cancellation
- **Endpoint**: `POST /api/v1/escrow/{escrow}/cancel-reject`
- **Auth**: Required (`auth:sanctum`). Delala only.
- **Request Body**:
```json
{
  "rejection_reason": "I already arranged the inspection and paid the third-party fee."
}
```
- **Success Response (200 OK)**: Transitions escrow to `disputed` for administrative adjudication.

---

### 1.5 Dispute Initiation & Third-Party Seller Evidence (FR-5.2, FR-5.3)

#### 1.5.1 Buyer Disputes Handover
- **Endpoint**: `POST /api/v1/escrow/{escrow}/dispute`
- **Auth**: Required (`auth:sanctum`). Buyer only (during `handover_declared` state).
- **Request Body**:
```json
{
  "reason": "Property owner never showed up for lease signing.",
  "reason_code": "SELLER_NO_SHOW",
  "evidence_keys": ["evidence/buyer_proof_1.pdf"]
}
```
*Valid `reason_code` options: `ITEM_NOT_AS_DESCRIBED`, `SELLER_NO_SHOW`, `DEAL_FELL_THROUGH`.*

#### 1.5.2 Broker Uploads Third-Party Seller Evidence
- **Endpoint**: `POST /api/v1/escrow/{escrow}/dispute-evidence`
- **Auth**: Required (`auth:sanctum`). Delala only.
- **Domain Rule (FR-5.3)**: Because principal transaction flows between Buyer and Third-Party Seller, valid evidence must demonstrate transfer between Buyer Account and Seller Account or a signed physical contract/bill of sale.
- **Request Body**:
```json
{
  "evidence_keys": [
    "evidence/cbe_buyer_to_seller_receipt.pdf",
    "evidence/signed_bill_of_sale.jpg"
  ],
  "notes": "Attached bank receipt showing transfer from Buyer to Landlord."
}
```
- **Success Response (200 OK)**:
```json
{
  "message": "Dispute evidence uploaded successfully. Admin will review proof of transfer between Buyer and Third-Party Seller.",
  "dispute_evidence_s3_keys": [
    "evidence/cbe_buyer_to_seller_receipt.pdf",
    "evidence/signed_bill_of_sale.jpg"
  ]
}
```

---

## 2. Broker Payouts (Module 8)

### 2.1 View Real-Time Balance & Payout History
- **Endpoint**: `GET /api/v1/payouts`
- **Auth**: Required (`auth:sanctum`). Role: `delala`.
- **Success Response (200 OK)**:
```json
{
  "available_balance_santim": 250000,
  "available_balance_etb": 2500.0,
  "payout_requests": {
    "data": [
      {
        "id": 4,
        "amount_santim": 100000,
        "bank_name": "Commercial Bank of Ethiopia",
        "account_number": "1000123456789",
        "payment_method": "bank_transfer",
        "status": "paid",
        "paid_at": "2026-10-22T15:00:00Z"
      }
    ]
  }
}
```

---

### 2.2 Request Withdrawal (FR-2.5)
- **Endpoint**: `POST /api/v1/payouts/request`
- **Auth**: Required (`auth:sanctum`). Role: `delala`.
- **Constraints & Rules**:
  - **Floor**: Minimum 50,000 Santim (500 ETB).
  - **Max Pending**: Maximum 1 active pending withdrawal at a time (`DUPLICATE_PAYOUT_REQUEST`).
  - **Immediate Ledger Reservation (JNL-4A)**: Immediately debits `delala_available_wallet_{id}` and credits `payout_clearing_account` so funds cannot be double-spent.
- **Request Body**:
```json
{
  "amount_santim": 100000,
  "payout_destination_id": 2
}
```
*Note: `payout_destination_id` is optional; if omitted, the registered default destination is used.*

---

## 3. Back-Office Maker-Checker Governance & Admin Operations (Module 8)

### 3.1 Force Release / Force Refund with Maker-Checker Threshold
Admins can resolve disputed escrows. Any single action exceeding **10,000 ETB (1,000,000 Santim)** triggers the **Maker-Checker Protocol (SRS §3 & AR-2)**.

- **Endpoints**:
  - `POST /api/v1/admin/escrow/{escrow}/force-release`
  - `POST /api/v1/admin/escrow/{escrow}/force-refund`
- **Auth**: Required (`auth:sanctum`). Role: `admin`.
- **Threshold Behavior**:
  - **$\le 10,000$ ETB**: Immediately executed by requesting admin.
  - **$> 10,000$ ETB**: Returns `202 Accepted` and enqueues an `AdminApprovalRequest` requiring secondary Senior Admin sign-off.
- **202 Accepted Response (High-Value Maker-Checker)**:
```json
{
  "message": "Action exceeds 10,000 ETB Maker-Checker threshold. Queued for Senior Admin approval.",
  "requires_maker_checker": true,
  "approval_request": {
    "id": 1,
    "action_type": "FORCE_RELEASE",
    "amount_santim": 1900000,
    "status": "PENDING",
    "requested_by_id": 2,
    "justification_note": "Verified paper contract signed by both parties."
  }
}
```

---

### 3.2 Maker-Checker Approvals Queue
- **List Pending Approvals**: `GET /api/v1/admin/approvals?status=PENDING`
- **Approve Request**: `POST /api/v1/admin/approvals/{approval}/approve`
- **Reject Request**: `POST /api/v1/admin/approvals/{approval}/reject`
- **Maker-Checker Segregation Rule**: The administrator who created the request CANNOT approve it. Doing so returns `403 Forbidden` (`MAKER_CHECKER_VIOLATION`).

---

### 3.3 Batch Bank Payout Operations (AR-3)
Ethiopian commercial banks lack open disbursement APIs. Delala processes payouts through verified batch CSV exports and reference reconciliation.

#### 3.3.1 Export Batch CSV for Bank Transfer
- **Endpoint**: `GET /api/v1/admin/payouts/export-csv`
- **Auth**: Admin only.
- **Returns**: Streamed CSV file (`delala_payout_batch_YYYYMMDD_HHMMSS.csv`) formatted with:
  `Payout ID,Delala Name,Delala Phone,Bank Name,Account Number,Telebirr Number,Payment Method,Amount (Santim),Amount (ETB),Requested At`.

#### 3.3.2 Settle Payout with Bank Reference
- **Endpoint**: `POST /api/v1/admin/payouts/{payout}/settle`
- **Auth**: Admin only.
- **Description**: Transitions payout to `paid` and records double-entry journal **JNL-4B** (clearing `payout_clearing_account` against `system_chapa_holding`).
- **Request Body**:
```json
{
  "bank_reference": "FT261022998877",
  "admin_note": "Settled via CBE online banking batch transfer"
}
```

---

### 3.4 User Suspension Kill-Switch (AR-4)
- **Suspend User**: `POST /api/v1/admin/users/{user}/suspend`
  - Immediately revokes all Sanctum authentication tokens.
  - Sets user status to `suspended`.
  - Automatically withdraws any open proposals submitted by the user.
- **Reactivate User**: `POST /api/v1/admin/users/{user}/reactivate`
