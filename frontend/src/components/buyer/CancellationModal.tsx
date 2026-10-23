import React, { useState } from 'react';
import { useBuyer } from '../../context/useBuyer';

interface CancellationModalProps {
  isOpen: boolean;
  escrowId: number;
  delalaName: string;
  commissionAmountEtb: number;
  onClose: () => void;
}

export const CancellationModal: React.FC<CancellationModalProps> = ({
  isOpen,
  escrowId,
  delalaName,
  commissionAmountEtb,
  onClose,
}) => {
  const { requestCancellation } = useBuyer();
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (!isOpen) return null;

  const handleConfirm = () => {
    setIsSubmitting(true);
    setTimeout(() => {
      requestCancellation(escrowId);
      setIsSubmitting(false);
      onClose();
    }, 600);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/45 backdrop-blur-xs animate-in fade-in duration-200">
      <div className="w-full max-w-md bg-[var(--app-card)] text-[var(--app-text)] rounded-2xl shadow-2xl border border-[var(--app-border)] p-6 sm:p-7 relative">
        <div className="flex items-center justify-between pb-4 border-b border-[var(--app-border)] mb-5">
          <div className="flex items-center gap-2.5">
            <div className="w-10 h-10 rounded-xl bg-[var(--app-accent-light)] text-[var(--app-accent)] flex items-center justify-center font-bold">
              <span className="material-symbols-outlined text-[22px]">history</span>
            </div>
            <div>
              <h2 className="text-base font-bold tracking-tight">Request Pre-Handover Cancellation</h2>
              <p className="text-xs text-[var(--app-text-muted)]">Official 48-Hour NBE Escrow Rule</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-lg flex items-center justify-center text-[var(--app-text-muted)] hover:text-[var(--app-text)] hover:bg-[var(--app-card-subtle)] transition-colors cursor-pointer border-0 bg-transparent"
          >
            <span className="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        <div className="space-y-4 mb-6">
          <p className="text-xs text-[var(--app-text)] leading-relaxed">
            You are requesting a cancellation for the <strong className="font-semibold">{commissionAmountEtb.toLocaleString()} ETB</strong> escrow held with <strong className="font-semibold">{delalaName}</strong>.
          </p>

          <div className="p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-2 text-xs">
            <div className="flex items-start gap-2">
              <span className="material-symbols-outlined text-[16px] text-[var(--app-primary)] shrink-0 mt-0.5">timer</span>
              <p className="text-[var(--app-text-muted)]">
                <strong className="text-[var(--app-text)]">48-Hour Broker Window:</strong> Delala {delalaName} will be given 48 hours to either approve your cancellation or claim work performed.
              </p>
            </div>
            <div className="flex items-start gap-2">
              <span className="material-symbols-outlined text-[16px] text-[var(--app-primary)] shrink-0 mt-0.5">autorenew</span>
              <p className="text-[var(--app-text-muted)]">
                <strong className="text-[var(--app-text)]">Guaranteed Auto-Refund:</strong> If the Delala does not respond within 48 hours, the system automatically refunds 100% of your funds to your original payment method.
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-center justify-end gap-2.5">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-xs font-semibold rounded-xl text-[var(--app-text-muted)] hover:text-[var(--app-text)] transition-colors cursor-pointer border-0 bg-transparent"
          >
            Keep Escrow
          </button>
          <button
            type="button"
            onClick={handleConfirm}
            disabled={isSubmitting}
            className="px-5 py-2.5 text-xs font-semibold rounded-xl bg-[var(--app-accent)] hover:bg-[var(--app-accent-hover)] text-white shadow-xs transition-all cursor-pointer border-0 flex items-center gap-1.5"
          >
            {isSubmitting ? (
              <>
                <span className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                <span>Initiating...</span>
              </>
            ) : (
              <>
                <span className="material-symbols-outlined text-[16px]">send</span>
                <span>Start 48h Cancellation</span>
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
};
