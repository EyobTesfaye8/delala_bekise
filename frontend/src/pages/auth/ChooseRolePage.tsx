import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AuthLayout } from '../../components/auth/AuthLayout';
import { useAuthFlow } from '../../context/useAuthFlow';
import { useAuth } from '../../context/useAuth';
import { MigrateGuestModal } from '../../components/auth/MigrateGuestModal';

export const ChooseRolePage: React.FC = () => {
  const navigate = useNavigate();
  const { updateFlowData } = useAuthFlow();
  const { user, isAuthenticated, isGuest, logout } = useAuth();
  const [showMigrateModal, setShowMigrateModal] = useState(false);
  const [successToast, setSuccessToast] = useState<string | null>(null);

  const handleSelectRole = (role: 'guest' | 'buyer' | 'delala', path: string) => {
    updateFlowData({ role });
    navigate(path);
  };

  const handleMigrationSuccess = () => {
    setSuccessToast('Account successfully upgraded to permanent Buyer!');
    setTimeout(() => setSuccessToast(null), 4000);
  };

  return (
    <AuthLayout>
      <div className="relative w-full max-w-5xl mx-auto px-6 py-12 md:py-20 flex flex-col items-center">
        {/* Ambient background accents */}
        <div className="absolute -top-12 left-1/2 -translate-x-1/2 w-96 h-96 bg-primary-fixed/30 rounded-full blur-3xl pointer-events-none -z-10"></div>
        <div className="absolute top-1/3 -right-24 w-80 h-80 bg-surface-container-high/60 rounded-full blur-3xl pointer-events-none -z-10"></div>

        {/* Active Session Card if user is already logged in */}
        {isAuthenticated && user && (
          <div className="w-full mb-10 p-5 rounded-2xl bg-surface-container-lowest border border-outline-variant/30 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4 animate-in fade-in duration-200">
            <div className="flex items-center gap-3.5">
              <div className="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-lg">
                {user.full_name ? user.full_name.charAt(0).toUpperCase() : (user.type === 'delala' ? 'D' : 'B')}
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <span className="font-bold text-on-surface text-base">
                    {user.full_name || user.phone}
                  </span>
                  <span className="px-2 py-0.5 rounded text-[11px] font-semibold uppercase tracking-wider bg-surface-container-high text-on-surface-variant">
                    {user.type}
                  </span>
                  <span className="inline-flex items-center gap-1 text-[11px] text-primary font-medium">
                    <span className="w-1.5 h-1.5 rounded-full bg-primary"></span>
                    {user.status}
                  </span>
                </div>
                <p className="text-xs text-on-surface-variant mt-0.5">
                  {user.email || user.phone} • Sanctum Session Active
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2">
              {isGuest && (
                <button
                  onClick={() => setShowMigrateModal(true)}
                  className="px-3.5 py-1.5 rounded-lg bg-primary text-on-primary text-xs font-semibold hover:opacity-90 transition-opacity cursor-pointer border-0 flex items-center gap-1.5 shadow-xs"
                >
                  <span className="material-symbols-outlined text-[16px]">upgrade</span>
                  Upgrade to Permanent
                </button>
              )}
              <button
                onClick={() => logout()}
                className="px-3 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface text-xs font-medium transition-colors cursor-pointer border-0 flex items-center gap-1.5"
              >
                <span className="material-symbols-outlined text-[16px]">logout</span>
                Sign Out
              </button>
            </div>
          </div>
        )}

        <header className="text-center max-w-xl mx-auto mb-14 space-y-3">
          <h1 className="font-headline text-3xl sm:text-4xl text-on-surface font-bold tracking-tight">
            How would you like to use Delala Bekise?
          </h1>
          <p className="text-base text-on-surface-variant max-w-md mx-auto">
            Select an account type to continue.
          </p>
        </header>

        <div className="w-full grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
          {/* Card 1: One-Time Buyer (Guest) */}
          <button
            onClick={() => handleSelectRole('guest', '/auth/guest')}
            className="group relative flex flex-col justify-between p-8 rounded-xl bg-surface-container-lowest shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1.5 text-left cursor-pointer border-0"
          >
            <div className="flex flex-col">
              <div className="flex items-center justify-between mb-8">
                <div className="w-12 h-12 rounded-xl bg-surface-container-low flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-on-primary transition-colors duration-200">
                  <span className="material-symbols-outlined text-[24px]">flash_on</span>
                </div>
                <span className="px-2.5 py-1 rounded-full bg-surface-container-low text-xs text-primary font-semibold tracking-wide">
                  Fastest
                </span>
              </div>
              <span className="text-xs text-on-surface-variant font-medium uppercase tracking-wider mb-1">
                Instant Access
              </span>
              <h2 className="text-xl font-bold text-on-surface mb-3 group-hover:text-primary transition-colors">
                One-Time Buyer
              </h2>
              <p className="text-sm text-on-surface-variant leading-relaxed">
                Post a single escrow request quickly without a password. Phone verification only.
              </p>
            </div>
            <div className="mt-10 pt-6 flex items-center justify-between">
              <span className="text-sm text-on-surface group-hover:text-primary font-semibold transition-colors">
                Continue as Guest
              </span>
              <div className="w-9 h-9 rounded-full bg-surface-container-low flex items-center justify-center text-on-surface group-hover:bg-primary group-hover:text-on-primary transition-all duration-200 group-hover:translate-x-1">
                <span className="material-symbols-outlined text-[18px]">arrow_forward</span>
              </div>
            </div>
          </button>

          {/* Card 2: Registered Buyer */}
          <button
            onClick={() => handleSelectRole('buyer', '/auth/register/buyer')}
            className="group relative flex flex-col justify-between p-8 rounded-xl bg-surface-container-lowest shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1.5 text-left cursor-pointer border-0"
          >
            <div className="flex flex-col">
              <div className="flex items-center justify-between mb-8">
                <div className="w-12 h-12 rounded-xl bg-surface-container-low flex items-center justify-center text-on-surface group-hover:bg-primary group-hover:text-on-primary transition-colors duration-200">
                  <span className="material-symbols-outlined text-[24px]">person</span>
                </div>
                <span className="px-2.5 py-1 rounded-full bg-surface-container-low text-xs text-on-surface-variant font-medium tracking-wide">
                  Full History
                </span>
              </div>
              <span className="text-xs text-on-surface-variant font-medium uppercase tracking-wider mb-1">
                Personal Account
              </span>
              <h2 className="text-xl font-bold text-on-surface mb-3 group-hover:text-primary transition-colors">
                Registered Buyer
              </h2>
              <p className="text-sm text-on-surface-variant leading-relaxed">
                Track repeat property &amp; vehicle acquisitions with an immutable transaction log.
              </p>
            </div>
            <div className="mt-10 pt-6 flex items-center justify-between">
              <span className="text-sm text-on-surface group-hover:text-primary font-semibold transition-colors">
                Create Buyer Account
              </span>
              <div className="w-9 h-9 rounded-full bg-surface-container-low flex items-center justify-center text-on-surface group-hover:bg-primary group-hover:text-on-primary transition-all duration-200 group-hover:translate-x-1">
                <span className="material-symbols-outlined text-[18px]">arrow_forward</span>
              </div>
            </div>
          </button>

          {/* Card 3: Delala Broker */}
          <button
            onClick={() => handleSelectRole('delala', '/auth/register/delala')}
            className="group relative flex flex-col justify-between p-8 rounded-xl bg-surface-container-lowest shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1.5 text-left cursor-pointer border-0"
          >
            <div className="flex flex-col">
              <div className="flex items-center justify-between mb-8">
                <div className="w-12 h-12 rounded-xl bg-surface-container-low flex items-center justify-center text-on-surface group-hover:bg-primary group-hover:text-on-primary transition-colors duration-200">
                  <span className="material-symbols-outlined text-[24px]">real_estate_agent</span>
                </div>
                <span className="px-2.5 py-1 rounded-full bg-surface-container-low text-xs text-on-surface-variant font-medium tracking-wide">
                  Licensed
                </span>
              </div>
              <span className="text-xs text-on-surface-variant font-medium uppercase tracking-wider mb-1">
                Professional
              </span>
              <h2 className="text-xl font-bold text-on-surface mb-3 group-hover:text-primary transition-colors">
                Delala Broker
              </h2>
              <p className="text-sm text-on-surface-variant leading-relaxed">
                Verified brokers earn commission with instant Telebirr &amp; CBE payouts.
              </p>
            </div>
            <div className="mt-10 pt-6 flex items-center justify-between">
              <span className="text-sm text-on-surface group-hover:text-primary font-semibold transition-colors">
                Register as Broker
              </span>
              <div className="w-9 h-9 rounded-full bg-surface-container-low flex items-center justify-center text-on-surface group-hover:bg-primary group-hover:text-on-primary transition-all duration-200 group-hover:translate-x-1">
                <span className="material-symbols-outlined text-[18px]">arrow_forward</span>
              </div>
            </div>
          </button>
        </div>

        <div className="mt-14 text-center">
          <p className="text-sm text-on-surface-variant">
            Already have an account?{' '}
            <Link
              to="/auth/login"
              className="text-primary hover:underline underline-offset-4 ml-1 inline-flex items-center gap-1 font-semibold"
            >
              <span>Log in</span>
              <span className="material-symbols-outlined text-[16px]">chevron_right</span>
            </Link>
          </p>
        </div>

        <div className="mt-12 flex flex-wrap items-center justify-center gap-8 opacity-75 text-xs text-on-surface-variant">
          <div className="flex items-center gap-2">
            <span className="material-symbols-outlined text-primary text-[18px]">verified_user</span>
            <span>NBE Escrow Compliance</span>
          </div>
          <div className="flex items-center gap-2">
            <span className="material-symbols-outlined text-primary text-[18px]">lock</span>
            <span>Bank-Grade Safeguard</span>
          </div>
          <div className="flex items-center gap-2">
            <span className="material-symbols-outlined text-primary text-[18px]">bolt</span>
            <span>Instant Telebirr &amp; CBE Payouts</span>
          </div>
        </div>
      </div>

      <MigrateGuestModal
        isOpen={showMigrateModal}
        onClose={() => setShowMigrateModal(false)}
        onSuccess={handleMigrationSuccess}
      />

      {successToast && (
        <div className="fixed bottom-6 right-6 z-50 px-4 py-2.5 rounded-xl bg-primary text-on-primary text-xs font-medium shadow-lg flex items-center gap-2">
          <span className="material-symbols-outlined text-[18px]">check_circle</span>
          <span>{successToast}</span>
        </div>
      )}
    </AuthLayout>
  );
};
