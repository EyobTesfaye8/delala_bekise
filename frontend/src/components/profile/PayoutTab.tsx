import React, { useState } from 'react';
import type { PayoutDestination } from '../../services/profileService';

interface PayoutTabProps {
  destinations: PayoutDestination[];
  isLoading: boolean;
  onOpenAddModal: () => void;
  onSetDefault: (id: number) => Promise<void>;
  onDelete: (id: number) => Promise<void>;
}

export const PayoutTab: React.FC<PayoutTabProps> = ({
  destinations,
  isLoading,
  onOpenAddModal,
  onSetDefault,
  onDelete,
}) => {
  const [actionId, setActionId] = useState<number | null>(null);

  const handleMakeDefault = async (id: number) => {
    try {
      setActionId(id);
      await onSetDefault(id);
    } finally {
      setActionId(null);
    }
  };

  const handleDelete = async (id: number) => {
    if (window.confirm('Are you sure you want to remove this payout destination?')) {
      try {
        setActionId(id);
        await onDelete(id);
      } finally {
        setActionId(null);
      }
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Banner & Action */}
      <div className="p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h3 className="text-base font-bold text-[var(--app-text)]">Payout Destinations</h3>
          <p className="text-xs text-[var(--app-text-muted)]">
            Manage your Ethiopian commercial bank accounts and Telebirr wallet
          </p>
        </div>

        <button
          type="button"
          onClick={onOpenAddModal}
          className="px-4 py-2.5 rounded-xl text-xs font-bold bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)] transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs shrink-0"
        >
          <span className="material-symbols-outlined text-[16px]">add</span>
          <span>Add Payout Destination</span>
        </button>
      </div>

      {/* Destinations List */}
      <div className="space-y-3">
        {isLoading ? (
          <div className="p-12 text-center rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)]">
            <span className="w-6 h-6 border-2 border-[var(--app-primary)] border-t-transparent rounded-full animate-spin inline-block mb-2" />
            <p className="text-xs text-[var(--app-text-muted)]">Loading payout accounts...</p>
          </div>
        ) : destinations.length === 0 ? (
          <div className="p-10 text-center rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] space-y-3">
            <div className="w-12 h-12 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center mx-auto">
              <span className="material-symbols-outlined text-[24px]">account_balance</span>
            </div>
            <h4 className="text-sm font-bold text-[var(--app-text)]">No Payout Accounts Configured</h4>
            <p className="text-xs text-[var(--app-text-muted)] max-w-sm mx-auto">
              Add your Commercial Bank of Ethiopia (CBE) account or Telebirr phone number to receive your escrow commissions.
            </p>
            <button
              onClick={onOpenAddModal}
              className="px-4 py-2 rounded-xl text-xs font-bold bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)] transition-all cursor-pointer border-0 inline-flex items-center gap-1.5 shadow-xs"
            >
              <span className="material-symbols-outlined text-[16px]">add</span>
              <span>Add First Payout Account</span>
            </button>
          </div>
        ) : (
          destinations.map((dest) => {
            const isTelebirr = dest.bank_code === 'TELEBIRR';
            const isBusy = actionId === dest.id;

            return (
              <div
                key={dest.id}
                className={`p-5 rounded-2xl bg-[var(--app-card)] border transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4 ${
                  dest.is_default
                    ? 'border-[var(--app-primary)]/40 shadow-xs ring-1 ring-[var(--app-primary)]/10'
                    : 'border-[var(--app-border)]'
                }`}
              >
                <div className="flex items-start sm:items-center gap-3.5">
                  <div
                    className={`w-11 h-11 rounded-xl flex items-center justify-center shrink-0 ${
                      isTelebirr
                        ? 'bg-[var(--app-accent-light)] text-[var(--app-accent)]'
                        : 'bg-[var(--app-primary-light)] text-[var(--app-primary)]'
                    }`}
                  >
                    <span className="material-symbols-outlined text-[22px]">
                      {isTelebirr ? 'phone_android' : 'account_balance'}
                    </span>
                  </div>

                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <h4 className="text-sm font-bold text-[var(--app-text)]">{dest.bank_name}</h4>
                      {dest.is_default && (
                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--app-primary-light)] text-[var(--app-primary)] border border-[var(--app-primary)]/20 flex items-center gap-0.5">
                          <span className="material-symbols-outlined text-[12px]">check</span>
                          <span>Default Destination</span>
                        </span>
                      )}
                    </div>

                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[var(--app-text-muted)]">
                      <span>
                        Account Holder: <strong className="text-[var(--app-text)]">{dest.account_holder_name}</strong>
                      </span>
                      <span>•</span>
                      <span>
                        Number: <strong className="font-mono text-[var(--app-text)]">{dest.account_number}</strong>
                      </span>
                    </div>
                  </div>
                </div>

                {/* Card Actions */}
                <div className="flex items-center justify-end gap-2 pt-2 sm:pt-0 border-t sm:border-t-0 border-[var(--app-border)]">
                  {!dest.is_default && (
                    <button
                      type="button"
                      onClick={() => handleMakeDefault(dest.id)}
                      disabled={isBusy}
                      className="px-3 py-1.5 rounded-xl text-xs font-semibold border border-[var(--app-border)] hover:border-[var(--app-primary)] text-[var(--app-text)] transition-all cursor-pointer bg-transparent disabled:opacity-50"
                    >
                      {isBusy ? 'Updating...' : 'Set as Default'}
                    </button>
                  )}

                  <button
                    type="button"
                    onClick={() => handleDelete(dest.id)}
                    disabled={isBusy}
                    className="w-8 h-8 rounded-xl border border-[var(--app-border)] hover:border-[var(--app-danger)] hover:text-[var(--app-danger)] text-[var(--app-text-muted)] flex items-center justify-center transition-colors cursor-pointer bg-transparent disabled:opacity-50"
                    title="Delete Destination"
                  >
                    <span className="material-symbols-outlined text-[16px]">delete</span>
                  </button>
                </div>
              </div>
            );
          })
        )}
      </div>

      {/* Institutional Callout */}
      <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-start gap-3 text-xs text-[var(--app-text-muted)]">
        <span className="material-symbols-outlined text-[18px] text-[var(--app-primary)] shrink-0 mt-0.5">
          verified_user
        </span>
        <div className="space-y-1">
          <p className="font-semibold text-[var(--app-text)]">
            National Bank of Ethiopia (NBE) Escrow Settlement Protocol
          </p>
          <p className="text-[11px] leading-relaxed">
            All commission payouts are automatically processed to your default destination within 24 hours of the Buyer releasing escrow funds upon asset handover. Payout account holder name must match your verified KYC profile name.
          </p>
        </div>
      </div>
    </div>
  );
};
