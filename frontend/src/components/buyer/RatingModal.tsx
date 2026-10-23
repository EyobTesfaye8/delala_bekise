import React, { useState } from 'react';
import { useBuyer } from '../../context/useBuyer';

interface RatingModalProps {
  isOpen: boolean;
  delalaName: string;
  delalaAvatar: string;
  commissionAmountEtb: number;
  onClose: () => void;
}

export const RatingModal: React.FC<RatingModalProps> = ({
  isOpen,
  delalaName,
  delalaAvatar,
  commissionAmountEtb,
  onClose,
}) => {
  const { confirmHandover } = useBuyer();
  const [rating, setRating] = useState<number>(5);
  const [selectedChips, setSelectedChips] = useState<string[]>(['Prompt Communication', 'Accurate Photos']);
  const [review, setReview] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (!isOpen) return null;

  const chips = [
    'Prompt Communication',
    'Accurate Photos',
    'Fair Commission',
    'Smooth Contract Signing',
    'Expert Neighborhood Knowledge',
  ];

  const toggleChip = (chip: string) => {
    setSelectedChips((prev) =>
      prev.includes(chip) ? prev.filter((c) => c !== chip) : [...prev, chip]
    );
  };

  const handleConfirm = () => {
    setIsSubmitting(true);
    setTimeout(() => {
      confirmHandover(rating, review);
      setIsSubmitting(false);
      onClose();
    }, 800);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/45 backdrop-blur-xs animate-in fade-in duration-200">
      <div className="w-full max-w-md bg-[var(--app-card)] text-[var(--app-text)] rounded-2xl shadow-2xl border border-[var(--app-border)] p-6 sm:p-7 relative">
        <div className="flex items-center justify-between pb-4 border-b border-[var(--app-border)] mb-5">
          <div className="flex items-center gap-2.5">
            <div className="w-10 h-10 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center font-bold">
              <span className="material-symbols-outlined text-[22px]">verified</span>
            </div>
            <div>
              <h2 className="text-base font-bold tracking-tight">Confirm Handover &amp; Release</h2>
              <p className="text-xs text-[var(--app-text-muted)]">Mutual review releases {commissionAmountEtb.toLocaleString()} ETB to Delala</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-lg flex items-center justify-center text-[var(--app-text-muted)] hover:text-[var(--app-text)] hover:bg-[var(--app-card-subtle)] transition-colors cursor-pointer border-0 bg-transparent"
          >
            <span className="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        {/* Delala profile mini card */}
        <div className="flex items-center gap-3.5 p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] mb-5">
          <img
            src={delalaAvatar}
            alt={delalaName}
            className="w-12 h-12 rounded-xl object-cover"
          />
          <div>
            <h3 className="text-sm font-bold text-[var(--app-text)] flex items-center gap-1.5">
              <span>{delalaName}</span>
              <span className="material-symbols-outlined text-[15px] text-[var(--app-primary)]">verified</span>
            </h3>
            <p className="text-xs text-[var(--app-text-muted)]">Licensed Ethiopian Broker • Escrow Handover Complete</p>
          </div>
        </div>

        {/* Star Rating Selector */}
        <div className="text-center mb-5">
          <p className="text-xs font-semibold text-[var(--app-text)] mb-2">Rate your broker experience</p>
          <div className="flex items-center justify-center gap-2">
            {[1, 2, 3, 4, 5].map((star) => (
              <button
                key={star}
                type="button"
                onClick={() => setRating(star)}
                className="w-10 h-10 rounded-xl flex items-center justify-center transition-all cursor-pointer border-0 bg-transparent hover:scale-110"
              >
                <span
                  className={`material-symbols-outlined text-[28px] ${
                    star <= rating ? 'text-[var(--app-accent)] fill-current' : 'text-[var(--app-text-muted)] opacity-40'
                  }`}
                >
                  star
                </span>
              </button>
            ))}
          </div>
          <span className="text-xs font-bold text-[var(--app-accent)] mt-1 block">
            {rating === 5 ? 'Excellent (5.0)' : rating === 4 ? 'Very Good (4.0)' : `${rating}.0 Stars`}
          </span>
        </div>

        {/* Praise chips */}
        <div className="mb-4">
          <p className="text-xs font-semibold text-[var(--app-text)] mb-2">Highlights</p>
          <div className="flex flex-wrap gap-1.5">
            {chips.map((chip) => {
              const active = selectedChips.includes(chip);
              return (
                <button
                  key={chip}
                  type="button"
                  onClick={() => toggleChip(chip)}
                  className={`px-2.5 py-1 rounded-lg text-[11px] font-medium transition-all cursor-pointer border ${
                    active
                      ? 'bg-[var(--app-primary-light)] text-[var(--app-primary)] border-[var(--app-primary)]'
                      : 'bg-[var(--app-card-subtle)] text-[var(--app-text-muted)] border-[var(--app-border)] hover:border-[var(--app-primary)]/40'
                  }`}
                >
                  {chip}
                </button>
              );
            })}
          </div>
        </div>

        {/* Review Input */}
        <div className="mb-5">
          <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">
            Written Feedback (Optional)
          </label>
          <textarea
            rows={2}
            value={review}
            onChange={(e) => setReview(e.target.value)}
            placeholder="Share details on how Delala Abebe assisted you with the property or vehicle..."
            className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] transition-colors resize-none"
          />
        </div>

        {/* Final Actions */}
        <div className="flex items-center justify-end gap-2.5">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-xs font-semibold rounded-xl text-[var(--app-text-muted)] hover:text-[var(--app-text)] transition-colors cursor-pointer border-0 bg-transparent"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={handleConfirm}
            disabled={isSubmitting}
            className="px-5 py-2.5 text-xs font-semibold rounded-xl bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white shadow-xs transition-all cursor-pointer border-0 flex items-center gap-1.5"
          >
            {isSubmitting ? (
              <>
                <span className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                <span>Releasing Funds...</span>
              </>
            ) : (
              <>
                <span className="material-symbols-outlined text-[16px]">lock_open</span>
                <span>Confirm &amp; Release Funds</span>
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
};
