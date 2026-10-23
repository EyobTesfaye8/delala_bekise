import React, { useState } from 'react';
import { useBuyer } from '../../context/useBuyer';

interface DisputeModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export const DisputeModal: React.FC<DisputeModalProps> = ({ isOpen, onClose }) => {
  const { submitDispute } = useBuyer();
  const [reason, setReason] = useState<string>('Item Not As Described');
  const [notes, setNotes] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (!isOpen) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setTimeout(() => {
      submitDispute(reason, notes);
      setIsSubmitting(false);
      onClose();
    }, 800);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/45 backdrop-blur-xs animate-in fade-in duration-200">
      <div className="w-full max-w-md bg-[var(--app-card)] text-[var(--app-text)] rounded-2xl shadow-2xl border border-[var(--app-border)] p-6 sm:p-7 relative">
        <div className="flex items-center justify-between pb-4 border-b border-[var(--app-border)] mb-5">
          <div className="flex items-center gap-2.5">
            <div className="w-10 h-10 rounded-xl bg-[var(--app-danger-light)] text-[var(--app-danger)] flex items-center justify-center font-bold">
              <span className="material-symbols-outlined text-[22px]">gavel</span>
            </div>
            <div>
              <h2 className="text-base font-bold tracking-tight text-[var(--app-danger)]">Dispute Handover</h2>
              <p className="text-xs text-[var(--app-text-muted)]">Freezes escrow for Delala Platform Maker-Checker Review</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-lg flex items-center justify-center text-[var(--app-text-muted)] hover:text-[var(--app-text)] hover:bg-[var(--app-card-subtle)] transition-colors cursor-pointer border-0 bg-transparent"
          >
            <span className="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-[var(--app-text)] mb-1.5">
              Select Primary Dispute Reason
            </label>
            <select
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              className="w-full px-3.5 py-2.5 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-danger)] focus:outline-hidden text-[var(--app-text)] transition-colors"
            >
              <option value="Item Not As Described">Item Not As Described (Discrepancy in specs/condition)</option>
              <option value="Seller No-Show">Seller No-Show (Seller did not attend scheduled inspection)</option>
              <option value="Deal Fell Through">Deal Fell Through (Offline acquisition cancelled)</option>
              <option value="Broker Commission Dispute">Broker Commission Dispute (Unresolved rate disagreement)</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-semibold text-[var(--app-text)] mb-1.5">
              Factual Explanation &amp; Evidence Notes
            </label>
            <textarea
              required
              rows={3}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Explain clearly what occurred during the on-site inspection..."
              className="w-full px-3.5 py-2.5 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-danger)] focus:outline-hidden text-[var(--app-text)] transition-colors resize-none"
            />
          </div>

          <div className="p-3 rounded-xl bg-[var(--app-danger-light)] text-[var(--app-danger)] text-xs flex items-start gap-2">
            <span className="material-symbols-outlined text-[16px] shrink-0 mt-0.5">warning</span>
            <p className="leading-relaxed">
              Once submitted, funds are immediately frozen. The Delala will be notified to submit their proof-of-work receipt within 48 hours for admin adjudication.
            </p>
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
              className="px-5 py-2.5 text-xs font-semibold rounded-xl bg-[var(--app-danger)] hover:opacity-90 text-white shadow-xs transition-all cursor-pointer border-0 flex items-center gap-1.5"
            >
              {isSubmitting ? (
                <>
                  <span className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                  <span>Freezing Escrow...</span>
                </>
              ) : (
                <>
                  <span className="material-symbols-outlined text-[16px]">report_problem</span>
                  <span>Submit Formal Dispute</span>
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
