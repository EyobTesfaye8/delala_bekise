# Authentication Module - Frontend Integration Guide

This document outlines the authentication flows and API endpoints available in the Delala Backend. All endpoints are prefixed with `/api/v1/auth`.

## Table of Contents
1. [Global Concepts](#1-global-concepts)
2. [Flow 1: Guest Buyer (Frictionless Entry)](#2-flow-1-guest-buyer-frictionless-entry)
3. [Flow 2: Registered User (Buyer or Delala)](#3-flow-2-registered-user-buyer-or-delala)
4. [Flow 3: Password Reset](#4-flow-3-password-reset)
5. [Token Management](#5-token-management)

---

## 1. Global Concepts

### Phone Number Format
You do not need to format the phone number heavily on the frontend. The backend will automatically normalize inputs like `0911000000`, `251911000000`, or `+251911000000` into the standard `+251` international format.

### Google reCAPTCHA v3
To protect against SMS toll fraud, the `POST /send-otp` endpoint requires a valid Google reCAPTCHA v3 token.
- **Key:** `g-recaptcha-response`
- **Testing:** If the backend environment is set to `local` or `testing`, you can pass the string `"test_token"` to bypass the Google API check.

### Bearer Tokens
Upon successful login or OTP verification, the backend returns a Sanctum `token`. You must include this token in the `Authorization` header of all subsequent authenticated requests:
`Authorization: Bearer <your_token>`

---

## 2. Flow 1: Guest Buyer (Frictionless Entry)

Use this flow when a user wants to quickly post a job using only their phone number.

### Step 1: Send OTP
**`POST /send-otp`**
```json
// Request payload
{
    "phone": "0911000000",
    "g-recaptcha-response": "<recaptcha_token>"
}
```
**Response (200 OK):**
```json
{
    "message": "OTP sent successfully"
}
```

### Step 2: Verify OTP
**`POST /verify-otp`**
```json
// Request payload
{
    "phone": "0911000000",
    "code": "123456"
}
```
**Response (200 OK):**
```json
{
    "message": "Authentication successful",
    "user": {
        "id": 1,
        "type": "guest",
        "guest_uuid": "...",
        // ...
    },
    "token": "1|abcdefg..."
}
```
*Note: A guest token is only valid for 24 hours.*

### Step 3: Migrate Guest to Registered Buyer
If a guest wants to save their account before the token expires, use this endpoint.
**`POST /migrate`** (Requires Bearer Token)
```json
// Request payload
{
    "full_name": "Abebe Kebede",
    "email": "abebe@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```
**Response (200 OK):**
```json
{
    "message": "Account upgraded successfully",
    "user": {
        "id": 1,
        "type": "buyer",
        "email": "abebe@example.com"
    }
}
```
**Error Response (409 Conflict - Email Already Registered):**
If the guest provides an email already associated with another registered account:
```json
{
    "message": "The provided email is already registered to another account.",
    "error_code": "EMAIL_ALREADY_REGISTERED"
}
```

---

## 3. Flow 2: Registered User (Buyer or Delala)

Use this flow when a user wants to create a permanent account immediately.

### Step 1: Registration
**`POST /register`**
```json
// Request payload
{
    "full_name": "Abebe Kebede",
    "phone": "0911000000",
    "email": "abebe@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "type": "buyer" // or "delala"
}
```
**Response (201 Created):**
```json
{
    "message": "Registration successful. Please verify your email and phone number.",
    "user": { ... }
}
```
*Note: The user's status is `unverified`. The backend will automatically send an Email Magic Link and an SMS OTP.*

### Step 2: Verification
The user must verify **both** their email and phone.
1. **Verify Phone:** Use the exact same `POST /verify-otp` endpoint from Flow 1.
2. **Verify Email:** The user clicks the link in their email. The backend handles this GET request directly and returns a success JSON/page.
*When both are verified, the backend automatically changes the user's status to `active`.*

### Step 3: Login
**`POST /login`**
```json
// Request payload
{
    "email": "abebe@example.com",
    "password": "password123"
}
```
**Response (200 OK):**
```json
{
    "message": "Login successful",
    "user": { ... },
    "token": "2|abcdefg..."
}
```

**Brute-Force Protection & Lockout (423 Locked):**
After 5 consecutive failed password attempts, the account is locked for 15 minutes.
```json
{
    "message": "Account is temporarily locked due to 5 consecutive failed login attempts. Please try again after 15 minutes.",
    "error_code": "ACCOUNT_LOCKED",
    "locked_until": "2026-10-22T19:15:00.000000Z"
}
```
*Frontend Guidance:* When receiving HTTP 423 with `ACCOUNT_LOCKED`, disable the submit button and show a countdown timer until `locked_until`.

---

## 4. Flow 3: Password Reset

### Step 1: Request Reset Link
**`POST /forgot-password`**
```json
// Request payload
{
    "email": "abebe@example.com"
}
```
**Response (200 OK):**
```json
{
    "message": "We have emailed your password reset link."
}
```

### Step 2: Submit New Password
The user receives an email with a link containing a token. The frontend should capture this token from the URL and submit it here.
**`POST /reset-password`**
```json
// Request payload
{
    "token": "<token_from_url>",
    "email": "abebe@example.com",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```
**Response (200 OK):**
```json
{
    "message": "Your password has been reset."
}
```

---

## 5. Token Management

### Logout (Revoke Token)
**`POST /logout`** (Requires Bearer Token)
```json
// Request payload (Empty body)
{}
```
**Response (200 OK):**
```json
{
    "message": "Successfully logged out"
}
```

### Get Current User
**`GET /me`** (Requires Bearer Token)
**Response (200 OK):**
```json
// Returns the currently authenticated User object.
{
    "id": 1,
    "full_name": "Abebe Kebede",
    "type": "buyer",
    "status": "active",
    // ...
}
```

---

## Note for Delala Access (KYC)
If a user registers as a `delala`, even after fully verifying their email and phone (status `active`), they will hit a `403 Forbidden` error on Job/Proposal endpoints until an admin approves their KYC profile. Handling the KYC submission will be covered in the KYC module documentation.
