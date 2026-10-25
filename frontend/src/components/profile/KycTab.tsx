import React from 'react';
import type { KycProfileData } from '../../services/profileService';

interface KycTabProps {
  kycData: KycProfileData | null;
  isLoading: boolean;
  onOpenSubmitModal: () => void;
}

export const KycTab: React.FC<KycTabProps> = ({
  kycData,
  isLoading,
  onOpenSubmitModal,
}) => {
  const status = kycData?.status || 'pending_submission';
  const profile = kycData?.profile;
  const urls = kycData?.urls;

  const docTypeLabels: Record<string, string> = {
    kebele_id: 'Ethiopian Kebele Resident ID Card',
    national_id: 'Fayda National Digital ID',
    passport: 'Ethiopian Passport',
    drivers_license: 'Ethiopian Driver\'s License',
  };

  const statusBadges: Record<string, { label: string; icon: string; className: string }> = {
    approved: {
      label: 'Verified & Approved (NBE Compliant)',
      icon: 'verified',
      className: 'bg-[var(--app-primary-light)] text-[var(--app-primary)] border-[var(--app-primary)]/20',
    },
    under_review: {
      label: 'Under Compliance Review (Est. 24h)',
      icon: 'pending',
      className: 'bg-[var(--app-accent-light)] text-[var(--app-accent)] border-[var(--app-accent)]/20',
    },
    rejected: {
      label: 'Verification Rejected - Action Required',
      icon: 'cancel',
      className: 'bg-[var(--app-danger-light)] text-[var(--app-danger)] border-[var(--app-danger)]/20',
    },
    pending_submission: {
      label: 'Documents Required - Not Submitted',
      icon: 'assignment_late',
      className: 'bg-slate-100 dark:bg-slate-800 text-[var(--app-text-muted)] border-[var(--app-border)]',
    },
  };

  const currentBadge = statusBadges[status] || statusBadges.pending_submission;

  return (
    <div className="space-y-6">
      {/* Status Hero Card */}
      <div className="p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--app-border)]">
          <div className="flex items-center gap-3">
            <div
              className={`w-11 h-11 rounded-xl flex items-center justify-center shrink-0 ${
                status === 'approved'
                  ? 'bg-[var(--app-primary-light)] text-[var(--app-primary)]'
                  : status === 'under_review'
                  ? 'bg-[var(--app-accent-light)] text-[var(--app-accent)]'
                  : 'bg-[var(--app-card-subtle)] text-[var(--app-text-muted)]'
              }`}
            >
              <span className="material-symbols-outlined text-[24px]">{currentBadge.icon}</span>
            </div>
            <div>
              <h3 className="text-base font-bold text-[var(--app-text)]">
                Delala Broker Identity Verification (KYC)
              </h3>
              <p className="text-xs text-[var(--app-text-muted)]">
                National Bank of Ethiopia financial marketplace compliance
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <span
              className={`px-3 py-1 rounded-full text-xs font-bold border flex items-center gap-1.5 ${currentBadge.className}`}
            >
              <span className="material-symbols-outlined text-[14px]">{currentBadge.icon}</span>
              <span>{currentBadge.label}</span>
            </span>

            {status !== 'approved' && status !== 'under_review' && (
              <button
                type="button"
                onClick={onOpenSubmitModal}
                className="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)] transition-all cursor-pointer border-0 flex items-center gap-1 shadow-xs"
              >
                <span className="material-symbols-outlined text-[15px]">upload</span>
                <span>Submit Documents</span>
              </button>
            )}
          </div>
        </div>

        {/* Status specific details */}
        {isLoading ? (
          <div className="py-8 text-center text-xs text-[var(--app-text-muted)]">
            <span className="w-5 h-5 border-2 border-[var(--app-primary)] border-t-transparent rounded-full animate-spin inline-block mb-1" />
            <p>Loading KYC verification status...</p>
          </div>
        ) : status === 'approved' ? (
          <div className="space-y-4">
            <div className="p-4 rounded-xl bg-[var(--app-primary-light)]/40 border border-[var(--app-primary)]/20 text-xs text-[var(--app-text)] space-y-1">
              <p className="font-bold text-[var(--app-primary)] flex items-center gap-1.5">
                <span className="material-symbols-outlined text-[16px]">verified</span>
                <span>Account Verified &amp; Broker Proposal Authority Granted</span>
              </p>
              <p className="text-[var(--app-text-muted)] leading-relaxed text-[11px]">
                Your government identity has been verified and registered with NBE FinTech sandbox compliance. You are authorized to submit multi-item asset proposals and manage escrow-backed transactions.
              </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div className="p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-1">
                <span className="text-xs font-medium text-[var(--app-text-muted)]">
                  Verified document
                </span>
                <p className="text-xs font-bold text-[var(--app-text)]">
                  {profile?.document_type ? docTypeLabels[profile.document_type] || profile.document_type : 'Kebele Resident ID'}
                </p>
              </div>

              <div className="p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-1">
                <span className="text-xs font-medium text-[var(--app-text-muted)]">
                  Approval date
                </span>
                <p className="text-xs font-bold text-[var(--app-text)]">
                  {profile?.reviewed_at ? new Date(profile.reviewed_at).toLocaleDateString() : 'Verified Active'}
                </p>
              </div>

              <div className="p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-1">
                <span className="text-xs font-medium text-[var(--app-text-muted)]">
                  Encrypted storage
                </span>
                <p className="text-xs font-bold text-[var(--app-primary)] flex items-center gap-1">
                  <span className="material-symbols-outlined text-[14px]">lock</span>
                  <span>AES-256 S3 Cloud Vault</span>
                </p>
              </div>
            </div>
          </div>
        ) : status === 'under_review' ? (
          <div className="p-4 rounded-xl bg-[var(--app-accent-light)]/40 border border-[var(--app-accent)]/30 text-xs text-[var(--app-text)] space-y-2">
            <p className="font-bold text-[var(--app-accent)] flex items-center gap-1.5">
              <span className="material-symbols-outlined text-[16px]">schedule</span>
              <span>Submission Received &amp; Pending Admin Review</span>
            </p>
            <p className="text-[var(--app-text-muted)] leading-relaxed text-[11px]">
              Our compliance team is verifying your documents against the national registry. Review typically completes within 24 hours on business days. You will receive an FCM push alert once approved.
            </p>
          </div>
        ) : status === 'rejected' ? (
          <div className="p-4 rounded-xl bg-[var(--app-danger-light)]/40 border border-[var(--app-danger)]/30 text-xs text-[var(--app-text)] space-y-2">
            <p className="font-bold text-[var(--app-danger)] flex items-center gap-1.5">
              <span className="material-symbols-outlined text-[16px]">error</span>
              <span>Rejection Reason: {profile?.rejection_reason || 'Document was unreadable or expired.'}</span>
            </p>
            <p className="text-[var(--app-text-muted)] leading-relaxed text-[11px]">
              Please upload high-resolution, glare-free photos of your valid government ID and live verification selfie to proceed.
            </p>
            <button
              onClick={onOpenSubmitModal}
              className="mt-2 px-3.5 py-1.5 rounded-lg text-xs font-bold bg-[var(--app-danger)] text-white hover:opacity-90 transition-all cursor-pointer border-0 inline-flex items-center gap-1"
            >
              <span className="material-symbols-outlined text-[14px]">replay</span>
              <span>Re-submit Documents</span>
            </button>
          </div>
        ) : (
          <div className="p-6 text-center rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-3">
            <div className="w-12 h-12 rounded-xl bg-[var(--app-card)] border border-[var(--app-border)] flex items-center justify-center mx-auto text-[var(--app-text-muted)]">
              <span className="material-symbols-outlined text-[24px]">badge</span>
            </div>
            <h4 className="text-sm font-bold text-[var(--app-text)]">
              Identity Verification Not Yet Completed
            </h4>
            <p className="text-xs text-[var(--app-text-muted)] max-w-md mx-auto leading-relaxed">
              To submit multi-item proposals and claim commission payouts on Delala, government identity verification is required under the National Bank of Ethiopia regulatory sandbox.
            </p>
            <button
              type="button"
              onClick={onOpenSubmitModal}
              className="px-4 py-2 rounded-xl text-xs font-bold bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)] transition-all cursor-pointer border-0 inline-flex items-center gap-1.5 shadow-xs"
            >
              <span className="material-symbols-outlined text-[16px]">upload_file</span>
              <span>Submit Government ID &amp; Selfie</span>
            </button>
          </div>
        )}
      </div>

      {/* Document Previews Card (if available) */}
      {(urls?.id_front_url || profile?.id_document_s3_key) && (
        <div className="p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs space-y-4">
          <h4 className="text-sm font-bold text-[var(--app-text)]">Submitted Identification Documents</h4>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {/* Front Document */}
            <div className="p-3 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-2">
              <div className="flex items-center justify-between text-xs font-semibold">
                <span>Front ID</span>
                <span className="material-symbols-outlined text-[15px] text-[var(--app-primary)]">check_circle</span>
              </div>
              <div className="h-32 rounded-lg bg-[var(--app-bg)] border border-[var(--app-border)] flex items-center justify-center overflow-hidden">
                {urls?.id_front_url ? (
                  <img src={urls.id_front_url} alt="Front ID" className="h-full w-full object-cover" />
                ) : (
                  <span className="material-symbols-outlined text-[32px] text-[var(--app-text-muted)]">image</span>
                )}
              </div>
            </div>

            {/* Back Document */}
            <div className="p-3 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-2">
              <div className="flex items-center justify-between text-xs font-semibold">
                <span>Back ID</span>
                <span className="material-symbols-outlined text-[15px] text-[var(--app-primary)]">check_circle</span>
              </div>
              <div className="h-32 rounded-lg bg-[var(--app-bg)] border border-[var(--app-border)] flex items-center justify-center overflow-hidden">
                {urls?.id_back_url ? (
                  <img src={urls.id_back_url} alt="Back ID" className="h-full w-full object-cover" />
                ) : (
                  <span className="material-symbols-outlined text-[32px] text-[var(--app-text-muted)]">image</span>
                )}
              </div>
            </div>

            {/* Verification Selfie */}
            <div className="p-3 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-2">
              <div className="flex items-center justify-between text-xs font-semibold">
                <span>Selfie with ID</span>
                <span className="material-symbols-outlined text-[15px] text-[var(--app-primary)]">check_circle</span>
              </div>
              <div className="h-32 rounded-lg bg-[var(--app-bg)] border border-[var(--app-border)] flex items-center justify-center overflow-hidden">
                {urls?.selfie_url ? (
                  <img src={urls.selfie_url} alt="Selfie" className="h-full w-full object-cover" />
                ) : (
                  <span className="material-symbols-outlined text-[32px] text-[var(--app-text-muted)]">face</span>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
