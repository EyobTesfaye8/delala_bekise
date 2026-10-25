import React, { useState, useContext } from 'react';
import { BuyerContext } from '../../context/buyerContextDef';
import { useAuth } from '../../context/useAuth';

interface JitUpgradeModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export const JitUpgradeModal: React.FC<JitUpgradeModalProps> = ({ isOpen, onClose }) => {
  const buyerContext = useContext(BuyerContext);
  const { user, migrateGuest } = useAuth();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (password !== passwordConfirm) {
      setError('Passwords do not match');
      return;
    }
    if (password.length < 8) {
      setError('Password must be at least 8 characters');
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      if (user?.type === 'guest') {
        await migrateGuest({
          full_name: user?.full_name || 'Registered Buyer',
          email: email.trim(),
          password,
          password_confirmation: passwordConfirm,
        });
      }

      if (buyerContext) {
        buyerContext.upgradeGuestAccount(email);
      }

      setIsSubmitting(false);
      onClose();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Upgrade failed. Please check credentials.');
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs animate-in fade-in duration-200">
      <div className="w-full max-w-md bg-[var(--app-card)] text-[var(--app-text)] rounded-2xl shadow-2xl border border-[var(--app-border)] p-6 sm:p-7 relative">
        <div className="flex items-center justify-between pb-4 border-b border-[var(--app-border)] mb-5">
          <div className="flex items-center gap-2.5">
            <div className="w-9 h-9 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center">
              <span className="material-symbols-outlined text-[20px]">upgrade</span>
            </div>
            <div>
              <h2 className="text-base font-bold tracking-tight">Upgrade to Registered Buyer</h2>
              <p className="text-xs text-[var(--app-text-muted)]">Save your active jobs &amp; escrow history permanently</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-lg flex items-center justify-center text-[var(--app-text-muted)] hover:text-[var(--app-text)] hover:bg-[var(--app-card-subtle)] transition-colors cursor-pointer border-0 bg-transparent"
          >
            <span className="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        {/* Pre-verified Phone Display */}
        <div className="mb-4 p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <span className="material-symbols-outlined text-[18px] text-[var(--app-primary)]">verified</span>
            <div>
              <p className="text-xs font-medium text-[var(--app-text-muted)]">Pre-verified phone</p>
              <p className="font-financial-numeric text-sm font-semibold text-[var(--app-text)]">{user?.phone || '+251 911 44 89 20'}</p>
            </div>
          </div>
          <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-[var(--app-primary-light)] text-[var(--app-primary)]">
            Verified (SMS)
          </span>
        </div>

        {error && (
          <div className="mb-4 p-3 rounded-xl bg-[var(--app-danger-light)] text-[var(--app-danger)] text-xs flex items-center gap-2">
            <span className="material-symbols-outlined text-[16px]">error</span>
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-3.5">
          <div>
            <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">
              Account Email
            </label>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="e.g. sara.haile@example.com"
              className="w-full px-3.5 py-2.5 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] transition-colors"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">
              Create Password
            </label>
            <input
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Minimum 8 characters"
              className="w-full px-3.5 py-2.5 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] transition-colors"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">
              Confirm Password
            </label>
            <input
              type="password"
              required
              value={passwordConfirm}
              onChange={(e) => setPasswordConfirm(e.target.value)}
              placeholder="Repeat your password"
              className="w-full px-3.5 py-2.5 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] transition-colors"
            />
          </div>

          <div className="pt-2 flex items-center justify-end gap-2.5">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-xs font-semibold rounded-xl text-[var(--app-text-muted)] hover:text-[var(--app-text)] transition-colors cursor-pointer border-0 bg-transparent"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="px-5 py-2 text-xs font-semibold rounded-xl bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white shadow-xs transition-all cursor-pointer border-0 flex items-center gap-1.5"
            >
              {isSubmitting ? (
                <>
                  <span className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                  <span>Upgrading account...</span>
                </>
              ) : (
                <>
                  <span className="material-symbols-outlined text-[16px]">check</span>
                  <span>Upgrade account</span>
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
