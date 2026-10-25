import React from 'react';
import { Link } from 'react-router-dom';
import type { User } from '../../types/auth';
import type { DelalaTrustProfile } from '../../services/profileService';

interface ProfileHeaderProps {
  user: User | null;
  trustProfile: DelalaTrustProfile | null;
  onEditName: () => void;
  onEditAvatar: () => void;
  onOpenUpgrade?: () => void;
}

export const ProfileHeader: React.FC<ProfileHeaderProps> = ({
  user,
  trustProfile,
  onEditName,
  onEditAvatar,
  onOpenUpgrade,
}) => {
  const isDelala = user?.type === 'delala';
  const isGuest = user?.type === 'guest';
  const initials = (user?.full_name || (isGuest ? 'Guest Buyer' : 'Delala User'))
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('');

  const memberSince = user?.created_at
    ? new Date(user.created_at).toLocaleDateString('en-US', {
        month: 'short',
        year: 'numeric',
      })
    : 'Oct 2026';

  const avatarSrc = user?.avatar_s3_key?.startsWith('http')
    ? user.avatar_s3_key
    : user?.avatar_s3_key
    ? `https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=240&auto=format&fit=crop&q=80`
    : null;

  return (
    <div className="bg-[var(--app-card)] border border-[var(--app-border)] rounded-2xl p-6 sm:p-8 shadow-xs relative overflow-hidden">
      {/* Decorative subtle background gradient */}
      <div className="absolute top-0 right-0 w-96 h-96 bg-gradient-to-bl from-[var(--app-primary)]/5 via-transparent to-transparent pointer-events-none rounded-full blur-3xl -mr-20 -mt-20" />

      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
        {/* Left: Avatar & Identity Details */}
        <div className="flex flex-col sm:flex-row items-center sm:items-start gap-5 text-center sm:text-left">
          {/* Avatar with Camera Overlay */}
          <div className="relative group shrink-0">
            <div className="w-24 h-24 sm:w-28 sm:h-28 rounded-full overflow-hidden border-2 border-[var(--app-border)] shadow-md bg-[var(--app-primary-light)] flex items-center justify-center transition-all group-hover:border-[var(--app-primary)]">
              {avatarSrc ? (
                <img
                  src={avatarSrc}
                  alt={user?.full_name || 'Profile'}
                  className="w-full h-full object-cover"
                />
              ) : (
                <span className="text-3xl font-extrabold text-[var(--app-primary)]">
                  {initials}
                </span>
              )}
            </div>

            {/* Online Status Dot */}
            <span
              className="absolute bottom-1 right-1 w-4 h-4 rounded-full bg-[var(--app-primary)] border-2 border-[var(--app-card)]"
              title="Online & Available"
            />

            {/* Hover Camera Overlay Button */}
            <button
              onClick={onEditAvatar}
              className="absolute inset-0 rounded-full bg-black/40 text-white flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer border-0"
              title="Change profile photo"
            >
              <span className="material-symbols-outlined text-[24px]">photo_camera</span>
              <span className="text-xs font-medium mt-0.5">Change photo</span>
            </button>
          </div>

          {/* User Names & Meta */}
          <div className="space-y-1.5">
            <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2.5">
              <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-[var(--app-text)]">
                {user?.full_name || (isGuest ? 'Guest buyer (temporary)' : 'Delala broker')}
              </h1>
              <button
                onClick={onEditName}
                className="w-7 h-7 rounded-lg border border-[var(--app-border)] bg-[var(--app-card-subtle)] hover:border-[var(--app-primary)] text-[var(--app-text-muted)] hover:text-[var(--app-primary)] flex items-center justify-center transition-colors cursor-pointer"
                title="Edit full legal name"
              >
                <span className="material-symbols-outlined text-[15px]">edit</span>
              </button>
            </div>

            {/* Role & Verification Badges */}
            <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2">
              {isDelala ? (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[var(--app-primary-light)] text-[var(--app-primary)] border border-[var(--app-primary)]/20">
                  <span className="material-symbols-outlined text-[14px]">verified</span>
                  <span>Verified delala broker</span>
                </span>
              ) : isGuest ? (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[var(--app-accent-light)] text-[var(--app-accent)] border border-[var(--app-accent)]/20">
                  <span className="material-symbols-outlined text-[14px]">schedule</span>
                  <span>Guest session (24h)</span>
                </span>
              ) : (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[var(--app-primary-light)] text-[var(--app-primary)] border border-[var(--app-primary)]/20">
                  <span className="material-symbols-outlined text-[14px]">verified_user</span>
                  <span>Registered buyer</span>
                </span>
              )}

              <span className="inline-flex items-center gap-1 text-xs text-[var(--app-text-muted)]">
                <span className="material-symbols-outlined text-[14px]">location_on</span>
                <span>Addis Ababa, Ethiopia</span>
              </span>

              <span className="inline-flex items-center gap-1 text-xs text-[var(--app-text-muted)]">
                <span className="material-symbols-outlined text-[14px]">calendar_today</span>
                <span>Member since {memberSince}</span>
              </span>
            </div>

            {/* Bio / Headline */}
            <p className="text-xs text-[var(--app-text-muted)] max-w-xl leading-relaxed pt-1">
              {user?.bio ||
                (isDelala
                  ? 'Specialized in Bole and Kirkos commercial, residential, and automotive escrow deal brokerage.'
                  : 'Verified marketplace participant for escrow-protected vehicle and property transactions.')}
            </p>
          </div>
        </div>

        {/* Right: Upwork-style Performance Stats & Quick Actions */}
        <div className="flex flex-col sm:flex-row lg:flex-col items-center sm:items-end justify-between gap-4 pt-4 lg:pt-0 border-t lg:border-t-0 border-[var(--app-border)]">
          {/* Stats Bar */}
          <div className="flex items-center gap-4 sm:gap-6 bg-[var(--app-card-subtle)] border border-[var(--app-border)] px-4 py-2.5 rounded-xl">
            {/* Job Success Score */}
            <div className="text-center sm:text-right">
              <div className="flex items-center justify-center sm:justify-end gap-1 text-[var(--app-primary)]">
                <span className="material-symbols-outlined text-[16px]">military_tech</span>
                <span className="font-bold text-sm">100%</span>
              </div>
              <p className="text-xs text-[var(--app-text-muted)] font-medium">Job success</p>
            </div>

            <div className="h-7 w-[1px] bg-[var(--app-border)]" />

            {/* Rating */}
            <div className="text-center sm:text-right">
              <div className="flex items-center justify-center sm:justify-end gap-1 text-[var(--app-accent)]">
                <span className="material-symbols-outlined text-[16px]">star</span>
                <span className="font-bold text-sm">
                  {trustProfile?.average_rating ? trustProfile.average_rating.toFixed(1) : '5.0'}
                </span>
              </div>
              <p className="text-xs text-[var(--app-text-muted)] font-medium">
                {trustProfile?.total_reviews_count || 1} review
              </p>
            </div>

            <div className="h-7 w-[1px] bg-[var(--app-border)]" />

            {/* Completed Deals */}
            <div className="text-center sm:text-right">
              <p className="font-bold text-sm text-[var(--app-text)]">
                {trustProfile?.completed_deals_count || 1}
              </p>
              <p className="text-xs text-[var(--app-text-muted)] font-medium">Deals closed</p>
            </div>
          </div>

          {/* Quick Action Navigation */}
          <div className="flex items-center gap-2">
            {isGuest && onOpenUpgrade && (
              <button
                onClick={onOpenUpgrade}
                className="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[var(--app-accent)] text-white hover:bg-[var(--app-accent-hover)] transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs"
              >
                <span className="material-symbols-outlined text-[16px]">upgrade</span>
                <span>Upgrade account</span>
              </button>
            )}

            <Link
              to={isDelala ? '/delala/feed' : '/buyer'}
              className="px-3.5 py-1.5 rounded-xl text-xs font-semibold border border-[var(--app-border)] bg-[var(--app-card)] hover:border-[var(--app-primary)] text-[var(--app-text)] transition-all cursor-pointer flex items-center gap-1.5 no-underline"
            >
              <span className="material-symbols-outlined text-[16px]">
                {isDelala ? 'feed' : 'shopping_bag'}
              </span>
              <span>{isDelala ? 'Back to feed' : 'Back to deals'}</span>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
};
