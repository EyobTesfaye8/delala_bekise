# Module 9 & 10: Ratings, Public Trust Profiles & Multi-Channel Notifications

This document details the frontend integration specifications for **Double-Blind Mutual Ratings & Trust Profiles (Module 9)** and **Multi-Channel Push Notifications & Device Token Management (Module 10)** for the Delala Platform.

---

## 1. Double-Blind Mutual Ratings (Module 9)

To prevent retaliatory negative reviews, ratings on completed jobs operate on a **Double-Blind Concealment Protocol (FR-6.1)**:
- After a job enters the `completed` state, both the Buyer and Delala have **14 days** to submit 1–5 stars and an optional written review.
- Any submitted review remains strictly **hidden** from both parties and the public until:
  1. **Both** the Buyer and Delala have submitted their reviews for that transaction, **OR**
  2. The **14-day** post-completion window has elapsed.
- Once revealed, reviews are factored into the Delala's public average star rating and profile.

### 1.1 Submit a Rating
- **Endpoint**: `POST /api/v1/jobs/{job}/ratings`
- **Auth**: Required (`auth:sanctum`). Must be the Job Buyer or the winning Delala.
- **Rules & Constraints**:
  - Job status must be `completed`.
  - Must be submitted within 14 days of job completion (`RATING_WINDOW_EXPIRED`).
  - Each participant can only rate the transaction once (`DUPLICATE_RATING`).
- **Request Body**:
```json
{
  "stars": 5,
  "review": "Excellent broker! Verified the property title and guided through every step."
}
```
- **Response when first party submits (Hidden / Awaiting Second Party) (201 Created)**:
```json
{
  "message": "Rating submitted. Reviews will remain hidden until the other party submits or 14 days elapse.",
  "both_submitted": false,
  "is_revealed": false,
  "rating": {
    "id": 14,
    "escrow_transaction_id": 8,
    "rater_id": 3,
    "ratee_id": 5,
    "stars": 5,
    "review": "Excellent broker! Verified the property title...",
    "is_revealed": false,
    "created_at": "2026-10-22T18:00:00Z"
  }
}
```
- **Response when second party submits (Both Revealed Immediately) (201 Created)**:
```json
{
  "message": "Rating submitted. Both parties have submitted, reviews are now visible!",
  "both_submitted": true,
  "is_revealed": true,
  "rating": {
    "id": 15,
    "escrow_transaction_id": 8,
    "rater_id": 5,
    "ratee_id": 3,
    "stars": 5,
    "is_revealed": true
  }
}
```

---

### 1.2 Delala Public Trust Profile (FR-6.2)
Publicly exposes broker reputation, KYC verification status, completed transaction count, and revealed reviews.

- **Endpoint**: `GET /api/v1/delalas/{delala_id}/profile`
- **Auth**: Public (No authentication required).
- **Success Response (200 OK)**:
```json
{
  "id": 5,
  "full_name": "Kebede Delala",
  "is_verified": true,
  "member_since": "2026-09-01T10:00:00Z",
  "completed_deals_count": 18,
  "average_rating": 4.9,
  "total_reviews_count": 16,
  "recent_reviews": [
    {
      "id": 14,
      "stars": 5,
      "review": "Extremely reliable and transparent with landlord contacts.",
      "created_at": "2026-10-20T12:00:00Z",
      "rater": {
        "id": 3,
        "full_name": "Abebe Buyer"
      }
    }
  ]
}
```

---

## 2. Multi-Channel Notifications & FCM Integration (Module 10)

The backend dispatches notifications through four coordinated channels:
1. **Push (FCM v1)**: Primary mobile channel using Google OAuth2 and Google Firebase Admin credentials.
2. **SMS (AfroMessage)**: OTP verification and time-critical transactional alerts (e.g. 96h countdown warning).
3. **Email (Resend)**: Account confirmation magic links, payment receipts, and dispute resolution summaries.
4. **In-App**: Standard database notifications available via `User::notifications`.

### 2.1 Register FCM Device Push Token
Mobile clients (Flutter / React Native) must register their FCM registration token after login:

- **Endpoint**: `POST /api/v1/users/fcm-token`
- **Auth**: Required (`auth:sanctum`).
- **Request Body**:
```json
{
  "fcm_token": "eX1aB9c...c8Z0Pq_device_registration_token"
}
```
- **Success Response (200 OK)**:
```json
{
  "message": "FCM device token registered successfully."
}
```

---

### 2.2 Domain Notification Matrix (§8 SRS)

| Trigger Event | Recipient | Channels | Message Content |
| :--- | :--- | :--- | :--- |
| `KycApproved` | Delala | In-App, Push | "Your KYC profile has been approved! You can now propose on jobs." |
| `KycRejected` | Delala | In-App, Push | "KYC submission rejected: [Reason]. Please re-upload documents." |
| `ProposalSubmitted` | Buyer | In-App, Push | "New proposal received on '[Job Title]' from [Broker Name]." |
| `CommissionUpdated` | Buyer, Delala | In-App, WebSocket | Real-time negotiation event with old vs. new commission. |
| `EscrowFunded` | Delala | In-App, Push, SMS | "Buyer funded [Amount] ETB in escrow. You may now begin work!" |
| `HandoverDeclared` | Buyer | In-App, Push | "Broker declared handover complete. 120-hour review window started." |
| `HandoverWarning (96h)`| Buyer | In-App, Push, SMS | "URGENT: 24h remaining to review deal before auto-release." |
| `DisputeOpened` | Delala | In-App, Push, Email | "Handover disputed. Upload third-party seller receipt within 48h." |
| `EscrowReleased` | Delala | In-App, Push | "Escrow released! [Amount] ETB credited to your Available Wallet." |
| `P1 Operational Alert` | Admins | FCM Topic (`admin_alerts`) | Critical ledger imbalance, unhandled webhook, worker lag. |
