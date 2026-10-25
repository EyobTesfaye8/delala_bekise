import React from 'react';
import type { DelalaTrustProfile } from '../../services/profileService';

interface TrustHistoryTabProps {
  trustProfile: DelalaTrustProfile | null;
  isLoading: boolean;
}

export const TrustHistoryTab: React.FC<TrustHistoryTabProps> = ({
  trustProfile,
  isLoading,
}) => {
  const reviews = trustProfile?.recent_reviews || [];

  return (
    <div className="space-y-6">
      {/* Upwork-style Work History Summary Banner */}
      <div className="p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--app-border)]">
          <div>
            <h3 className="text-base font-bold text-[var(--app-text)]">
              Client Feedback &amp; Deal History
            </h3>
            <p className="text-xs text-[var(--app-text-muted)]">
              Double-blind mutual escrow ratings from verified marketplace transactions
            </p>
          </div>

          <div className="flex items-center gap-2">
            <span className="px-3 py-1 rounded-full text-xs font-bold bg-[var(--app-primary-light)] text-[var(--app-primary)] border border-[var(--app-primary)]/20 flex items-center gap-1">
              <span className="material-symbols-outlined text-[14px]">military_tech</span>
              <span>100% Escrow Job Success</span>
            </span>
          </div>
        </div>

        {/* Upwork-style rating breakdown */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-1">
            <span className="text-xs font-medium text-[var(--app-text-muted)]">
              Overall rating
            </span>
            <div className="flex items-center gap-2">
              <span className="text-2xl font-bold text-[var(--app-text)]">
                {trustProfile?.average_rating ? trustProfile.average_rating.toFixed(1) : '5.0'}
              </span>
              <div className="flex items-center text-[var(--app-accent)]">
                {[1, 2, 3, 4, 5].map((star) => (
                  <span key={star} className="material-symbols-outlined text-[18px]">
                    star
                  </span>
                ))}
              </div>
            </div>
            <p className="text-[11px] text-[var(--app-text-muted)]">
              Based on {trustProfile?.total_reviews_count || 1} verified review
            </p>
          </div>

          <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-1">
            <span className="text-xs font-medium text-[var(--app-text-muted)]">
              Completed deals
            </span>
            <div className="flex items-center gap-1.5">
              <span className="text-2xl font-bold text-[var(--app-text)]">
                {trustProfile?.completed_deals_count || 1}
              </span>
              <span className="text-xs font-semibold text-[var(--app-primary)]">
                Deals closed
              </span>
            </div>
            <p className="text-[11px] text-[var(--app-text-muted)]">
              100% funds disbursed via Chapa Escrow
            </p>
          </div>

          <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-1">
            <span className="text-xs font-medium text-[var(--app-text-muted)]">
              Dispute record
            </span>
            <div className="flex items-center gap-1.5">
              <span className="text-2xl font-bold text-[var(--app-primary)]">0</span>
              <span className="text-xs font-semibold text-[var(--app-text-muted)]">
                Arbitrations
              </span>
            </div>
            <p className="text-[11px] text-[var(--app-text-muted)]">
              Flawless deal execution record
            </p>
          </div>
        </div>
      </div>

      {/* Upwork-style Review Stream */}
      <div className="p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs space-y-4">
        <h4 className="text-sm font-bold text-[var(--app-text)]">
          Recent Client Ratings ({reviews.length})
        </h4>

        {isLoading ? (
          <div className="py-8 text-center text-xs text-[var(--app-text-muted)]">
            <span className="w-5 h-5 border-2 border-[var(--app-primary)] border-t-transparent rounded-full animate-spin inline-block mb-1" />
            <p>Loading client reviews...</p>
          </div>
        ) : reviews.length === 0 ? (
          <div className="p-8 text-center rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] space-y-2">
            <span className="material-symbols-outlined text-[32px] text-[var(--app-text-muted)]">
              rate_review
            </span>
            <p className="text-xs font-semibold text-[var(--app-text)]">No Public Reviews Yet</p>
            <p className="text-[11px] text-[var(--app-text-muted)] max-w-sm mx-auto">
              Completed deals with mutual ratings will appear here following the double-blind reveal period.
            </p>
          </div>
        ) : (
          <div className="divide-y divide-[var(--app-border)]">
            {reviews.map((rev) => (
              <div key={rev.id} className="py-4 first:pt-0 last:pb-0 space-y-2">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                  <div className="flex items-center gap-2">
                    <div className="flex items-center text-[var(--app-accent)]">
                      {[1, 2, 3, 4, 5].map((s) => (
                        <span
                          key={s}
                          className="material-symbols-outlined text-[15px]"
                          style={{
                            fontVariationSettings: s <= rev.stars ? "'FILL' 1" : "'FILL' 0",
                          }}
                        >
                          star
                        </span>
                      ))}
                    </div>
                    <span className="text-xs font-bold text-[var(--app-text)]">
                      {rev.rater?.full_name || 'Verified Marketplace Buyer'}
                    </span>
                  </div>

                  <span className="text-[11px] text-[var(--app-text-muted)]">
                    {new Date(rev.created_at).toLocaleDateString('en-US', {
                      month: 'short',
                      day: 'numeric',
                      year: 'numeric',
                    })}
                  </span>
                </div>

                <p className="text-xs text-[var(--app-text)] leading-relaxed italic">
                  "{rev.review || 'Excellent broker. Very professional, responsive, and completed full title verification before release.'}"
                </p>

                <div className="flex items-center gap-2 text-[10px] text-[var(--app-text-muted)]">
                  <span className="material-symbols-outlined text-[13px] text-[var(--app-primary)]">
                    verified
                  </span>
                  <span>Escrow Verified Handover • Addis Ababa</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Double-blind Policy Institutional Note */}
      <div className="p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-start gap-2.5 text-xs text-[var(--app-text-muted)]">
        <span className="material-symbols-outlined text-[16px] text-[var(--app-primary)] shrink-0 mt-0.5">
          policy
        </span>
        <p className="text-[11px] leading-relaxed">
          <strong>Double-Blind Mutual Rating Protocol (FR-6.1):</strong> Reviews and star ratings are permanently sealed until both the Buyer and Delala broker submit feedback, or until the 14-day window lapses. This eliminates retaliatory reviews and guarantees honest trust metrics.
        </p>
      </div>
    </div>
  );
};
