# Authentication Module: Clarifications & Decisions Log

All architectural and UI component decisions for the Authentication module have been resolved and aligned with `docs/Auth.md` and the backend implementation (`backend/app/Http/Controllers/Api/AuthController.php`).

---

### Decision Log

| # | Item | User Decision / Backend Status | Implementation in Frontend |
| :--- | :--- | :--- | :--- |
| **1** | **Delala Account Role (`POST /register`)** | Backend assigns `'type' => 'buyer'` in `AuthController.php` L41 currently. | `SignUpDelala` passes `type: 'delala'` in the payload matching `docs/Auth.md`. When the backend updates to `$request->type ?? 'buyer'`, Delalas will be saved as `type: 'delala'` automatically. |
| **2** | **Sign-In Identifier (Email vs Phone)** | Confirmed: Backend `AuthController::login` strictly validates `'email' => ['required', 'string', 'email']`. | Primary sign-in form accepts **Email** & **Password**. The secondary **"Log in with SMS Code (OTP)"** button handles phone-based logins via OTP flow (`/send-otp` + `/verify-otp`). |
| **3** | **Delala Operating Region Dropdown** | Confirmed: Backend user schema has no `region` field. | **Removed** the `region` dropdown from `SignUpDelala` so it conforms strictly to backend validation rules. |
| **4** | **Guest Buyer Full Name Field** | Confirmed: Backend `sendOtp` only takes `phone` and `g-recaptcha-response`. Backend generates constant/random name for display. | **Removed** `Full Name` from `GuestLogin`. User only enters phone number for instant frictionless entry. |
| **5** | **Password Recovery Views** | Confirmed: Built custom password recovery views matching the `DESIGN.md` design system. | Built `ForgotPassword` (`POST /forgot-password`) and `ResetPassword` (`POST /reset-password`) with token capture, matching cards, and security badges. |
| **6** | **Google reCAPTCHA v3 Handling** | Confirmed: Automatic test bypass in dev, dynamic script in production. | Created `recaptcha.ts` helper that supplies `"test_token"` when `import.meta.env.DEV` or when `VITE_RECAPTCHA_SITE_KEY` is not present, and loads Google reCAPTCHA v3 when configured. |

---

### Additional Module Enhancements
- **Password Confirmation (`password_confirmation`)**: Added to `SignUpBuyer`, `SignUpDelala`, `ResetPassword`, and `MigrateGuestModal` to fulfill Laravel's `confirmed` validation rule.
- **Brute-Force Account Lockout (HTTP 423 `ACCOUNT_LOCKED`)**: Implemented in `SignIn` with disabled submit state and live countdown timer until `locked_until`.
- **Guest Account Migration (`POST /migrate`)**: Implemented `MigrateGuestModal` with `409 Conflict` (`EMAIL_ALREADY_REGISTERED`) error handling.
