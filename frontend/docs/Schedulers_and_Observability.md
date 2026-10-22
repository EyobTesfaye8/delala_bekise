# Module 11: Automation, Background Schedulers & Observability

This document details the background automation tasks, scheduled console commands, distributed tracing, and observability infrastructure for the Delala Platform.

---

## 1. Automated Cron Tasks & Schedulers (§10 & §11.1 SRS)

The application defines six mission-critical background schedulers executed via Laravel's Task Scheduler (`php artisan schedule:run`):

| Command Signature | Cadence | Purpose | Failure Mode / Safety Guard |
| :--- | :--- | :--- | :--- |
| `delala:auto-release-escrow` | **Hourly** | Auto-releases funds to Delala's Available Wallet when 120-hour handover window expires without Buyer dispute. | Writes double-entry journal JNL-2 inside database transaction. |
| `delala:handover-warning` | **Hourly** | Sends urgent 96-hour countdown warning (24h before auto-release) to Buyer via push and SMS. | Idempotent; tracks `handover_warning_sent_at` timestamp to prevent duplicate alerts. |
| `delala:auto-refund-cancellations` | **Hourly** | Executes automated Chapa refund for pre-handover cancellations ignored by broker after 48 hours. | Calls Chapa refund API and writes JNL-3 double-entry reversal. |
| `delala:chapa-polling-fallback` | **Every 15 min** | Circuit breaker: queries Chapa verify API for transactions in `pending_payment` older than 10 minutes. | Overcomes dropped webhooks or network partitions. |
| `delala:nightly-reconciliation` | **Daily at 02:00 EAT** | Audits entire double-entry ledger by checking if $\sum\text{Debits} == \sum\text{Credits}$. | If discrepancy detected, immediately dispatches P1 alert to admin FCM topic. |
| `delala:purge-unverified` | **Daily** | Deletes unverified user accounts older than 72 hours with 0 associated jobs. | Cleans database spam and unverified test numbers. |

### Running Schedulers Manually
```bash
# Execute auto-release check
php artisan delala:auto-release-escrow

# Execute 96h countdown warnings
php artisan delala:handover-warning

# Execute 48h cancellation auto-refunds
php artisan delala:auto-refund-cancellations

# Run Chapa polling fallback
php artisan delala:chapa-polling-fallback

# Audit double-entry ledger
php artisan delala:nightly-reconciliation
```

---

## 2. Distributed Tracing & Correlation ID (NFR-OBS-1)

Every HTTP request entering the Delala backend passes through `CorrelationIdMiddleware`:
1. **Client Propagation**: If the client (Frontend / Mobile App) provides an `X-Request-ID` header (e.g., UUIDv4), the backend binds and echoes it.
2. **Auto-Generation**: If omitted, the middleware generates a fresh UUIDv4 (`(string) Str::uuid()`).
3. **Structured Contextual Logging**: The correlation ID is attached to Laravel's contextual logger (`Log::withContext(['request_id' => $requestId])`). Every log entry emitted across HTTP requests, queued jobs, and database queries carries the identical `request_id`.
4. **Response Header**: The `X-Request-ID` is returned in all outgoing HTTP responses.

### Frontend Integration
Frontend clients should log the returned `X-Request-ID` on any `4xx` or `5xx` error so support tickets can be instantly traced in backend logs:
```javascript
// Example Axios Error Interceptor
axios.interceptors.response.use(
  response => response,
  error => {
    const correlationId = error.response?.headers['x-request-id'];
    console.error(`Request failed [Trace ID: ${correlationId}]:`, error.response?.data);
    return Promise.reject(error);
  }
);
```

---

## 3. High-Priority Operational Alerting (P1/P2)

In accordance with `services_needed.md`, critical production alerts (such as ledger imbalances, worker lag, or refund failures) are dispatched directly to the Firebase Admin topic `admin_alerts`:
- **Topic**: `admin_alerts`
- **Payload**: Includes title, message body, and custom key-value metadata (e.g. `imbalance_santim`, `account_id`).
- Admins with the Delala Admin Companion app receive high-priority push alerts within seconds of any financial irregularity.
