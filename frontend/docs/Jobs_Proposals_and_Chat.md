# Module 5 & 6: Jobs, Proposals, and Anti-Circumvention Chat

This document details the frontend integration specifications for **Job Posting & Discovery (Module 5)** and **Proposal Submission, In-Chat Negotiation & Anti-Circumvention Protection (Module 6)** for the Delala Platform.

---

## 1. Job Posting & Discovery (Module 5)

### 1.1 List / Search Jobs (Feed)
Cursor-paginated public feed of active jobs. Accessible by guests, buyers, and brokers.

- **Endpoint**: `GET /api/v1/jobs`
- **Auth**: Optional (`auth:sanctum`). If authenticated as an approved or unapproved Delala, jobs where the Delala has already submitted an active proposal are automatically omitted (FR-3.4).
- **Query Parameters**:
  - `cursor` (string, optional): Pagination cursor (`next_cursor`).
  - `q` (string, optional): Full-text keyword search against job `title` and `description`.
  - `category_id` (integer, optional): Filter by category ID.
  - `location_region` (string, optional): e.g., `Addis Ababa`.
  - `location_subcity` (string, optional): e.g., `Bole`, `Kirkos`.
  - `location` (string, optional): Freeform location filter.
  - `min_budget` or `budget_min_santim` (integer, optional): Min budget in Santim (1 ETB = 100 Santim).
  - `max_budget` or `budget_max_santim` (integer, optional): Max budget in Santim.
- **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 14,
      "buyer_id": 3,
      "category_id": 5,
      "title": "3-Bedroom Apartment in Bole Rwanda",
      "description": "Looking for a furnished apartment with standby generator.",
      "location": "Bole Rwanda",
      "location_region": "Addis Ababa",
      "location_subcity": "Bole",
      "budget_min_santim": 5000000,
      "budget_max_santim": 8000000,
      "budget_min": 50000.0,
      "budget_max": 80000.0,
      "attributes": {
        "bedrooms": 3,
        "furnished": true,
        "backup_generator": true
      },
      "status": "open",
      "created_at": "2026-10-22T14:30:00Z",
      "category": {
        "id": 5,
        "name": "Apartments for Rent",
        "slug": "apartments-rent",
        "parent_id": 1
      }
    }
  ],
  "path": "https://api.delala.et/api/v1/jobs",
  "per_page": 15,
  "next_cursor": "eyJpZCI6MTQsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
  "next_page_url": "https://api.delala.et/api/v1/jobs?cursor=...",
  "prev_cursor": null,
  "prev_page_url": null
}
```

---

### 1.2 Get Job Details
- **Endpoint**: `GET /api/v1/jobs/{id}`
- **Auth**: Optional.
- **Success Response (200 OK)**:
```json
{
  "id": 14,
  "buyer_id": 3,
  "category_id": 5,
  "title": "3-Bedroom Apartment in Bole Rwanda",
  "description": "Looking for a furnished apartment with standby generator.",
  "location": "Bole Rwanda",
  "location_region": "Addis Ababa",
  "location_subcity": "Bole",
  "budget_min_santim": 5000000,
  "budget_max_santim": 8000000,
  "budget_min": 50000.0,
  "budget_max": 80000.0,
  "attributes": {
    "bedrooms": 3,
    "furnished": true
  },
  "status": "open",
  "created_at": "2026-10-22T14:30:00Z",
  "category": {
    "id": 5,
    "name": "Apartments for Rent",
    "slug": "apartments-rent",
    "parent_id": 1
  },
  "buyer": {
    "id": 3,
    "full_name": "Abebe Bikila",
    "type": "buyer",
    "created_at": "2026-10-01T10:00:00Z"
  }
}
```

---

### 1.3 Create a Job (Post Requirement)
- **Endpoint**: `POST /api/v1/jobs`
- **Auth**: Required (`auth:sanctum`). Must be Buyer or Guest.
- **Constraints & Rules**:
  - **Leaf Category Only**: Jobs can only be assigned to leaf subcategories (`is_leaf: true`). Submitting a parent category returns `422 Unprocessable Content`.
  - **JSON Schema Validation (FR-3.2)**: `attributes` must strictly comply with the category's `json_schema` definition. Missing required properties or type mismatches return `422`.
  - **Guest Rate Limit**: Guest accounts (`type: guest`) are strictly limited to **2 active open jobs**. Attempting to create a third returns `403 Forbidden` with `GUEST_JOB_LIMIT_REACHED`.
  - **Currency**: Santim amounts are stored directly. If `budget_min` or `budget_max` (ETB decimals) are passed, they are automatically normalized to Santim.
- **Request Body**:
```json
{
  "category_id": 5,
  "title": "Need 2 Bed in Bole",
  "description": "Quiet neighborhood preferred",
  "location": "Bole Brass",
  "location_region": "Addis Ababa",
  "location_subcity": "Bole",
  "budget_min_santim": 3000000,
  "budget_max_santim": 4500000,
  "attributes": {
    "bedrooms": 2,
    "furnished": true
  }
}
```
- **Success Response (201 Created)**:
```json
{
  "message": "Job created successfully",
  "job": {
    "id": 15,
    "buyer_id": 3,
    "category_id": 5,
    "title": "Need 2 Bed in Bole",
    "status": "open",
    "budget_min_santim": 3000000,
    "budget_max_santim": 4500000,
    "attributes": {
      "bedrooms": 2,
      "furnished": true
    },
    "created_at": "2026-10-22T17:00:00Z"
  }
}
```
- **Error Responses**:
  - `403 Forbidden` (`GUEST_JOB_LIMIT_REACHED`):
    ```json
    {
      "message": "Guest buyers may only have up to 2 active open jobs. Please upgrade your account to post more.",
      "error_code": "GUEST_JOB_LIMIT_REACHED"
    }
    ```
  - `422 Unprocessable Content` (Parent category):
    ```json
    {
      "message": "Jobs must be posted under a subcategory (leaf category)."
    }
    ```
  - `422 Unprocessable Content` (Schema validation failure):
    ```json
    {
      "message": "The provided attributes do not conform to the category schema.",
      "errors": [
        "The required property 'bedrooms' is missing."
      ]
    }
    ```

---

## 2. Proposals & Broker Offers (Module 6)

### 2.1 Submit Proposal on a Job
- **Endpoint**: `POST /api/v1/jobs/{job_id}/proposals`
- **Auth**: Required (`auth:sanctum`). Role must be `delala` with **approved KYC** (`EnsureKycApproved`).
- **Constraints & Rules**:
  - **KYC Gate**: Unverified Delalas receive `403 Forbidden` (`Your KYC profile must be approved to perform this action.`).
  - **1 Active Proposal Per Job (FR-4.1)**: Only 1 active proposal per Delala per job is permitted. Duplicate submission returns `422` with `DUPLICATE_PROPOSAL`.
  - **Commission Floor (SRS §6.1)**: Every item must have `commission_amount_santim >= 10000` (100 ETB).
- **Request Body**:
```json
{
  "cover_letter": "I have 2 verified houses matching your criteria with full amenities.",
  "items": [
    {
      "item_name": "Property 1 - Bole Rwanda",
      "description": "Fully furnished, 3 bed, backup generator",
      "commission_amount_santim": 50000
    },
    {
      "item_name": "Property 2 - Bole Atlas",
      "description": "Modern kitchen, security guard included",
      "commission_amount_santim": 60000
    }
  ]
}
```
- **Success Response (201 Created)**:
```json
{
  "message": "Proposal submitted successfully",
  "proposal": {
    "id": 20,
    "job_id": 15,
    "delala_id": 5,
    "status": "pending",
    "cover_letter": "I have 2 verified houses matching your criteria...",
    "created_at": "2026-10-22T17:10:00Z",
    "items": [
      {
        "id": 31,
        "item_name": "Property 1 - Bole Rwanda",
        "commission_amount_santim": 50000,
        "status": "pending"
      },
      {
        "id": 32,
        "item_name": "Property 2 - Bole Atlas",
        "commission_amount_santim": 60000,
        "status": "pending"
      }
    ]
  }
}
```

---

### 2.2 Mutate Proposal Item Commission (In-Chat Negotiation)
Allows the Delala to adjust an item's commission fee during chat negotiation (FR-4.4).
- **Endpoint**: `PATCH /api/v1/proposals/{proposal}/items/{item}/commission`
- **Auth**: Required (`auth:sanctum`). Only the proposal's Delala may call this.
- **Constraints**:
  - Item must be in `pending` status.
  - New commission must be $\ge 10,000$ Santim.
  - Persists an immutable price history record in `proposal_item_price_histories`.
  - Emits real-time WebSocket event `CommissionUpdated` on private channel `job.{job_id}.chat`.
- **Request Body**:
```json
{
  "commission_amount_santim": 40000
}
```
- **Success Response (200 OK)**:
```json
{
  "message": "Commission updated successfully",
  "item": {
    "id": 31,
    "proposal_id": 20,
    "commission_amount_santim": 40000,
    "status": "pending"
  },
  "old_amount_santim": 50000,
  "new_amount_santim": 40000
}
```

---

### 2.3 Withdraw Proposal (Delala)
- **Endpoint**: `POST /api/v1/proposals/{proposal}/withdraw`
- **Auth**: Required. Delala only. Allowed only when proposal is `pending`.
- **Success Response (200 OK)**:
```json
{
  "message": "Proposal withdrawn"
}
```

---

### 2.4 Reject Proposal (Buyer)
- **Endpoint**: `POST /api/v1/proposals/{proposal}/reject`
- **Auth**: Required. Job Buyer only. Transitions proposal to `rejected` and permanently marks associated chat channel as `is_read_only: true`.
- **Success Response (200 OK)**:
```json
{
  "message": "Proposal rejected"
}
```

---

## 3. Negotiation Chat & Anti-Circumvention (FR-4.3, FR-4.5)

### 3.1 Get or Create Chat Channel
- **Endpoint**: `GET /api/v1/proposals/{proposal}/chat`
- **Auth**: Required. Must be the Buyer or Proposal's Delala.
- **Buyer-First Gate (FR-4.3)**:
  - Delala cannot initiate the chat room. If a Delala calls this endpoint before a chat exists, the server returns `403 Forbidden` with:
    `{"message": "The buyer must initiate the negotiation chat first."}`
  - When the Buyer calls this endpoint, the chat room is created, and both the Proposal and Job transition to the `negotiating` state.
- **Contact Details Gate (FR-4.5)**:
  - Phone numbers are completely redacted (`contact_details: null`) until Escrow is Funded (`is_contracted: true`).
- **Success Response (200 OK)**:
```json
{
  "chat": {
    "id": 8,
    "proposal_id": 20,
    "buyer_id": 3,
    "delala_id": 5,
    "is_read_only": false,
    "created_at": "2026-10-22T17:15:00Z",
    "messages": []
  },
  "contact_details": null,
  "is_contracted": false
}
```
*Note: When `is_contracted: true`, `contact_details` provides `{"buyer_phone": "+251911111111", "delala_phone": "+251922222222"}`.*

---

### 3.2 Send Chat Message (With Anti-Circumvention Protection)
- **Endpoint**: `POST /api/v1/chats/{chat}/messages`
- **Auth**: Required (`auth:sanctum`). Only chat participants can send messages.
- **Pre-Contract Circumvention Filter (FR-4.5)**:
  - Scans `body` for:
    - Ethiopian phone numbers (`09...`, `07...`, `+251...`)
    - Spelled-out phone numbers in English/Amharic (`zero nine one one...`)
    - Telegram handles or links (`@handle`, `t.me/...`)
    - Email addresses (`...@...`)
  - If prohibited contact information is detected prior to contract funding:
    - Request is rejected with `422 Unprocessable Content` and `error_code: CIRCUMVENTION_DETECTED`.
    - User's `circumvention_strike_count` is incremented.
    - If strike count reaches 3, an alert is logged for administrator review.
- **Post-Contract Exemption**: Once escrow is funded and job status is `contracted`, off-platform contact sharing is permitted.
- **Request Body**:
```json
{
  "type": "text",
  "body": "Can you provide the exact floor plan for Property 1?",
  "metadata": {}
}
```
- **Success Response (201 Created)**:
```json
{
  "message": "Sent successfully",
  "data": {
    "id": 54,
    "chat_id": 8,
    "sender_id": 3,
    "type": "text",
    "body": "Can you provide the exact floor plan for Property 1?",
    "metadata": null,
    "created_at": "2026-10-22T17:16:00Z"
  }
}
```
- **Circumvention Blocked Response (422 Unprocessable Content)**:
```json
{
  "message": "Sharing contact details before funding escrow violates platform rules.",
  "error_code": "CIRCUMVENTION_DETECTED",
  "strike_count": 1
}
```

---

## 4. WebSocket & Broadcast Events
Frontend clients using Laravel Echo can subscribe to the following private channels:

1. **`job.{job_id}.chat`**:
   - `ChatMessageSent`: Dispatched when a participant posts a new message.
   - `CommissionUpdated`: Dispatched when a Delala adjusts an item's commission fee. Contains `item`, `old_amount_santim`, `new_amount_santim`.
