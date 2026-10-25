import React, { useState, useEffect } from 'react';
import { useBuyer } from '../../context/useBuyer';
import { RatingModal } from '../../components/buyer/RatingModal';
import { DisputeModal } from '../../components/buyer/DisputeModal';
import { CancellationModal } from '../../components/buyer/CancellationModal';
import type { EscrowState } from '../../types/buyer';

export const ActiveEscrowsPage: React.FC = () => {
  const { escrows, activeEscrowPreviewState, setEscrowPreviewState, openChatForJob } = useBuyer();

  // Modals
  const [ratingModalOpen, setRatingModalOpen] = useState(false);
  const [disputeModalOpen, setDisputeModalOpen] = useState(false);
  const [cancellationModalOpen, setCancellationModalOpen] = useState(false);

  // Live 120-Hour Countdown Timer (114 hours, 42 mins, 15 seconds)
  const [secondsRemaining, setSecondsRemaining] = useState<number>(114 * 3600 + 42 * 60 + 15);

  useEffect(() => {
    const timer = setInterval(() => {
      setSecondsRemaining((prev) => (prev > 0 ? prev - 1 : 0));
    }, 1000);
    return () => clearInterval(timer);
  }, []);

  const formatCountdown = (totalSec: number) => {
    const days = Math.floor(totalSec / 86400);
    const hours = Math.floor((totalSec % 86400) / 3600);
    const minutes = Math.floor((totalSec % 3600) / 60);
    const seconds = totalSec % 60;

    return `${String(days).padStart(2, '0')}d : ${String(hours).padStart(2, '0')}h : ${String(minutes).padStart(2, '0')}m : ${String(seconds).padStart(2, '0')}s`;
  };

  // Primary active deal for previewing states
  const mainDeal = escrows[1] || escrows[0];

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-[var(--app-text)]">
            Active Escrows &amp; Handover Center
          </h1>
          <p className="text-xs sm:text-sm text-[var(--app-text-muted)] mt-0.5">
            National Bank of Ethiopia (NBE) regulatory compliant escrow with locked funds &amp; double-blind protection.
          </p>
        </div>

        {/* State Preview Pill (Demo Control) */}
        <div className="flex items-center gap-1.5 p-1 rounded-xl bg-[var(--app-card)] border border-[var(--app-border)] self-start sm:self-auto">
          <span className="text-xs font-medium text-[var(--app-text-muted)] px-2">
            Demo state:
          </span>
          {(['FUNDED', 'HANDOVER_DECLARED', 'DISPUTED'] as EscrowState[]).map((st) => (
            <button
              key={st}
              onClick={() => setEscrowPreviewState(st)}
              className={`px-2.5 py-1 rounded-lg text-xs font-medium transition-all cursor-pointer border-0 ${
                activeEscrowPreviewState === st
                  ? 'bg-[var(--app-primary)] text-white shadow-xs'
                  : 'text-[var(--app-text-muted)] hover:text-[var(--app-text)] bg-transparent'
              }`}
            >
              {st === 'FUNDED' && '1. Funded'}
              {st === 'HANDOVER_DECLARED' && '2. Handover declared'}
              {st === 'DISPUTED' && '3. Disputed'}
            </button>
          ))}
        </div>
      </div>

      {/* Main High-Trust Escrow Card */}
      {mainDeal && (
        <div
          className={`rounded-2xl bg-[var(--app-card)] border transition-all duration-200 p-6 sm:p-8 shadow-xs space-y-6 ${
            activeEscrowPreviewState === 'HANDOVER_DECLARED'
              ? 'border-[var(--app-primary)] ring-2 ring-[var(--app-primary)]/10'
              : activeEscrowPreviewState === 'DISPUTED'
              ? 'border-[var(--app-danger)] ring-2 ring-[var(--app-danger)]/10'
              : 'border-[var(--app-border)]'
          }`}
        >
          {/* Top Status Bar & Locked Amount */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-[var(--app-border)]">
            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <span className="px-2.5 py-0.5 rounded text-xs font-medium bg-[var(--app-primary-light)] text-[var(--app-primary)]">
                  {mainDeal.category_name}
                </span>
                <span className="text-xs text-[var(--app-text-muted)]">Escrow ID #{mainDeal.id}</span>
              </div>
              <h2 className="text-lg sm:text-xl font-bold text-[var(--app-text)]">
                {mainDeal.item_name}
              </h2>
              <p className="text-xs text-[var(--app-text-muted)]">
                Associated request: <strong className="text-[var(--app-text)]">{mainDeal.job_title}</strong>
              </p>
            </div>

            {/* Locked Commission Badge */}
            <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] text-left sm:text-right shrink-0">
              <span className="text-xs font-medium text-[var(--app-text-muted)] block">
                Escrow commission held
              </span>
              <span className="font-financial-numeric text-xl font-extrabold text-[var(--app-primary)] block">
                {mainDeal.commission_amount_etb.toLocaleString()} ETB
              </span>
              <span className="text-[10px] text-[var(--app-primary)] font-medium flex items-center gap-1 sm:justify-end mt-0.5">
                <span className="material-symbols-outlined text-[13px]">lock</span>
                <span>Chapa Vault Encrypted</span>
              </span>
            </div>
          </div>

          {/* Broker Information Bar */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)]">
            <div className="flex items-center gap-3.5">
              <img
                src={mainDeal.delala_avatar}
                alt={mainDeal.delala_name}
                className="w-11 h-11 rounded-xl object-cover"
              />
              <div>
                <div className="flex items-center gap-2">
                  <h3 className="text-sm font-bold text-[var(--app-text)]">{mainDeal.delala_name}</h3>
                  <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center gap-0.5">
                    <span className="material-symbols-outlined text-[13px]">verified</span>
                    <span>Verified Broker</span>
                  </span>
                </div>
                <p className="text-xs text-[var(--app-text-muted)] mt-0.5">
                  Verified Contact: <strong className="font-financial-numeric text-[var(--app-text)]">{mainDeal.delala_phone}</strong>
                </p>
              </div>
            </div>

            <button
              onClick={() => openChatForJob(mainDeal.job_id, mainDeal.delala_id)}
              className="px-3.5 py-1.5 rounded-lg bg-[var(--app-card)] hover:bg-[var(--app-border)] text-[var(--app-text)] text-xs font-semibold transition-colors cursor-pointer border border-[var(--app-border)] flex items-center gap-1.5 self-start sm:self-auto"
            >
              <span className="material-symbols-outlined text-[16px]">chat</span>
              <span>Open Escrow Chat</span>
            </button>
          </div>

          {/* ======================================================== */}
          {/* STATE 1: FUNDED (In Progress Offline) */}
          {/* ======================================================== */}
          {activeEscrowPreviewState === 'FUNDED' && (
            <div className="space-y-5 animate-in fade-in duration-200">
              <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-start gap-3">
                <div className="w-8 h-8 rounded-lg bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center shrink-0">
                  <span className="material-symbols-outlined text-[20px]">explore</span>
                </div>
                <div className="space-y-1">
                  <h4 className="text-xs font-bold text-[var(--app-text)]">
                    Inspection &amp; Offline Acquisition in Progress
                  </h4>
                  <p className="text-xs text-[var(--app-text-muted)] leading-relaxed">
                    Delala {mainDeal.delala_name} is currently escorting you or coordinating the inspection with the property owner/car seller. Contact information has been fully unlocked. Once you sign the offline agreement or title transfer, the broker will declare handover.
                  </p>
                </div>
              </div>

              <div className="flex items-center justify-between pt-2">
                <span className="text-xs text-[var(--app-text-muted)]">
                  Need to cancel before meeting the seller?
                </span>
                <button
                  onClick={() => setCancellationModalOpen(true)}
                  className="px-3.5 py-2 rounded-xl text-xs font-semibold text-[var(--app-text-muted)] hover:text-[var(--app-danger)] hover:bg-[var(--app-card-subtle)] transition-colors cursor-pointer border-0 flex items-center gap-1.5"
                >
                  <span className="material-symbols-outlined text-[16px]">cancel</span>
                  <span>Request Cancellation (48h Rule)</span>
                </button>
              </div>
            </div>
          )}

          {/* ======================================================== */}
          {/* STATE 2: HANDOVER_DECLARED (Action Required) */}
          {/* ======================================================== */}
          {activeEscrowPreviewState === 'HANDOVER_DECLARED' && (
            <div className="space-y-6 animate-in fade-in duration-200">
              {/* Live 120-Hour Auto-Release Countdown Banner */}
              <div className="p-5 rounded-xl bg-[var(--app-primary-light)] border border-[var(--app-primary)]/40 text-center space-y-2">
                <span className="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-[var(--app-primary)] text-white text-xs font-medium">
                  <span className="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                  <span>Handover declared by broker, 120-hour window</span>
                </span>

                <h3 className="text-sm font-bold text-[var(--app-text)]">
                  Please confirm property inspection or title transfer
                </h3>

                <div className="pt-2">
                  <span className="font-financial-numeric text-2xl sm:text-3xl font-extrabold text-[var(--app-primary)] tracking-tight">
                    {formatCountdown(secondsRemaining)}
                  </span>
                  <p className="text-xs text-[var(--app-text-muted)] mt-1">
                    Auto-releases to broker upon timer expiration if no dispute is logged.
                  </p>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex flex-col sm:flex-row items-center justify-end gap-3 pt-2">
                <button
                  onClick={() => setDisputeModalOpen(true)}
                  className="w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-bold text-[var(--app-danger)] border border-[var(--app-danger)] hover:bg-[var(--app-danger-light)] transition-all cursor-pointer bg-transparent flex items-center justify-center gap-1.5"
                >
                  <span className="material-symbols-outlined text-[16px]">report</span>
                  <span>Dispute Handover</span>
                </button>

                <button
                  onClick={() => setRatingModalOpen(true)}
                  className="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white text-xs font-bold shadow-xs transition-all cursor-pointer border-0 flex items-center justify-center gap-2"
                >
                  <span className="material-symbols-outlined text-[16px]">verified</span>
                  <span>Confirm Handover &amp; Release Funds</span>
                </button>
              </div>
            </div>
          )}

          {/* ======================================================== */}
          {/* STATE 3: DISPUTED (Frozen) */}
          {/* ======================================================== */}
          {activeEscrowPreviewState === 'DISPUTED' && (
            <div className="space-y-5 animate-in fade-in duration-200">
              <div className="p-4 rounded-xl bg-[var(--app-danger-light)] border border-[var(--app-danger)]/40 text-[var(--app-danger)] space-y-2">
                <div className="flex items-center gap-2">
                  <span className="material-symbols-outlined text-[20px]">gavel</span>
                  <span className="text-xs font-semibold">
                    Escrow frozen, under platform admin review
                  </span>
                </div>
                <p className="text-xs leading-relaxed text-[var(--app-text)]">
                  Reason Logged: <strong>{mainDeal.dispute_reason || 'Item Not As Described'}</strong>. The commission amount of {mainDeal.commission_amount_etb.toLocaleString()} ETB is securely locked in escrow vault and cannot be released until an Admin Maker-Checker resolves the claim.
                </p>
              </div>

              {/* Status Indicator: Broker Proof of Work */}
              <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-3">
                <h4 className="text-xs font-semibold text-[var(--app-text)]">
                  Evidence pipeline status
                </h4>
                <div className="flex items-center justify-between text-xs">
                  <span className="text-[var(--app-text-muted)]">Broker Transfer Receipt:</span>
                  {mainDeal.has_delala_receipt ? (
                    <span className="font-semibold text-[var(--app-primary)] flex items-center gap-1">
                      <span className="material-symbols-outlined text-[15px]">check_circle</span>
                      <span>Delala Receipt Uploaded (Maker-Checker Reviewing)</span>
                    </span>
                  ) : (
                    <span className="font-semibold text-[var(--app-accent)] flex items-center gap-1">
                      <span className="material-symbols-outlined text-[15px]">pending</span>
                      <span>Pending Delala Submission (Within 48h)</span>
                    </span>
                  )}
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Rating & Release Modal */}
      {mainDeal && (
        <RatingModal
          isOpen={ratingModalOpen}
          delalaName={mainDeal.delala_name}
          delalaAvatar={mainDeal.delala_avatar}
          commissionAmountEtb={mainDeal.commission_amount_etb}
          onClose={() => setRatingModalOpen(false)}
        />
      )}

      {/* Dispute Modal */}
      <DisputeModal
        isOpen={disputeModalOpen}
        onClose={() => setDisputeModalOpen(false)}
      />

      {/* Cancellation Modal */}
      {mainDeal && (
        <CancellationModal
          isOpen={cancellationModalOpen}
          escrowId={mainDeal.id}
          delalaName={mainDeal.delala_name}
          commissionAmountEtb={mainDeal.commission_amount_etb}
          onClose={() => setCancellationModalOpen(false)}
        />
      )}
    </div>
  );
};
