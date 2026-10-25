import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/useAuth';
import { MigrateGuestModal } from './MigrateGuestModal';

export interface AuthHeaderProps {
  onNavigate?: (path: string) => void;
}

export const AuthHeader: React.FC<AuthHeaderProps> = ({ onNavigate }) => {
  const { user, isAuthenticated, isGuest, logout } = useAuth();
  const navigate = useNavigate();
  const [showMigrateModal, setShowMigrateModal] = useState(false);
  const [toastMessage, setToastMessage] = useState<string | null>(null);

  const toggleDark = () => {
    document.documentElement.classList.toggle('dark');
  };

  const handleLogout = async () => {
    try {
      await logout();
      navigate('/auth/choose-role');
    } catch {
      // Ignored
    }
  };

  const handleMigrationSuccess = () => {
    setToastMessage('Account successfully upgraded to permanent Buyer!');
    setTimeout(() => setToastMessage(null), 4000);
  };

  return (
    <>
      <header className="fixed top-0 w-full z-50 bg-surface/90 backdrop-blur-md border-b border-outline-variant/20">
        <div className="h-16 max-w-6xl mx-auto px-6 flex items-center justify-between">
          <Link
            to="/auth/choose-role"
            onClick={() => onNavigate?.('/auth/choose-role')}
            className="flex items-center gap-2.5 group no-underline"
          >
            <div className="w-8 h-8 rounded-lg bg-primary flex items-center justify-center text-on-primary font-bold text-sm shadow-sm">
              DB
            </div>
            <div className="flex flex-col">
              <span className="font-headline text-lg text-on-surface font-bold tracking-tight leading-tight">
                Delala Bekise
              </span>
              <span className="text-[10px] text-on-surface-variant font-medium hidden sm:inline leading-none">
                ደላላ በኪሴ
              </span>
            </div>
          </Link>

          <div className="flex items-center gap-3">
            {isAuthenticated && user ? (
              <div className="flex items-center gap-2 sm:gap-3">
                {isGuest ? (
                  <button
                    onClick={() => setShowMigrateModal(true)}
                    className="px-2.5 py-1 rounded-full bg-primary/10 text-primary text-xs font-semibold hover:bg-primary/20 transition-colors cursor-pointer border-0 flex items-center gap-1"
                    title="Convert 24h guest session to permanent account"
                  >
                    <span className="material-symbols-outlined text-[15px]">upgrade</span>
                    <span className="hidden sm:inline">Upgrade Account</span>
                  </button>
                ) : (
                  <span className="hidden sm:inline-block px-2 py-0.5 rounded text-xs font-medium bg-surface-container-high text-on-surface-variant capitalize">
                    {user.type}
                  </span>
                )}

                <div className="flex items-center gap-1.5 text-xs font-medium text-on-surface">
                  <span className="material-symbols-outlined text-[18px] text-on-surface-variant">
                    account_circle
                  </span>
                  <span className="max-w-[120px] sm:max-w-[180px] truncate">
                    {user.full_name || user.phone}
                  </span>
                </div>

                <button
                  aria-label="Sign Out"
                  onClick={handleLogout}
                  type="button"
                  title="Sign Out"
                  className="w-8 h-8 rounded-full bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-error hover:bg-error-container/30 transition-colors cursor-pointer border-0"
                >
                  <span className="material-symbols-outlined text-[18px]">logout</span>
                </button>
              </div>
            ) : null}

            <button
              aria-label="Toggle light/dark theme"
              onClick={toggleDark}
              type="button"
              className="w-8 h-8 rounded-full bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-on-surface transition-colors cursor-pointer border-0"
            >
              <span className="material-symbols-outlined text-[18px]">routine</span>
            </button>
          </div>
        </div>
      </header>

      {/* Migration Modal for Guest Users */}
      <MigrateGuestModal
        isOpen={showMigrateModal}
        onClose={() => setShowMigrateModal(false)}
        onSuccess={handleMigrationSuccess}
      />

      {/* Floating Success Toast */}
      {toastMessage && (
        <div className="fixed bottom-6 right-6 z-50 px-4 py-2.5 rounded-xl bg-primary text-on-primary text-xs font-medium shadow-lg flex items-center gap-2 animate-in fade-in slide-in-from-bottom-2 duration-200">
          <span className="material-symbols-outlined text-[18px]">check_circle</span>
          <span>{toastMessage}</span>
        </div>
      )}
    </>
  );
};

export const AuthFooter: React.FC = () => {
  return (
    <footer className="w-full py-6 text-center text-xs text-on-surface-variant border-t border-outline-variant/10">
      <div className="max-w-6xl mx-auto px-6 flex items-center justify-center gap-3">
        <span>National Bank of Ethiopia FinTech Sandbox</span>
        <span>•</span>
        <span>Encrypted Escrow Infrastructure</span>
      </div>
    </footer>
  );
};

interface AuthLayoutProps {
  children: React.ReactNode;
}

export const AuthLayout: React.FC<AuthLayoutProps> = ({ children }) => {
  return (
    <div className="min-h-screen flex flex-col bg-surface font-body text-on-surface antialiased selection:bg-primary-fixed selection:text-on-primary-fixed">
      <AuthHeader />

      {/* Main Content Area */}
      <main className="w-full pt-16 min-h-[calc(100vh-4rem)] flex flex-col justify-center bg-surface relative">
        <div className="flex flex-col w-full">
          {children}
        </div>
      </main>

      <AuthFooter />
    </div>
  );
};
