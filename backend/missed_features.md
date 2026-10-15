# Missed / Gap Features (vs SRS v2.0)

## Schema Gaps

### 1. `delala_jobs` table
- Missing `uuid` column (SRS NFR-SEC-3: UUIDv7 for all public IDs)
- Missing `location_region` and `location_subcity` columns (SRS FR-3.3 requires separate fields for filtering)
- Missing `canceled_reason` column (EXPIRED | BUYER_CANCELED etc.)
- `budget_min/budget_max` stored as DECIMAL — should be BIGINT Santim per SRS §5.1

### 2. `proposal_items` table
- Missing `uuid` column (public ID requirement)
- Missing `estimated_item_price_santim` column (SRS FR-4.1 — informational field)
- Missing `proposal_item_price_histories` table (SRS FR-4.4 — commission mutation audit trail)

### 3. `kyc_profiles` table
- Missing `id_back_s3_key` — SRS FR-2.3 requires Front AND Back of ID document

### 4. `users` table
- Missing `circumvention_strike_count` integer column (SRS FR-4.5 — 3 strikes = admin flag)
- Missing `login_attempt_count` and `locked_until` columns (SRS NFR-SEC-2 — 5 failures = 15 min lockout)
- Missing `totp_secret` and `totp_enabled_at` columns (Admin TOTP 2FA)

### 5. `escrow_transactions` table
- Missing `dispute_reason_code` column (ITEM_NOT_AS_DESCRIBED | SELLER_NO_SHOW | DEAL_FELL_THROUGH)
- `dispute_evidence_s3_key` should be JSON array (1-5 files)
- Missing `overpayment_tx_ref` for concurrent "First-to-Fund Wins" scenario

### 6. Missing `proposal_item_price_histories` table
SRS FR-4.4: `proposal_item_id`, `old_amount_santim`, `new_amount_santim`, `changed_by_id`, `created_at`

### 7. Missing `payout_destinations` table
SRS FR-2.4: Standalone reusable payout destinations per Delala.
Currently `payout_requests` embeds bank info inline (not reusable).

### 8. Missing `audit_logs` table
SRS NFR-AUD-1: append-only with `actor_id`, `actor_role`, `ip_address`, `user_agent`,
`auditable_type`, `auditable_id`, `old_values` (JSONB), `new_values` (JSONB), `created_at`.

### 9. Missing `admin_approval_requests` table
SRS §3 Maker-Checker: Operations >10,000 ETB need pending approval workflow.

### 10. `categories` table missing `json_schema` column
SRS FR-3.1: Each leaf category needs a `json_schema` JSONB column for attribute validation.

### 11. Missing `notifications` table
In-app notifications (§8 Notification Matrix) — Laravel notifications table not migrated.

---

## Functional Gaps

- FR-1.6: Unverified Account Purge Cron (72h, zero jobs) — not implemented
- FR-3.4: Feed excludes Delala's already-submitted proposals — not implemented
- FR-4.5: Anti-Circumvention PII Filter middleware — not implemented
- NFR-SEC-1: SMS rate limiting + reCAPTCHA gate — not implemented
- NFR-SEC-2: Failed login lockout (5 attempts, 15 min) — not implemented
- NFR-SEC-4: Chapa webhook HMAC signature verification — not implemented
- NFR-PERF-2: Horizon 4-queue isolation topology — not configured
- FIN-1.1: Nightly Chapa reconciliation job — not implemented
- §9.2: SMS circuit breaker (sliding window, Redis) — not implemented
