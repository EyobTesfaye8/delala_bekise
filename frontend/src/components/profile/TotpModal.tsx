import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

interface TotpModalProps {
  isOpen: boolean;
  isEnabled: boolean;
  onClose: () => void;
  onToggle: (enable: boolean, code?: string) => Promise<void>;
}

export const TotpModal: React.FC<TotpModalProps> = ({
  isOpen,
  isEnabled,
  onClose,
  onToggle,
}) => {
  const [code, setCode] = useState('');
  const [secretKey] = useState('DELALA-7B83-9K2M-ET44');
  const [copied, setCopied] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!isOpen) return null;

  const handleCopySecret = () => {
    navigator.clipboard.writeText(secretKey);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (isEnabled) {
      // Disabling 2FA
      try {
        setIsSubmitting(true);
        setError(null);
        await onToggle(false);
        onClose();
      } catch (err: unknown) {
        setError(err instanceof Error ? err.message : 'Failed to disable 2FA.');
      } finally {
        setIsSubmitting(false);
      }
      return;
    }

    // Enabling 2FA
    if (code.length !== 6 || !/^\d+$/.test(code)) {
      setError('Please enter a valid 6-digit verification code.');
      return;
    }

    try {
      setIsSubmitting(true);
      setError(null);
      await onToggle(true, code);
      onClose();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Invalid authenticator code.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <motion.div
          initial={{ opacity: 0, scale: 0.95, y: 10 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.95, y: 10 }}
          className="w-full max-w-md bg-[var(--app-card)] text-[var(--app-text)] rounded-2xl shadow-2xl border border-[var(--app-border)] p-6 relative"
        >
          {/* Header */}
          <div className="flex items-center justify-between pb-4 border-b border-[var(--app-border)] mb-4">
            <div className="flex items-center gap-2.5">
              <div
                className={`w-9 h-9 rounded-xl flex items-center justify-center ${
                  isEnabled
                    ? 'bg-[var(--app-danger-light)] text-[var(--app-danger)]'
                    : 'bg-[var(--app-primary-light)] text-[var(--app-primary)]'
                }`}
              >
                <span className="material-symbols-outlined text-[20px]">
                  {isEnabled ? 'lock_open' : 'security'}
                </span>
              </div>
              <div>
                <h3 className="text-base font-bold tracking-tight">
                  {isEnabled ? 'Disable two-factor authentication' : 'Set up two-factor authentication'}
                </h3>
                <p className="text-xs text-[var(--app-text-muted)]">
                  {isEnabled ? 'Deactivate authenticator protection' : 'Protect payouts and escrow releases'}
                </p>
              </div>
            </div>
            <button
              onClick={onClose}
              disabled={isSubmitting}
              className="w-8 h-8 rounded-lg flex items-center justify-center text-[var(--app-text-muted)] hover:text-[var(--app-text)] hover:bg-[var(--app-card-subtle)] transition-colors cursor-pointer border-0 bg-transparent"
            >
              <span className="material-symbols-outlined text-[20px]">close</span>
            </button>
          </div>

          {error && (
            <div className="mb-4 p-3 rounded-xl bg-[var(--app-danger-light)] text-[var(--app-danger)] text-xs flex items-center gap-2">
              <span className="material-symbols-outlined text-[16px]">error</span>
              <span>{error}</span>
            </div>
          )}

          {isEnabled ? (
            /* Disable Warning View */
            <div className="space-y-4">
              <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] text-xs text-[var(--app-text)] space-y-2">
                <p className="font-semibold text-[var(--app-danger)] flex items-center gap-1.5">
                  <span className="material-symbols-outlined text-[16px]">warning</span>
                  <span>Security Degradation Warning</span>
                </p>
                <p className="text-[var(--app-text-muted)] leading-relaxed">
                  Disabling TOTP will remove the 6-digit confirmation step required for authorising large escrow transactions and bank payouts.
                </p>
              </div>

              <div className="flex items-center justify-end gap-2.5 pt-2 border-t border-[var(--app-border)]">
                <button
                  type="button"
                  onClick={onClose}
                  disabled={isSubmitting}
                  className="px-4 py-2 rounded-xl text-xs font-semibold border border-[var(--app-border)] hover:bg-[var(--app-card-subtle)] transition-all cursor-pointer bg-transparent text-[var(--app-text)]"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={handleSubmit}
                  disabled={isSubmitting}
                  className="px-4 py-2 rounded-xl text-xs font-bold bg-[var(--app-danger)] text-white hover:opacity-90 transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs disabled:opacity-50"
                >
                  {isSubmitting ? 'Deactivating...' : 'Confirm Disable 2FA'}
                </button>
              </div>
            </div>
          ) : (
            /* Enable Setup View */
            <form onSubmit={handleSubmit} className="space-y-4">
              {/* QR Code and Secret */}
              <div className="flex flex-col items-center p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-3">
                <div className="p-2.5 bg-white rounded-xl shadow-xs border border-slate-200">
                  <svg className="w-36 h-36" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" fill="white" />
                    {/* Visual QR Code Representation */}
                    <rect x="10" y="10" width="24" height="24" fill="#0F172A" />
                    <rect x="14" y="14" width="16" height="16" fill="white" />
                    <rect x="18" y="18" width="8" height="8" fill="#059669" />

                    <rect x="66" y="10" width="24" height="24" fill="#0F172A" />
                    <rect x="70" y="14" width="16" height="16" fill="white" />
                    <rect x="74" y="18" width="8" height="8" fill="#059669" />

                    <rect x="10" y="66" width="24" height="24" fill="#0F172A" />
                    <rect x="14" y="70" width="16" height="16" fill="white" />
                    <rect x="18" y="74" width="8" height="8" fill="#059669" />

                    {/* Data grid patterns */}
                    <rect x="40" y="12" width="6" height="6" fill="#0F172A" />
                    <rect x="50" y="18" width="6" height="6" fill="#0F172A" />
                    <rect x="42" y="32" width="6" height="6" fill="#0F172A" />
                    <rect x="54" y="44" width="6" height="6" fill="#059669" />
                    <rect x="38" y="56" width="8" height="6" fill="#0F172A" />
                    <rect x="68" y="40" width="6" height="8" fill="#0F172A" />
                    <rect x="44" y="70" width="6" height="6" fill="#0F172A" />
                    <rect x="58" y="68" width="6" height="6" fill="#0F172A" />
                    <rect x="70" y="74" width="8" height="6" fill="#0F172A" />
                    <rect x="80" y="60" width="6" height="6" fill="#0F172A" />
                    <rect x="20" y="44" width="6" height="6" fill="#0F172A" />
                  </svg>
                </div>

                <div className="w-full text-center space-y-1">
                  <p className="text-[11px] text-[var(--app-text-muted)] font-medium">
                    Scan with Google Authenticator or 1Password, or enter key:
                  </p>
                  <div className="flex items-center justify-center gap-2">
                    <code className="text-xs font-mono font-bold bg-[var(--app-bg)] px-2.5 py-1 rounded-md border border-[var(--app-border)] select-all">
                      {secretKey}
                    </code>
                    <button
                      type="button"
                      onClick={handleCopySecret}
                      className="px-2 py-1 rounded-md text-[11px] font-semibold bg-[var(--app-bg)] border border-[var(--app-border)] hover:border-[var(--app-primary)] text-[var(--app-text)] transition-all cursor-pointer flex items-center gap-1"
                    >
                      <span className="material-symbols-outlined text-[14px]">
                        {copied ? 'check' : 'content_copy'}
                      </span>
                      <span>{copied ? 'Copied' : 'Copy'}</span>
                    </button>
                  </div>
                </div>
              </div>

              {/* 6-Digit Code Input */}
              <div>
                <label className="block text-xs font-medium text-[var(--app-text)] mb-1.5">
                  Enter 6-digit authenticator code
                </label>
                <input
                  type="text"
                  maxLength={6}
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                  placeholder="e.g. 123456"
                  required
                  className="w-full px-3.5 py-2.5 text-center tracking-widest font-mono font-bold text-lg rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] focus:outline-none focus:border-[var(--app-primary)] transition-all"
                />
              </div>

              <div className="flex items-center justify-end gap-2.5 pt-2 border-t border-[var(--app-border)]">
                <button
                  type="button"
                  onClick={onClose}
                  disabled={isSubmitting}
                  className="px-4 py-2 rounded-xl text-xs font-semibold border border-[var(--app-border)] hover:bg-[var(--app-card-subtle)] transition-all cursor-pointer bg-transparent text-[var(--app-text)]"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting || code.length !== 6}
                  className="px-4 py-2 rounded-xl text-xs font-bold bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)] transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs disabled:opacity-50"
                >
                  {isSubmitting ? (
                    <>
                      <span className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                      <span>Verifying...</span>
                    </>
                  ) : (
                    <>
                      <span className="material-symbols-outlined text-[16px]">verified_user</span>
                      <span>Activate two-factor protection</span>
                    </>
                  )}
                </button>
              </div>
            </form>
          )}
        </motion.div>
      </div>
    </AnimatePresence>
  );
};
