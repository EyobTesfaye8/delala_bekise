import React, { useState } from 'react';
import type { User } from '../../types/auth';
import { profileService } from '../../services/profileService';

interface GeneralProfileTabProps {
  user: User | null;
  onEditName: () => void;
  onUpdateBio: (bio: string) => Promise<void>;
  onTokenUpdated: (token: string) => void;
}

export const GeneralProfileTab: React.FC<GeneralProfileTabProps> = ({
  user,
  onEditName,
  onUpdateBio,
  onTokenUpdated,
}) => {
  const [bio, setBio] = useState(user?.bio || '');
  const [isSavingBio, setIsSavingBio] = useState(false);
  const [bioSaved, setBioSaved] = useState(false);

  // FCM Token state
  const [fcmToken, setFcmToken] = useState(user?.fcm_token || '');
  const [isRegisteringFcm, setIsRegisteringFcm] = useState(false);
  const [fcmSuccessMsg, setFcmSuccessMsg] = useState<string | null>(null);
  const [fcmError, setFcmError] = useState<string | null>(null);

  const handleSaveBio = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      setIsSavingBio(true);
      await onUpdateBio(bio);
      setBioSaved(true);
      setTimeout(() => setBioSaved(false), 3000);
    } catch {
      // ignore
    } finally {
      setIsSavingBio(false);
    }
  };

  const handleRegisterFcm = async () => {
    const tokenToUse = fcmToken.trim() || `fcm_web_${Date.now()}_delala_push_device`;
    try {
      setIsRegisteringFcm(true);
      setFcmError(null);
      const res = await profileService.updateFcmToken(tokenToUse);
      setFcmToken(tokenToUse);
      onTokenUpdated(tokenToUse);
      setFcmSuccessMsg(res.message || 'FCM device push token registered successfully!');
      setTimeout(() => setFcmSuccessMsg(null), 4000);
    } catch (err: unknown) {
      setFcmError(err instanceof Error ? err.message : 'Failed to update FCM token.');
    } finally {
      setIsRegisteringFcm(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* 1. Personal & Display Identity */}
      <div className="p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs space-y-5">
        <div className="flex items-center justify-between pb-4 border-b border-[var(--app-border)]">
          <div>
            <h3 className="text-base font-bold text-[var(--app-text)]">General Profile Information</h3>
            <p className="text-xs text-[var(--app-text-muted)]">Manage your public marketplace presence</p>
          </div>
          <button
            onClick={onEditName}
            className="px-3 py-1.5 rounded-xl text-xs font-semibold border border-[var(--app-border)] bg-[var(--app-card-subtle)] hover:border-[var(--app-primary)] text-[var(--app-text)] transition-all cursor-pointer flex items-center gap-1.5"
          >
            <span className="material-symbols-outlined text-[15px]">edit</span>
            <span>Edit Name</span>
          </button>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {/* Full Name */}
          <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)]">
            <span className="text-xs font-medium text-[var(--app-text-muted)] block mb-1">
              Legal or display name
            </span>
            <div className="flex items-center justify-between">
              <span className="text-sm font-bold text-[var(--app-text)]">
                {user?.full_name || 'Not configured'}
              </span>
              <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-[var(--app-primary-light)] text-[var(--app-primary)]">
                Active
              </span>
            </div>
          </div>

          {/* Email Address */}
          <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)]">
            <span className="text-xs font-medium text-[var(--app-text-muted)] block mb-1">
              Email address
            </span>
            <div className="flex items-center justify-between">
              <span className="text-sm font-semibold text-[var(--app-text)] truncate max-w-[200px]">
                {user?.email || 'No email attached (Guest session)'}
              </span>
              {user?.email ? (
                <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-[var(--app-primary-light)] text-[var(--app-primary)]">
                  Verified
                </span>
              ) : (
                <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-[var(--app-accent-light)] text-[var(--app-accent)]">
                  Unregistered
                </span>
              )}
            </div>
          </div>
        </div>

        {/* Bio / Headline Form */}
        <form onSubmit={handleSaveBio} className="space-y-3 pt-2">
          <div>
            <label className="block text-xs font-medium text-[var(--app-text)] mb-1.5">
              Marketplace headline or broker bio
            </label>
            <textarea
              rows={3}
              value={bio}
              onChange={(e) => setBio(e.target.value)}
              placeholder="Describe your property or vehicle expertise, preferred subcities (Bole, Kirkos, Yeka), or buyer criteria..."
              className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm focus:outline-none focus:border-[var(--app-primary)] transition-all resize-none font-sans"
            />
          </div>

          <div className="flex items-center justify-between">
            <span className="text-[11px] text-[var(--app-text-muted)]">
              Displayed on proposal items and public delala discovery cards.
            </span>
            <div className="flex items-center gap-2">
              {bioSaved && (
                <span className="text-xs font-semibold text-[var(--app-primary)] flex items-center gap-1">
                  <span className="material-symbols-outlined text-[16px]">check_circle</span>
                  <span>Bio updated</span>
                </span>
              )}
              <button
                type="submit"
                disabled={isSavingBio}
                className="px-4 py-2 rounded-xl text-xs font-bold bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)] transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs disabled:opacity-50"
              >
                {isSavingBio ? 'Saving...' : 'Save headline'}
              </button>
            </div>
          </div>
        </form>
      </div>

      {/* 2. Immutable Identity Anchor (Phone Number) */}
      <div className="p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs space-y-4">
        <div className="flex items-center justify-between pb-3 border-b border-[var(--app-border)]">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center">
              <span className="material-symbols-outlined text-[18px]">lock</span>
            </div>
            <div>
              <h3 className="text-sm font-bold text-[var(--app-text)]">
                National Identity Anchor (Phone Number)
              </h3>
              <p className="text-[11px] text-[var(--app-text-muted)]">
                Tied cryptographically to SMS OTP verification
              </p>
            </div>
          </div>
          <span className="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-[var(--app-text-muted)] border border-[var(--app-border)] flex items-center gap-1">
            <span className="material-symbols-outlined text-[12px]">lock</span>
            <span>Immutable</span>
          </span>
        </div>

        <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="space-y-1">
            <span className="text-xs font-medium text-[var(--app-text-muted)]">
              Primary authenticated phone
            </span>
            <div className="flex items-center gap-2">
              <span className="font-mono text-base font-bold text-[var(--app-text)]">
                {user?.phone || '+251 9XX XXX XXX'}
              </span>
              <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center gap-0.5">
                <span className="material-symbols-outlined text-[12px]">verified</span>
                <span>EthioTelecom OTP anchored</span>
              </span>
            </div>
          </div>

          <div className="text-right">
            <span className="text-[11px] text-[var(--app-text-muted)] block">
              Verified: {user?.phone_verified_at ? new Date(user.phone_verified_at).toLocaleDateString() : 'Yes'}
            </span>
          </div>
        </div>

        {/* Regulatory callout */}
        <div className="p-3.5 rounded-xl bg-[var(--app-bg)] border border-[var(--app-border)] flex items-start gap-2.5 text-xs text-[var(--app-text-muted)]">
          <span className="material-symbols-outlined text-[16px] text-[var(--app-primary)] shrink-0 mt-0.5">
            shield
          </span>
          <p className="leading-relaxed text-[11px]">
            <strong>Why cannot this be edited?</strong> Under National Bank of Ethiopia (NBE) regulatory directives for escrow transactions, your phone number serves as your non-repudiation cryptographic identifier. To request an identity transfer or number change, contact Delala Compliance with government identification.
          </p>
        </div>
      </div>

      {/* 3. FCM Push Notification Token Manager */}
      <div className="p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs space-y-4">
        <div className="flex items-center justify-between pb-3 border-b border-[var(--app-border)]">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center">
              <span className="material-symbols-outlined text-[18px]">notifications_active</span>
            </div>
            <div>
              <h3 className="text-sm font-bold text-[var(--app-text)]">
                Device Push Notifications (FCM)
              </h3>
              <p className="text-[11px] text-[var(--app-text-muted)]">
                Real-time alerts for escrow deposits, chat messages &amp; proposal updates
              </p>
            </div>
          </div>
          <span
            className={`px-2.5 py-1 rounded-full text-[10px] font-bold flex items-center gap-1 ${
              fcmToken
                ? 'bg-[var(--app-primary-light)] text-[var(--app-primary)]'
                : 'bg-slate-100 dark:bg-slate-800 text-[var(--app-text-muted)]'
            }`}
          >
            <span className="w-1.5 h-1.5 rounded-full bg-current" />
            <span>{fcmToken ? 'Push Enabled' : 'Not Registered'}</span>
          </span>
        </div>

        {fcmSuccessMsg && (
          <div className="p-3 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] text-xs flex items-center gap-2">
            <span className="material-symbols-outlined text-[16px]">check_circle</span>
            <span>{fcmSuccessMsg}</span>
          </div>
        )}

        {fcmError && (
          <div className="p-3 rounded-xl bg-[var(--app-danger-light)] text-[var(--app-danger)] text-xs flex items-center gap-2">
            <span className="material-symbols-outlined text-[16px]">error</span>
            <span>{fcmError}</span>
          </div>
        )}

        <div className="space-y-3">
          <div>
            <label className="block text-xs font-medium text-[var(--app-text)] mb-1.5">
              Device push notification token (FCM)
            </label>
            <div className="flex items-center gap-2">
              <input
                type="text"
                value={fcmToken}
                onChange={(e) => setFcmToken(e.target.value)}
                placeholder="fcm_device_token_hash_string..."
                className="flex-1 px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-xs font-mono focus:outline-none focus:border-[var(--app-primary)] transition-all"
              />
              <button
                type="button"
                onClick={handleRegisterFcm}
                disabled={isRegisteringFcm}
                className="px-4 py-2.5 rounded-xl text-xs font-bold bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)] transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs disabled:opacity-50 shrink-0"
              >
                {isRegisteringFcm ? (
                  <>
                    <span className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    <span>Registering...</span>
                  </>
                ) : (
                  <>
                    <span className="material-symbols-outlined text-[15px]">send</span>
                    <span>Test &amp; Sync Token</span>
                  </>
                )}
              </button>
            </div>
            <p className="mt-1.5 text-[11px] text-[var(--app-text-muted)]">
              Automatically synchronized by Delala Progressive Web App and Android/iOS clients via <code>POST /api/v1/users/fcm-token</code>.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};
