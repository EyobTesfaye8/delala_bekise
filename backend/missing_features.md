# Missing Features - Authentication Module

Based on the Software Requirements Specification (`srs.md`) and the current backend implementation, here is an analysis of the missing features in the Auth module:

## 1. Direct Registration (FR-1.3) [✅ IMPLEMENTED]
- **Missing Endpoint:** There is no `POST /api/v1/auth/register` endpoint.
- **SRS Requirement:** A new user must be able to register directly with `Full Name`, `Phone Number`, `Email`, and `Password`.
- **Missing Logic:** The account must remain `UNVERIFIED` until both SMS OTP and Email magic-link are validated. Currently, there is no email verification/magic-link system implemented at all.

## 2. Registered User Login (Email/Password)
- **Missing Endpoint:** There is no `POST /api/v1/auth/login` endpoint.
- **Current State:** The `AuthController` only supports logging in via SMS OTP. Registered Buyers and Delalas must be able to log in using their Email and Password.

## 3. reCAPTCHA Validation (NFR-1.1)
- **Missing Validation:** The `POST /api/v1/auth/send-otp` endpoint does not validate reCAPTCHA tokens.
- **SRS Requirement:** To protect against SMS toll fraud, the `send-otp` endpoint must be protected by invisible reCAPTCHA v3. The rate limiting (3/hr per phone, 10/hr per IP) is implemented in `AppServiceProvider`, but the reCAPTCHA rule is entirely absent from the request validation.

## 4. Token Revocation (Logout)
- **Missing Endpoint:** There is no `POST /api/v1/auth/logout` endpoint to revoke Sanctum tokens.

## 5. Delala KYC State Machine (FR-2.1 / FR-2.2) (Auth-related constraints)
- **Missing Middleware/Gate:** The SRS specifies that Delalas cannot view the Job feed, submit proposals, or initiate chats until their KYC status is `APPROVED`. 
- **Current State:** There is no middleware or policy explicitly checking `isDelala()` AND `kycProfile->status == 'approved'` on those specific job/proposal routes.

## 6. Forgot Password (Password Reset)
- **Missing Endpoints:** There is no way for a registered user to reset their password if they forget it.
- **Current State:** Standard Laravel password reset flow (via email) or OTP-based reset is missing. Needs endpoints for requesting a reset link and submitting the new password.

## Summary of Missing Auth Routes needed:
```
POST /auth/register (Direct Registration)
POST /auth/login (Email + Password login)
POST /auth/logout (Revoke token)
POST /auth/verify-email (Magic link handling)
POST /auth/forgot-password (Request reset link)
POST /auth/reset-password (Submit new password)
```

## 7. Required From User
Before beginning implementation of these missing features, the following configurations and credentials must be provided:

1. **Email Service Provider (SMTP/API):**
   - Required to send the Email Magic-Links for verification when a user registers (FR-1.3).
   - *Action:* Please provide SMTP credentials (e.g., Mailtrap, Resend, AWS SES) or state if we should use Laravel's default `log` driver for local testing.

2. **Frontend Application URL:**
   - Required for generating correct Magic-Links for email verification.
   - *Action:* Please specify the intended local frontend URL (e.g., `http://localhost:3000`).

3. **Google reCAPTCHA v3 Keys:**
   - Required for SMS Toll Fraud Protection (NFR-1.1).
   - *Action:* Please provide a reCAPTCHA v3 Secret Key. Alternatively, state if we should implement a bypass for the `local` environment.

4. **SMS Gateway Credentials (Optional/Future):**
   - Currently, the `SendSmsOtpJob` mocks SMS delivery by logging it to `laravel.log`. If you want real SMS delivery (e.g., via AfroMessage or EthioTelecom) implemented now, credentials for the gateway are needed.
   - *Action:* Provide API keys/credentials for the SMS provider, or confirm we should continue mocking it for now.
