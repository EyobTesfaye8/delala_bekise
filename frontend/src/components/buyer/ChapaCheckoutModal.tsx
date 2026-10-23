import React, { useState } from 'react';
import { useBuyer } from '../../context/useBuyer';

interface ChapaCheckoutModalProps {
  isOpen: boolean;
  jobId: number;
  proposalId: number;
  itemId: number;
  itemTitle: string;
  delalaName: string;
  commissionAmountEtb: number;
  onClose: () => void;
}

export const ChapaCheckoutModal: React.FC<ChapaCheckoutModalProps> = ({
  isOpen,
  jobId,
  proposalId,
  itemId,
  itemTitle,
  delalaName,
  commissionAmountEtb,
  onClose,
}) => {
  const { acceptAndFundItem } = useBuyer();
  const [selectedChannel, setSelectedChannel] = useState<'telebirr' | 'cbe' | 'card'>('telebirr');
  const [isProcessing, setIsProcessing] = useState(false);

  if (!isOpen) return null;

  const handlePay = () => {
    setIsProcessing(true);
    setTimeout(() => {
      acceptAndFundItem(jobId, proposalId, itemId, commissionAmountEtb);
      setIsProcessing(false);
      onClose();
    }, 1000);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/45 backdrop-blur-xs animate-in fade-in duration-200">
      <div className="w-full max-w-lg bg-[var(--app-card)] text-[var(--app-text)] rounded-2xl shadow-2xl border border-[var(--app-border)] p-6 sm:p-8 relative">
        <div className="flex items-center justify-between pb-4 border-b border-[var(--app-border)] mb-5">
          <div className="flex items-center gap-2.5">
            <div className="w-10 h-10 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center font-bold text-sm">
              <span className="material-symbols-outlined text-[22px]">lock</span>
            </div>
            <div>
              <h2 className="text-base font-bold tracking-tight">Fund Escrow with Chapa</h2>
              <p className="text-xs text-[var(--app-text-muted)]">Locked securely until you inspect &amp; declare handover</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-lg flex items-center justify-center text-[var(--app-text-muted)] hover:text-[var(--app-text)] hover:bg-[var(--app-card-subtle)] transition-colors cursor-pointer border-0 bg-transparent"
          >
            <span className="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        {/* Item & Delala Recap Card */}
        <div className="mb-5 p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-2.5">
          <div className="flex items-center justify-between">
            <span className="text-xs text-[var(--app-text-muted)]">Broker / Delala</span>
            <span className="text-xs font-bold text-[var(--app-text)] flex items-center gap-1">
              <span>{delalaName}</span>
              <span className="material-symbols-outlined text-[15px] text-[var(--app-primary)]">verified</span>
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-xs text-[var(--app-text-muted)]">Selected Proposal Item</span>
            <span className="text-xs font-semibold text-[var(--app-text)] max-w-[240px] truncate text-right">
              {itemTitle}
            </span>
          </div>
          <div className="pt-2 border-t border-[var(--app-border)] flex items-center justify-between">
            <span className="text-xs font-semibold text-[var(--app-text)]">Broker Commission Amount</span>
            <span className="font-financial-numeric text-base font-bold text-[var(--app-primary)]">
              {commissionAmountEtb.toLocaleString()} ETB
            </span>
          </div>
        </div>

        {/* Payment Channels */}
        <div className="mb-5 space-y-2">
          <label className="block text-xs font-semibold text-[var(--app-text)]">Select Payment Channel</label>
          <div className="grid grid-cols-3 gap-2.5">
            <button
              type="button"
              onClick={() => setSelectedChannel('telebirr')}
              className={`p-3 rounded-xl border text-left transition-all cursor-pointer ${
                selectedChannel === 'telebirr'
                  ? 'border-[var(--app-primary)] bg-[var(--app-primary-light)]'
                  : 'border-[var(--app-border)] bg-[var(--app-card-subtle)] hover:border-[var(--app-primary)]/50'
              }`}
            >
              <div className="flex items-center justify-between mb-1">
                <span className="text-xs font-bold text-[var(--app-text)]">Telebirr</span>
                {selectedChannel === 'telebirr' && (
                  <span className="w-2 h-2 rounded-full bg-[var(--app-primary)]"></span>
                )}
              </div>
              <p className="text-[10px] text-[var(--app-text-muted)]">USSD &amp; SuperApp</p>
            </button>

            <button
              type="button"
              onClick={() => setSelectedChannel('cbe')}
              className={`p-3 rounded-xl border text-left transition-all cursor-pointer ${
                selectedChannel === 'cbe'
                  ? 'border-[var(--app-primary)] bg-[var(--app-primary-light)]'
                  : 'border-[var(--app-border)] bg-[var(--app-card-subtle)] hover:border-[var(--app-primary)]/50'
              }`}
            >
              <div className="flex items-center justify-between mb-1">
                <span className="text-xs font-bold text-[var(--app-text)]">CBE Birr</span>
                {selectedChannel === 'cbe' && (
                  <span className="w-2 h-2 rounded-full bg-[var(--app-primary)]"></span>
                )}
              </div>
              <p className="text-[10px] text-[var(--app-text-muted)]">Direct Bank Debit</p>
            </button>

            <button
              type="button"
              onClick={() => setSelectedChannel('card')}
              className={`p-3 rounded-xl border text-left transition-all cursor-pointer ${
                selectedChannel === 'card'
                  ? 'border-[var(--app-primary)] bg-[var(--app-primary-light)]'
                  : 'border-[var(--app-border)] bg-[var(--app-card-subtle)] hover:border-[var(--app-primary)]/50'
              }`}
            >
              <div className="flex items-center justify-between mb-1">
                <span className="text-xs font-bold text-[var(--app-text)]">Cards</span>
                {selectedChannel === 'card' && (
                  <span className="w-2 h-2 rounded-full bg-[var(--app-primary)]"></span>
                )}
              </div>
              <p className="text-[10px] text-[var(--app-text-muted)]">Visa / Mastercard</p>
            </button>
          </div>
        </div>

        {/* Trust Badges */}
        <div className="mb-6 p-3 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-center justify-between text-[11px] text-[var(--app-text-muted)]">
          <div className="flex items-center gap-1.5">
            <span className="material-symbols-outlined text-[16px] text-[var(--app-primary)]">verified_user</span>
            <span>NBE Sandbox Protected</span>
          </div>
          <span>•</span>
          <div className="flex items-center gap-1.5">
            <span className="material-symbols-outlined text-[16px] text-[var(--app-primary)]">lock_reset</span>
            <span>100% Refund Guarantee</span>
          </div>
        </div>

        <div className="flex items-center justify-end gap-3">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2.5 text-xs font-semibold rounded-xl text-[var(--app-text-muted)] hover:text-[var(--app-text)] transition-colors cursor-pointer border-0 bg-transparent"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={handlePay}
            disabled={isProcessing}
            className="px-6 py-2.5 text-xs font-semibold rounded-xl bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white shadow-xs transition-all cursor-pointer border-0 flex items-center gap-2"
          >
            {isProcessing ? (
              <>
                <span className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                <span>Connecting to Chapa...</span>
              </>
            ) : (
              <>
                <span className="material-symbols-outlined text-[16px]">payment</span>
                <span>Pay {commissionAmountEtb.toLocaleString()} ETB with Chapa</span>
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
};
