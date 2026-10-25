import React, { useState } from 'react';
import { useBuyer } from '../../context/useBuyer';
import { JitUpgradeModal } from '../../components/buyer/JitUpgradeModal';

export const SavedDelalasPage: React.FC = () => {
  const { isGuestMode, bookmarkedDelalas, setRoute } = useBuyer();
  const [jitModalOpen, setJitModalOpen] = useState(false);
  const [sessionRevoked, setSessionRevoked] = useState(false);

  const handleRevokeSessions = () => {
    setSessionRevoked(true);
    setTimeout(() => setSessionRevoked(false), 4000);
  };

  // ==========================================
  // GUEST MODE: Locked Minimalist State
  // ==========================================
  if (isGuestMode) {
    return (
      <div className="max-w-xl mx-auto py-12 text-center space-y-6">
        <div className="rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] p-8 sm:p-12 shadow-xs space-y-5">
          <div className="w-16 h-16 rounded-2xl bg-[var(--app-card-subtle)] text-[var(--app-text-muted)] flex items-center justify-center mx-auto">
            <span className="material-symbols-outlined text-[32px]">lock</span>
          </div>

          <div className="space-y-2">
            <span className="px-3 py-1 rounded-full bg-[var(--app-card-subtle)] text-[var(--app-text-muted)] text-xs font-medium">
              Guest mode restricted
            </span>
            <h2 className="text-xl font-bold tracking-tight text-[var(--app-text)]">
              Saved Delalas &amp; Historical Records Locked
            </h2>
            <p className="text-xs text-[var(--app-text-muted)] max-w-md mx-auto leading-relaxed">
              Bookmarking top-rated Delalas and maintaining a permanent ledger of your property &amp; vehicle acquisitions requires a registered account. Complete the 1-step JIT Upgrade in under 20 seconds.
            </p>
          </div>

          <div className="pt-2">
            <button
              onClick={() => setJitModalOpen(true)}
              className="px-6 py-2.5 rounded-xl bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white text-xs font-bold shadow-xs transition-all cursor-pointer border-0 inline-flex items-center gap-2"
            >
              <span className="material-symbols-outlined text-[16px]">upgrade</span>
              <span>Upgrade account to unlock</span>
            </button>
          </div>
        </div>

        <JitUpgradeModal isOpen={jitModalOpen} onClose={() => setJitModalOpen(false)} />
      </div>
    );
  }

  // ==========================================
  // REGISTERED MODE: Bookmarks & Session Manager
  // ==========================================
  return (
    <div className="space-y-8">
      {/* Header */}
      <div>
        <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-[var(--app-text)]">
          Saved Delalas &amp; Account Security
        </h1>
        <p className="text-xs sm:text-sm text-[var(--app-text-muted)] mt-0.5">
          Your bookmarked broker network and active Sanctum device sessions.
        </p>
      </div>

      {/* Bookmarked Delalas Grid */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-xs font-semibold text-[var(--app-text)]">
            Bookmarked brokers ({bookmarkedDelalas.length})
          </h2>
          <span className="text-xs text-[var(--app-text-muted)]">Verified Ethiopian FinTech Sandbox Licensed</span>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {bookmarkedDelalas.map((delala) => (
            <div
              key={delala.id}
              className="p-5 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs hover:border-[var(--app-primary)]/40 transition-all duration-200 flex flex-col justify-between space-y-4"
            >
              <div className="space-y-3">
                <div className="flex items-start gap-3.5">
                  <img
                    src={delala.avatar}
                    alt={delala.full_name}
                    className="w-12 h-12 rounded-xl object-cover"
                  />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-1.5">
                      <h3 className="text-sm font-bold text-[var(--app-text)] truncate">{delala.full_name}</h3>
                      {delala.is_kyc_verified && (
                        <span className="text-[var(--app-primary)] material-symbols-outlined text-[16px]">
                          verified
                        </span>
                      )}
                    </div>
                    <div className="flex items-center gap-2 text-xs text-[var(--app-text-muted)] mt-0.5">
                      <span className="flex items-center gap-1 font-semibold text-[var(--app-accent)]">
                        <span className="material-symbols-outlined text-[14px]">star</span>
                        {delala.rating}
                      </span>
                      <span>•</span>
                      <span>{delala.deals_completed} deals completed</span>
                    </div>
                    <p className="text-[11px] text-[var(--app-text-muted)] flex items-center gap-1 mt-1">
                      <span className="material-symbols-outlined text-[13px]">location_on</span>
                      <span>{delala.location}</span>
                    </p>
                  </div>
                </div>

                <p className="text-xs text-[var(--app-text-muted)] line-clamp-2 leading-relaxed">
                  {delala.bio}
                </p>

                <div className="flex flex-wrap gap-1.5 pt-1">
                  {delala.categories.map((cat) => (
                    <span
                      key={cat}
                      className="px-2 py-0.5 rounded text-[10px] font-medium bg-[var(--app-card-subtle)] text-[var(--app-text-muted)] border border-[var(--app-border)]"
                    >
                      {cat}
                    </span>
                  ))}
                </div>
              </div>

              <div className="pt-3 border-t border-[var(--app-border)] flex items-center justify-between">
                <span className="text-[11px] text-[var(--app-text-muted)] font-financial-numeric">
                  {delala.phone}
                </span>
                <button
                  onClick={() => setRoute('post-job')}
                  className="px-3.5 py-1.5 rounded-lg bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white text-xs font-semibold shadow-xs transition-all cursor-pointer border-0 flex items-center gap-1.5"
                >
                  <span className="material-symbols-outlined text-[14px]">add</span>
                  <span>Send Requirement</span>
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Active Device Sessions Manager */}
      <div className="rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] p-6 shadow-xs space-y-5">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-[var(--app-border)]">
          <div>
            <h3 className="text-sm font-bold text-[var(--app-text)]">Active Device Sessions</h3>
            <p className="text-xs text-[var(--app-text-muted)] mt-0.5">
              Manage cryptographic Sanctum bearer tokens authorized on your buyer account.
            </p>
          </div>

          <button
            onClick={handleRevokeSessions}
            className="px-3.5 py-1.5 rounded-xl border border-[var(--app-danger)] text-[var(--app-danger)] hover:bg-[var(--app-danger-light)] text-xs font-semibold transition-colors cursor-pointer bg-transparent self-start sm:self-auto flex items-center gap-1.5"
          >
            <span className="material-symbols-outlined text-[15px]">phonelink_erase</span>
            <span>Revoke All Other Sessions</span>
          </button>
        </div>

        {sessionRevoked && (
          <div className="p-3 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] text-xs flex items-center gap-2 animate-in fade-in duration-150">
            <span className="material-symbols-outlined text-[16px]">check_circle</span>
            <span>All secondary active device sessions have been revoked successfully.</span>
          </div>
        )}

        <div className="space-y-3">
          <div className="p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-center justify-between">
            <div className="flex items-center gap-3">
              <span className="material-symbols-outlined text-[20px] text-[var(--app-primary)]">laptop_mac</span>
              <div>
                <p className="text-xs font-bold text-[var(--app-text)]">Chrome on Linux (This Device)</p>
                <p className="text-[11px] text-[var(--app-text-muted)] font-financial-numeric">IP: 127.0.0.1 • Addis Ababa • Active Now</p>
              </div>
            </div>
            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-[var(--app-primary-light)] text-[var(--app-primary)]">
              Current Session
            </span>
          </div>

          <div className="p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-center justify-between">
            <div className="flex items-center gap-3">
              <span className="material-symbols-outlined text-[20px] text-[var(--app-text-muted)]">smartphone</span>
              <div>
                <p className="text-xs font-bold text-[var(--app-text)]">Safari on iPhone (iOS 18)</p>
                <p className="text-[11px] text-[var(--app-text-muted)] font-financial-numeric">IP: 196.189.28.11 • Bole Telebirr Network • 3h ago</p>
              </div>
            </div>
            <span className="text-xs text-[var(--app-text-muted)]">Authorized</span>
          </div>
        </div>
      </div>
    </div>
  );
};
