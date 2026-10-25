import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import type { CreatePayoutPayload } from '../../services/profileService';

interface AddPayoutModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (payload: CreatePayoutPayload) => Promise<void>;
}

export const AddPayoutModal: React.FC<AddPayoutModalProps> = ({
  isOpen,
  onClose,
  onSave,
}) => {
  const [payoutType, setPayoutType] = useState<'bank' | 'telebirr'>('bank');
  const [bankCode, setBankCode] = useState('CBE');
  const [accountHolderName, setAccountHolderName] = useState('');
  const [accountNumber, setAccountNumber] = useState('');
  const [telebirrNumber, setTelebirrNumber] = useState('');
  const [isDefault, setIsDefault] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!isOpen) return null;

  const ethiopianBanks = [
    { code: 'CBE', name: 'Commercial Bank of Ethiopia (CBE)' },
    { code: 'AWASH', name: 'Awash International Bank' },
    { code: 'ABYSSINIA', name: 'Bank of Abyssinia' },
    { code: 'DASHEN', name: 'Dashen Bank' },
    { code: 'COOP', name: 'Cooperative Bank of Oromia' },
    { code: 'NIB', name: 'Nib International Bank' },
    { code: 'ZEMEN', name: 'Zemen Bank' },
    { code: 'HIBRET', name: 'Hibret Bank' },
    { code: 'BERHAN', name: 'Berhan Bank' },
    { code: 'WEGAGEN', name: 'Wegagen Bank' },
  ];

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!accountHolderName.trim()) {
      setError('Account holder legal name is required.');
      return;
    }

    if (payoutType === 'bank') {
      if (!accountNumber.trim()) {
        setError('Bank account number is required.');
        return;
      }
      const selectedBank = ethiopianBanks.find((b) => b.code === bankCode);
      const payload: CreatePayoutPayload = {
        bank_code: bankCode,
        bank_name: selectedBank ? selectedBank.name : bankCode,
        account_number: accountNumber.trim(),
        account_holder_name: accountHolderName.trim(),
        is_default: isDefault,
      };

      try {
        setIsSubmitting(true);
        setError(null);
        await onSave(payload);
        onClose();
      } catch (err: unknown) {
        setError(err instanceof Error ? err.message : 'Failed to register payout destination.');
      } finally {
        setIsSubmitting(false);
      }
    } else {
      // Telebirr
      const phone = telebirrNumber.trim();
      if (!phone || !/^(?:\+251|0)[79]\d{8}$/.test(phone)) {
        setError('Please enter a valid Ethiopian mobile phone (e.g. 0911223344 or +251911223344).');
        return;
      }

      const payload: CreatePayoutPayload = {
        bank_code: 'TELEBIRR',
        bank_name: 'Telebirr Mobile Money',
        account_number: phone,
        account_holder_name: accountHolderName.trim(),
        telebirr_number: phone,
        is_default: isDefault,
      };

      try {
        setIsSubmitting(true);
        setError(null);
        await onSave(payload);
        onClose();
      } catch (err: unknown) {
        setError(err instanceof Error ? err.message : 'Failed to register Telebirr payout destination.');
      } finally {
        setIsSubmitting(false);
      }
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
              <div className="w-9 h-9 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center">
                <span className="material-symbols-outlined text-[20px]">account_balance</span>
              </div>
              <div>
                <h3 className="text-base font-bold tracking-tight">Add Payout Destination</h3>
                <p className="text-xs text-[var(--app-text-muted)]">Receive your earned broker commissions</p>
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

          {/* Segmented Type Toggle */}
          <div className="grid grid-cols-2 p-1 bg-[var(--app-bg)] rounded-xl border border-[var(--app-border)] mb-4">
            <button
              type="button"
              onClick={() => setPayoutType('bank')}
              className={`py-2 text-xs font-semibold rounded-lg transition-all cursor-pointer border-0 flex items-center justify-center gap-1.5 ${
                payoutType === 'bank'
                  ? 'bg-[var(--app-card)] text-[var(--app-primary)] shadow-xs'
                  : 'text-[var(--app-text-muted)] bg-transparent'
              }`}
            >
              <span className="material-symbols-outlined text-[16px]">account_balance</span>
              <span>Bank Account</span>
            </button>
            <button
              type="button"
              onClick={() => setPayoutType('telebirr')}
              className={`py-2 text-xs font-semibold rounded-lg transition-all cursor-pointer border-0 flex items-center justify-center gap-1.5 ${
                payoutType === 'telebirr'
                  ? 'bg-[var(--app-card)] text-[var(--app-primary)] shadow-xs'
                  : 'text-[var(--app-text-muted)] bg-transparent'
              }`}
            >
              <span className="material-symbols-outlined text-[16px]">phone_android</span>
              <span>Telebirr Wallet</span>
            </button>
          </div>

          <form onSubmit={handleSubmit} className="space-y-3.5">
            {payoutType === 'bank' ? (
              <>
                <div>
                  <label className="block text-xs font-medium text-[var(--app-text)] mb-1.5">
                    Select bank
                  </label>
                  <select
                    value={bankCode}
                    onChange={(e) => setBankCode(e.target.value)}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm focus:outline-none focus:border-[var(--app-primary)] transition-all"
                  >
                    {ethiopianBanks.map((bank) => (
                      <option key={bank.code} value={bank.code}>
                        {bank.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-medium text-[var(--app-text)] mb-1.5">
                    Bank account number
                  </label>
                  <input
                    type="text"
                    value={accountNumber}
                    onChange={(e) => setAccountNumber(e.target.value.replace(/\s+/g, ''))}
                    placeholder="e.g. 1000123456789"
                    required
                    className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm font-mono focus:outline-none focus:border-[var(--app-primary)] transition-all"
                  />
                </div>
              </>
            ) : (
              <div>
                <label className="block text-xs font-medium text-[var(--app-text)] mb-1.5">
                  Telebirr mobile number
                </label>
                <input
                  type="text"
                  value={telebirrNumber}
                  onChange={(e) => setTelebirrNumber(e.target.value)}
                  placeholder="e.g. 0911223344 or +251911223344"
                  required
                  className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm font-mono focus:outline-none focus:border-[var(--app-primary)] transition-all"
                />
              </div>
            )}

            <div>
              <label className="block text-xs font-medium text-[var(--app-text)] mb-1.5">
                Account holder full name
              </label>
              <input
                type="text"
                value={accountHolderName}
                onChange={(e) => setAccountHolderName(e.target.value)}
                placeholder="Exact name registered on the bank/telebirr account"
                required
                className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm focus:outline-none focus:border-[var(--app-primary)] transition-all"
              />
              <p className="mt-1 text-[11px] text-[var(--app-text-muted)]">
                Must match your Delala verified KYC name to prevent fraud.
              </p>
            </div>

            <label className="flex items-center gap-2 pt-1 cursor-pointer">
              <input
                type="checkbox"
                checked={isDefault}
                onChange={(e) => setIsDefault(e.target.checked)}
                className="rounded border-[var(--app-border)] text-[var(--app-primary)] focus:ring-[var(--app-primary)]"
              />
              <span className="text-xs font-medium text-[var(--app-text)]">
                Make this my default payout destination
              </span>
            </label>

            <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-[var(--app-border)]">
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
                disabled={isSubmitting}
                className="px-4 py-2 rounded-xl text-xs font-bold bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)] transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs disabled:opacity-50"
              >
                {isSubmitting ? (
                  <>
                    <span className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    <span>Saving account...</span>
                  </>
                ) : (
                  <>
                    <span className="material-symbols-outlined text-[16px]">check</span>
                    <span>Save payout destination</span>
                  </>
                )}
              </button>
            </div>
          </form>
        </motion.div>
      </div>
    </AnimatePresence>
  );
};
