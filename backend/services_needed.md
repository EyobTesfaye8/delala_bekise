# External Services Needed

The following credentials/services are not yet in the .env but are required by the SRS.

## 1. Firebase Cloud Messaging (FCM) — Push Notifications
- Required for: All push notification events (§8 Notification Matrix)
- Need: `FIREBASE_PROJECT_ID`, `FIREBASE_CREDENTIALS_JSON` (Service Account JSON path or base64)
- Used for: ProposalSubmitted, ChatMessageSent, EscrowFunded, HandoverDeclared, etc.

## 2. Secondary SMS Gateway — Circuit Breaker Failover
- Required for: §9.2 SMS Circuit Breaker (primary AfroMessage fails → secondary takes over)
- Need: A secondary Ethiopian SMS provider credentials (`SECONDARY_SMS_API_KEY`, `SECONDARY_SMS_PROVIDER`)
- Options: Africa's Talking, Infobip, or another AfroMessage sub-account

## 3. Cloudflare Turnstile (alternative to reCAPTCHA)
- The .env has `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` (Google reCAPTCHA v3)
- SRS NFR-SEC-1 also mentions Cloudflare Turnstile as an option
- Current: reCAPTCHA v3 credentials already present — will use those
- Status: ✅ Already available

## 4. PagerDuty / Slack Webhook — P1/P2 Alerting
- Required for: NFR-OBS-2 operational alerts (ledger imbalance, REFUND_FAILED, queue lag, 5xx rate)
- Need: `SLACK_ALERT_WEBHOOK_URL` or `PAGERDUTY_ROUTING_KEY`
- Used for: P1 Critical (ledger imbalance, refund failures) and P2 High alerts

## 5. Chapa Webhook Secret
- Required for: NFR-SEC-4 HMAC webhook validation
- Need: `CHAPA_WEBHOOK_SECRET` — obtain from Chapa merchant dashboard
- Current .env has `CHAPA_PRIVATE_KEY` but not a dedicated webhook secret

## Already Available (No Action Needed)
- AfroMessage SMS: `AFROMESSAGE_API_KEY` ✅
- Resend Email: `RESEND_API_KEY` ✅
- Cloudflare R2 / S3: Full credentials ✅
- Chapa API keys: Public + Private ✅
- Google reCAPTCHA v3: Site + Secret keys ✅
- Google OAuth: Client ID + Secret ✅ (for future use)
