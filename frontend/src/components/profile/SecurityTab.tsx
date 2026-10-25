import React, { useState } from 'react';
import type { User } from '../../types/auth';
import { profileService } from '../../services/profileService';
import { authService } from '../../services/authService';

interface SecurityTabProps {
  user: User | null;
  onOpenTotpModal: () => void;
  onMigrateGuestSuccess: (updatedUser: User) => void;
}

export const SecurityTab: React.FC<SecurityTabProps> = ({
  user,
  onOpenTotpModal,
  onMigrateGuestSuccess,
}) => {
  const isGuest = user?.type === 'guest';
  const isDelala = user?.type === 'delala';
  const hasTotp = !!user?.totp_enabled_at;

  // Password Update State
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [isUpdatingPassword, setIsUpdatingPassword] = useState(false);
  const [passwordSuccess, setPasswordSuccess] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);

  // Forgot password email trigger
  const [isSendingResetEmail, setIsSendingResetEmail] = useState(false);
  const [resetEmailSuccess, setResetEmailSuccess] = useState<string | null>(null);

  // Guest Upgrade Form State
  const [upgradeName, setUpgradeName] = useState(user?.full_name || '');
  const [upgradeEmail, setUpgradeEmail] = useState('');
  const [upgradePassword, setUpgradePassword] = useState('');
  const [upgradePasswordConfirm, setUpgradePasswordConfirm] = useState('');
  const [isUpgrading, setIsUpgrading] = useState(false);
  const [upgradeError, setUpgradeError] = useState<string | null>(null);

  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (newPassword.length < 8) {
      setPasswordError('New password must be at least 8 characters.');
      return;
    }
    if (newPassword !== confirmPassword) {
      setPasswordError('Passwords do not match.');
      return;
    }

    try {
      setIsUpdatingPassword(true);
      setPasswordError(null);
      const res = await profileService.updatePassword({
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword,
      });
      setPasswordSuccess(res.message || 'Password changed successfully.');
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
      setTimeout(() => setPasswordSuccess(null), 4000);
    } catch (err: unknown) {
      setPasswordError(err instanceof Error ? err.message : 'Failed to update password.');
    } finally {
      setIsUpdatingPassword(false);
    }
  };

  const handleSendResetEmail = async () => {
    if (!user?.email) {
      setPasswordError('No email associated with this account. Please register an email first.');
      return;
    }

    try {
      setIsSendingResetEmail(true);
      setPasswordError(null);
      const res = await authService.forgotPassword({ email: user.email });
      setResetEmailSuccess(res.message || 'Password reset link sent to your email.');
      setTimeout(() => setResetEmailSuccess(null), 5000);
    } catch (err: unknown) {
      setPasswordError(err instanceof Error ? err.message : 'Failed to send reset link.');
    } finally {
      setIsSendingResetEmail(false);
    }
  };

  const handleGuestUpgrade = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!upgradeName.trim()) {
      setUpgradeError('Full name is required.');
      return;
    }
    if (!upgradeEmail.trim()) {
      setUpgradeError('Valid email is required.');
      return;
    }
    if (upgradePassword.length < 8) {
      setUpgradeError('Password must be at least 8 characters.');
      return;
    }
    if (upgradePassword !== upgradePasswordConfirm) {
      setUpgradeError('Passwords do not match.');
      return;
    }

    try {
      setIsUpgrading(true);
      setUpgradeError(null);
      const res = await authService.migrateGuest({
        full_name: upgradeName.trim(),
        email: upgradeEmail.trim(),
        password: upgradePassword,
        password_confirmation: upgradePasswordConfirm,
      });
      if (res.user) {
        onMigrateGuestSuccess(res.user);
      }
    } catch (err: unknown) {
      setUpgradeError(err instanceof Error ? err.message : 'Failed to upgrade guest account.');
    } finally {
      setIsUpgrading(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* If Guest: Prominent Just-In-Time Account Migration */}
      {isGuest && (
        <div className="p-6 rounded-2xl bg-gradient-to-r from-[var(--app-accent-light)]/60 to-[var(--app-card)] border border-[var(--app-accent)]/30 shadow-xs space-y-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-[var(--app-accent)] text-white flex items-center justify-center shrink-0">
              <span className="material-symbols-outlined text-[22px]">upgrade</span>
            </div>
            <div>
              <h3 className="text-base font-bold text-[var(--app-text)]">
                Upgrade Guest Account to Registered Buyer
              </h3>
              <p className="text-xs text-[var(--app-text-muted)]">
                Keep your active jobs, proposal chats &amp; escrow records permanently
              </p>
            </div>
          </div>

          <div className="p-3.5 rounded-xl bg-[var(--app-card)] border border-[var(--app-border)] flex items-center justify-between text-xs">
            <div className="flex items-center gap-2">
              <span className="material-symbols-outlined text-[16px] text-[var(--app-primary)]">
                verified
              </span>
              <span className="text-[var(--app-text-muted)]">Pre-verified Phone Number:</span>
              <strong className="font-mono text-[var(--app-text)]">{user?.phone}</strong>
            </div>
            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-[var(--app-primary-light)] text-[var(--app-primary)]">
              Anchored
            </span>
          </div>

          {upgradeError && (
            <div className="p-3 rounded-xl bg-[var(--app-danger-light)] text-[var(--app-danger)] text-xs flex items-center gap-2">
              <span className="material-symbols-outlined text-[16px]">error</span>
              <span>{upgradeError}</span>
            </div>
          )}

          <form onSubmit={handleGuestUpgrade} className="space-y-3 pt-2">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-medium text-[var(--app-text)] mb-1">
                  Full name
                </label>
                <input
                  type="text"
                  value={upgradeName}
                  onChange={(e) => setUpgradeName(e.target.value)}
                  placeholder="e.g. Abebech Tadesse"
                  required
                  className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm focus:outline-none focus:border-[var(--app-primary)]"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-[var(--app-text)] mb-1">
                  Email address
                </label>
                <input
                  type="email"
                  value={upgradeEmail}
                  onChange={(e) => setUpgradeEmail(e.target.value)}
                  placeholder="name@example.com"
                  required
                  className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm focus:outline-none focus:border-[var(--app-primary)]"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-medium text-[var(--app-text)] mb-1">
                  Password (minimum 8 characters)
                </label>
                <input
                  type="password"
                  value={upgradePassword}
                  onChange={(e) => setUpgradePassword(e.target.value)}
                  required
                  minLength={8}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm focus:outline-none focus:border-[var(--app-primary)]"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-[var(--app-text)] mb-1">
                  Confirm password
                </label>
                <input
                  type="password"
                  value={upgradePasswordConfirm}
                  onChange={(e) => setUpgradePasswordConfirm(e.target.value)}
                  required
                  minLength={8}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm focus:outline-none focus:border-[var(--app-primary)]"
                />
              </div>
            </div>

            <div className="flex justify-end pt-2">
              <button
                type="submit"
                disabled={isUpgrading}
                className="px-5 py-2.5 rounded-xl text-xs font-bold bg-[var(--app-accent)] text-white hover:bg-[var(--app-accent-hover)] transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs disabled:opacity-50"
              >
                {isUpgrading ? 'Upgrading account...' : 'Upgrade account'}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Two-Factor Authentication (TOTP) */}
      <div className="p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs space-y-4">
        <div className="flex items-center justify-between pb-3 border-b border-[var(--app-border)]">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center">
              <span className="material-symbols-outlined text-[18px]">verified_user</span>
            </div>
            <div>
              <h3 className="text-sm font-bold text-[var(--app-text)]">
                Two-Factor Authentication (TOTP Authenticator)
              </h3>
              <p className="text-[11px] text-[var(--app-text-muted)]">
                High-security 6-digit confirmation codes for payouts &amp; large transactions
              </p>
            </div>
          </div>
          <span
            className={`px-2.5 py-1 rounded-full text-[10px] font-bold flex items-center gap-1 ${
              hasTotp
                ? 'bg-[var(--app-primary-light)] text-[var(--app-primary)]'
                : 'bg-slate-100 dark:bg-slate-800 text-[var(--app-text-muted)]'
            }`}
          >
            <span className="w-1.5 h-1.5 rounded-full bg-current" />
            <span>{hasTotp ? 'Active & Protected' : 'Not Activated'}</span>
          </span>
        </div>

        <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="space-y-1">
            <p className="text-xs font-bold text-[var(--app-text)]">
              {hasTotp
                ? 'Authenticator App is Active (Google Authenticator / 1Password)'
                : 'Add an extra layer of protection to your account'}
            </p>
            <p className="text-[11px] text-[var(--app-text-muted)] leading-relaxed">
              {hasTotp
                ? `Protected since ${new Date(user.totp_enabled_at!).toLocaleDateString()}. Every bank payout requires a 6-digit one-time code.`
                : 'Recommended for all Delala brokers and registered buyers conducting escrow-backed transactions.'}
            </p>
          </div>

          <button
            type="button"
            onClick={onOpenTotpModal}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer border-0 flex items-center gap-1.5 shrink-0 shadow-xs ${
              hasTotp
                ? 'bg-[var(--app-danger-light)] text-[var(--app-danger)] hover:bg-[var(--app-danger)] hover:text-white'
                : 'bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)]'
            }`}
          >
            <span className="material-symbols-outlined text-[16px]">
              {hasTotp ? 'lock_open' : 'add_moderator'}
            </span>
            <span>{hasTotp ? 'Configure / Disable 2FA' : 'Enable 2FA Protection'}</span>
          </button>
        </div>
      </div>

      {/* Password Management */}
      <div className="p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs space-y-4">
        <div className="flex items-center justify-between pb-3 border-b border-[var(--app-border)]">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center">
              <span className="material-symbols-outlined text-[18px]">key</span>
            </div>
            <div>
              <h3 className="text-sm font-bold text-[var(--app-text)]">Password Management</h3>
              <p className="text-[11px] text-[var(--app-text-muted)]">
                Update account password or trigger secure email reset
              </p>
            </div>
          </div>
        </div>

        {passwordSuccess && (
          <div className="p-3 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] text-xs flex items-center gap-2">
            <span className="material-symbols-outlined text-[16px]">check_circle</span>
            <span>{passwordSuccess}</span>
          </div>
        )}

        {resetEmailSuccess && (
          <div className="p-3 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] text-xs flex items-center gap-2">
            <span className="material-symbols-outlined text-[16px]">mail</span>
            <span>{resetEmailSuccess}</span>
          </div>
        )}

        {passwordError && (
          <div className="p-3 rounded-xl bg-[var(--app-danger-light)] text-[var(--app-danger)] text-xs flex items-center gap-2">
            <span className="material-symbols-outlined text-[16px]">error</span>
            <span>{passwordError}</span>
          </div>
        )}

        <form onSubmit={handlePasswordSubmit} className="space-y-3.5">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label className="block text-xs font-medium text-[var(--app-text)] mb-1">
                Current password
              </label>
              <input
                type="password"
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                placeholder="••••••••"
                className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm focus:outline-none focus:border-[var(--app-primary)]"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-[var(--app-text)] mb-1">
                New password (min 8)
              </label>
              <input
                type="password"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                placeholder="••••••••"
                required
                minLength={8}
                className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm focus:outline-none focus:border-[var(--app-primary)]"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-[var(--app-text)] mb-1">
                Confirm new password
              </label>
              <input
                type="password"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                placeholder="••••••••"
                required
                minLength={8}
                className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm focus:outline-none focus:border-[var(--app-primary)]"
              />
            </div>
          </div>

          <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
            <button
              type="button"
              onClick={handleSendResetEmail}
              disabled={isSendingResetEmail || !user?.email}
              className="text-xs font-semibold text-[var(--app-primary)] hover:underline flex items-center gap-1 cursor-pointer bg-transparent border-0 p-0 disabled:opacity-50"
            >
              <span className="material-symbols-outlined text-[15px]">forward_to_inbox</span>
              <span>Send password reset link to registered email</span>
            </button>

            <button
              type="submit"
              disabled={isUpdatingPassword || !newPassword}
              className="px-4 py-2 rounded-xl text-xs font-bold bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)] transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs disabled:opacity-50"
            >
              {isUpdatingPassword ? 'Saving password...' : 'Save new password'}
            </button>
          </div>
        </form>
      </div>

      {/* Immutable System Roles & Safeguards */}
      <div className="p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs space-y-4">
        <div className="flex items-center justify-between pb-3 border-b border-[var(--app-border)]">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center">
              <span className="material-symbols-outlined text-[18px]">policy</span>
            </div>
            <div>
              <h3 className="text-sm font-bold text-[var(--app-text)]">System role and guardrails</h3>
              <p className="text-[11px] text-[var(--app-text-muted)]">
                Permanent platform security roles and anti-circumvention compliance
              </p>
            </div>
          </div>
          <span className="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-[var(--app-text-muted)] border border-[var(--app-border)] flex items-center gap-1">
            <span className="material-symbols-outlined text-[12px]">lock</span>
            <span>Immutable</span>
          </span>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div className="p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-1">
            <span className="text-xs font-medium text-[var(--app-text-muted)]">
              Assigned system role
            </span>
            <p className="text-sm font-bold text-[var(--app-text)] capitalize">
              {isDelala ? 'Delala Broker' : isGuest ? 'Guest Buyer' : 'Registered Buyer'}
            </p>
          </div>

          <div className="p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-1">
            <span className="text-xs font-medium text-[var(--app-text-muted)]">
              Anti-circumvention strikes
            </span>
            <div className="flex items-center gap-1.5">
              <span className="text-sm font-bold text-[var(--app-primary)]">
                {user?.circumvention_strike_count || 0} / 3 Strikes
              </span>
              <span className="px-1.5 py-0.2 rounded text-[9px] font-bold bg-[var(--app-primary-light)] text-[var(--app-primary)]">
                Clean
              </span>
            </div>
          </div>

          <div className="p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-1">
            <span className="text-xs font-medium text-[var(--app-text-muted)]">
              Regulatory status
            </span>
            <p className="text-xs font-semibold text-[var(--app-text)] flex items-center gap-1">
              <span className="material-symbols-outlined text-[14px] text-[var(--app-primary)]">
                check_circle
              </span>
              <span>NBE Sandbox Compliant</span>
            </p>
          </div>
        </div>

        <p className="text-[11px] text-[var(--app-text-muted)] leading-relaxed">
          Users cannot arbitrarily alter their platform roles or reset circumvention strike counts. Attempting to bypass the escrow mechanism by soliciting off-platform cash deposits will trigger an automated kill-switch suspension under AR-4.
        </p>
      </div>
    </div>
  );
};
