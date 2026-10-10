# Missing Features - KYC Module

Based on the Software Requirements Specification (`srs.md`) and the current backend implementation, here is an analysis of the missing features in the KYC Module:

## 1. KYC Submission Endpoint (Delala)

- **Missing Endpoint:** There is no `POST /api/v1/kyc` endpoint for Delalas to submit their documents.
- **SRS Requirement:** A Delala must be able to upload an ID document and a Selfie. These must be stored in a private S3 bucket.
- **Current State:** The `KycProfile` model exists, but there is no controller to handle the file uploads and create the record in the `UNDER_REVIEW` state.

## 2. Admin KYC Queue (AR-1)

- **Missing Endpoint:** There is no `GET /api/v1/admin/kyc` endpoint.
- **SRS Requirement:** Admins need a paginated list of all `UNDER_REVIEW` Delalas. This endpoint must generate and return 15-minute pre-signed S3 URLs so the admin can securely view the documents on the frontend.
- **Current State:** Completely missing.

## 3. Admin KYC Actions (Approve / Reject)

- **Missing Endpoints:**
  - `POST /api/v1/admin/kyc/{kyc}/approve`
  - `POST /api/v1/admin/kyc/{kyc}/reject`
- **SRS Requirement:** Admins must be able to approve or reject a KYC submission. Rejection requires a mandatory reason field.
- **Current State:** Completely missing. State transition logic exists via `Spatie\ModelStates`, but no controllers trigger it.

## 4. Notifications

- **Missing Logic:** When KYC is Approved or Rejected, the system must dispatch an asynchronous notification to the Delala (SMS, Email, and In-App).
- **Current State:** Missing.

## Summary of Next Steps

1. Create `KycController` for Delalas to upload documents.
2. Create `Admin\KycController` for the admin queue and approval/rejection logic.
3. Configure the `private` filesystem disk (S3/local mock) for secure document storage and pre-signed URL generation.
4. Implement Notification classes for `KycApproved` and `KycRejected`.

---

## 5. Proposed KYC Approval & Rejection Rules

Before an Admin makes a decision, they should evaluate the submission against these criteria.

**Criteria for ACCEPTANCE:**

1. **Name Match:** The full name on the ID perfectly matches the `full_name` on the Delala's account.
2. **Face Match:** The face in the uploaded selfie clearly matches the photo on the ID document.
3. **Legibility:** The ID is clear, fully readable, and not cropped or cut off.
4. **Validity:** The ID is a government-issued document (Kebele ID, Passport, Driver's License) and is **not expired**.

**Standard REJECTION Reasons (Sent to Delala):**

- `BLURRY_DOCUMENT`: "The uploaded ID is blurry, glaring, or unreadable. Please upload a clear, well-lit photo."
- `NAME_MISMATCH`: "The name on your ID does not match your account name. Please contact support."
- `FACE_MISMATCH`: "We could not confidently match your selfie to the photo on your ID. Please try taking a clearer selfie."
- `EXPIRED_DOCUMENT`: "The provided ID document is expired. Please provide a valid, current ID."
- `SUSPECTED_FRAUD`: "Your submission was flagged for irregularities and has been rejected."

---

## 6. Required From User

To actually build this module, I will need the following decisions/configurations from you:

1. **Storage Driver (AWS S3 vs Local):**
   - The SRS states these documents must be stored in a private S3 bucket and accessed via 15-minute pre-signed URLs.
   - *Action:* Do you want to provide **AWS S3 credentials (Key, Secret, Region, Bucket)** in the `.env` now? Or should I configure it to use Laravel's `local` private disk for now (which can still simulate secure URLs) so you don't have to pay for AWS during local development?

2. **Admin Authentication:**
   - Admins need to log in to access the `GET /api/v1/admin/kyc` queues. The SRS mentions "Internal SSO / 2FA" for Admins.
   - *Action:* For now, should I just create a basic Admin seeder with a hardcoded email/password and standard Sanctum token login so we can test the admin endpoints via Postman?
