import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { useBuyer } from '../../context/useBuyer';
import { NotificationPopover } from './NotificationPopover';
import { JitUpgradeModal } from './JitUpgradeModal';
import { MyJobsPage } from '../../pages/buyer/MyJobsPage';
import { PostJobPage } from '../../pages/buyer/PostJobPage';
import { NegotiationChatPage } from '../../pages/buyer/NegotiationChatPage';
import { ActiveEscrowsPage } from '../../pages/buyer/ActiveEscrowsPage';
import { SavedDelalasPage } from '../../pages/buyer/SavedDelalasPage';
import type { BuyerRoute } from '../../types/buyer';

export const BuyerShell: React.FC = () => {
  const {
    currentRoute,
    selectedJobId,
    setRoute,
    isGuestMode,
    toggleGuestMode,
    unreadNotificationCount,
    unreadMessageCount,
    activeEscrowCount,
  } = useBuyer();

  const [notificationOpen, setNotificationOpen] = useState(false);
  const [jitModalOpen, setJitModalOpen] = useState(false);

  // Theme toggle function
  const toggleTheme = () => {
    document.documentElement.classList.toggle('dark');
  };

  // Breadcrumbs text
  const getBreadcrumb = () => {
    switch (currentRoute) {
      case 'my-jobs':
        return selectedJobId ? 'Buyer Portal / My Jobs / Proposal Review Inbox' : 'Buyer Portal / My Jobs & Proposals';
      case 'post-job':
        return 'Buyer Portal / Post a Request';
      case 'messages':
        return 'Buyer Portal / Negotiation Chat';
      case 'escrows':
        return 'Buyer Portal / Active Escrows';
      case 'saved':
        return 'Buyer Portal / Saved Delalas & Security';
      default:
        return 'Buyer Portal';
    }
  };

  // Nav items configuration
  const navItems: {
    id: BuyerRoute;
    label: string;
    icon: string;
    isCta?: boolean;
    badgeCount?: number;
    lockedInGuest?: boolean;
  }[] = [
    {
      id: 'my-jobs',
      label: 'My Jobs & Proposals',
      icon: 'work_outline',
    },
    {
      id: 'post-job',
      label: 'Post a Request',
      icon: 'add_circle',
      isCta: true,
    },
    {
      id: 'messages',
      label: 'Messages',
      icon: 'chat',
      badgeCount: unreadMessageCount,
    },
    {
      id: 'escrows',
      label: 'Active Escrows',
      icon: 'lock',
      badgeCount: activeEscrowCount,
    },
    {
      id: 'saved',
      label: 'Saved Delalas & History',
      icon: 'bookmark',
      lockedInGuest: isGuestMode,
    },
  ];

  return (
    <div className="min-h-screen bg-[var(--app-bg)] text-[var(--app-text)] font-sans flex flex-col antialiased">
      {/* ======================================================== */}
      {/* JIT Guest Upgrade Banner (Visible ONLY in Guest Mode)    */}
      {/* ======================================================== */}
      {isGuestMode && (
        <div className="w-full bg-[var(--app-primary-light)] border-b border-[var(--app-primary)]/20 py-2.5 px-4 sm:px-6 flex items-center justify-between text-xs animate-in fade-in duration-200">
          <div className="flex items-center gap-2 text-[var(--app-primary)]">
            <span className="material-symbols-outlined text-[16px] shrink-0">timer</span>
            <span>
              <strong>Guest Session (24h)</strong> • Save your active jobs, proposals &amp; escrows permanently.
            </span>
          </div>

          <button
            onClick={() => setJitModalOpen(true)}
            className="px-3.5 py-1 rounded-lg bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white font-bold transition-all cursor-pointer border-0 flex items-center gap-1 shadow-xs shrink-0"
          >
            <span className="material-symbols-outlined text-[14px]">upgrade</span>
            <span>Upgrade Account</span>
          </button>
        </div>
      )}

      {/* ======================================================== */}
      {/* Top Header Bar                                           */}
      {/* ======================================================== */}
      <header className="h-16 w-full border-b border-[var(--app-border)] bg-[var(--app-card)]/90 backdrop-blur-md sticky top-0 z-30 flex items-center justify-between px-4 sm:px-8">
        {/* Brand & Breadcrumbs */}
        <div className="flex items-center gap-4">
          <button
            onClick={() => setRoute('my-jobs')}
            className="flex items-center gap-2.5 group cursor-pointer border-0 bg-transparent text-left"
          >
            <div className="w-8 h-8 rounded-lg bg-[var(--app-primary)] text-white flex items-center justify-center font-bold text-sm shadow-xs">
              DB
            </div>
            <div className="hidden sm:block">
              <span className="font-bold text-sm text-[var(--app-text)] block tracking-tight leading-tight">
                Delala Bekise
              </span>
              <span className="text-[10px] text-[var(--app-text-muted)] font-medium block leading-none">
                ደላላ በኪሴ • Escrow Portal
              </span>
            </div>
          </button>

          <span className="text-[var(--app-border)] hidden md:inline">|</span>

          {/* Breadcrumb of Current Page */}
          <span className="text-xs text-[var(--app-text-muted)] hidden md:inline font-medium">
            {getBreadcrumb()}
          </span>
        </div>

        {/* Right Header Actions */}
        <div className="flex items-center gap-3">
          {/* Guest vs. Registered Mode Switcher (Demo Control) */}
          <button
            onClick={toggleGuestMode}
            className="px-3 py-1 rounded-full text-xs font-semibold border border-[var(--app-border)] bg-[var(--app-card-subtle)] hover:border-[var(--app-primary)] text-[var(--app-text)] flex items-center gap-1.5 transition-all cursor-pointer"
            title="Toggle between Guest Buyer and Registered Buyer personas"
          >
            <span
              className={`w-2 h-2 rounded-full ${
                isGuestMode ? 'bg-[var(--app-accent)]' : 'bg-[var(--app-primary)]'
              }`}
            ></span>
            <span>{isGuestMode ? 'Guest Mode (24h)' : 'Registered Buyer'}</span>
            <span className="material-symbols-outlined text-[14px] text-[var(--app-text-muted)]">
              swap_horiz
            </span>
          </button>

          {/* Notification Bell */}
          <div className="relative">
            <button
              onClick={() => setNotificationOpen(!notificationOpen)}
              className="w-8 h-8 rounded-full bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-center justify-center text-[var(--app-text)] hover:border-[var(--app-primary)] transition-colors cursor-pointer relative"
              aria-label="Platform Notifications"
            >
              <span className="material-symbols-outlined text-[18px]">notifications</span>
              {unreadNotificationCount > 0 && (
                <span className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-[var(--app-danger)] text-white text-[9px] font-bold flex items-center justify-center">
                  {unreadNotificationCount}
                </span>
              )}
            </button>
            <NotificationPopover
              isOpen={notificationOpen}
              onClose={() => setNotificationOpen(false)}
            />
          </div>

          {/* Working Light / Dark Theme Toggle */}
          <button
            onClick={toggleTheme}
            className="w-8 h-8 rounded-full bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-center justify-center text-[var(--app-text)] hover:border-[var(--app-primary)] transition-colors cursor-pointer"
            aria-label="Toggle Theme"
          >
            <span className="material-symbols-outlined text-[18px]">routine</span>
          </button>

          {/* User Profile Avatar */}
          <div className="flex items-center gap-2 pl-1 border-l border-[var(--app-border)]">
            <Link
              to="/profile"
              className="flex items-center gap-2 p-1 rounded-xl hover:bg-[var(--app-card-subtle)] transition-colors no-underline text-inherit group"
              title="View & Edit Profile"
            >
              <div className="w-8 h-8 rounded-lg bg-[var(--app-primary-light)] text-[var(--app-primary)] font-bold text-xs flex items-center justify-center border border-[var(--app-primary)]/20 group-hover:scale-105 transition-transform">
                {isGuestMode ? 'GB' : 'SH'}
              </div>
              <span className="hidden lg:inline text-xs font-semibold text-[var(--app-text)] group-hover:text-[var(--app-primary)] transition-colors">
                Profile
              </span>
            </Link>
          </div>
        </div>
      </header>

      {/* ======================================================== */}
      {/* App Body with Sidebar + Single Route Transition Container*/}
      {/* ======================================================== */}
      <div className="flex-1 flex max-w-7xl w-full mx-auto pb-16 md:pb-0">
        {/* Left Sidebar (Desktop) */}
        <aside className="w-64 shrink-0 border-r border-[var(--app-border)] p-4 hidden md:flex flex-col justify-between">
          <nav className="space-y-1.5">
            {navItems.map((item) => {
              const isActive = currentRoute === item.id;

              if (item.isCta) {
                return (
                  <button
                    key={item.id}
                    onClick={() => setRoute(item.id)}
                    className={`w-full px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer border-0 flex items-center justify-between mb-3 shadow-xs ${
                      isActive
                        ? 'bg-[var(--app-primary)] text-white'
                        : 'bg-[var(--app-primary-light)] hover:bg-[var(--app-primary)] hover:text-white text-[var(--app-primary)]'
                    }`}
                  >
                    <div className="flex items-center gap-2.5">
                      <span className="material-symbols-outlined text-[18px]">{item.icon}</span>
                      <span>{item.label}</span>
                    </div>
                    <span className="material-symbols-outlined text-[16px]">chevron_right</span>
                  </button>
                );
              }

              return (
                <button
                  key={item.id}
                  onClick={() => setRoute(item.id)}
                  className={`w-full px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all cursor-pointer border-0 flex items-center justify-between ${
                    isActive
                      ? 'bg-[var(--app-card)] text-[var(--app-primary)] shadow-xs border border-[var(--app-border)]'
                      : 'text-[var(--app-text-muted)] hover:text-[var(--app-text)] hover:bg-[var(--app-card-subtle)] bg-transparent'
                  }`}
                >
                  <div className="flex items-center gap-2.5">
                    <span
                      className={`material-symbols-outlined text-[18px] ${
                        isActive ? 'text-[var(--app-primary)]' : 'text-[var(--app-text-muted)]'
                      }`}
                    >
                      {item.icon}
                    </span>
                    <span>{item.label}</span>
                  </div>

                  {item.badgeCount && item.badgeCount > 0 ? (
                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--app-primary)] text-white">
                      {item.badgeCount}
                    </span>
                  ) : item.lockedInGuest ? (
                    <span className="material-symbols-outlined text-[14px] text-[var(--app-text-muted)]" title="Locked in Guest Mode">
                      lock
                    </span>
                  ) : null}
                </button>
              );
            })}
          </nav>

          {/* Sidebar Institutional Footer */}
          <div className="p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] text-[11px] text-[var(--app-text-muted)] space-y-1">
            <p className="font-semibold text-[var(--app-text)] flex items-center gap-1">
              <span className="material-symbols-outlined text-[14px] text-[var(--app-primary)]">verified_user</span>
              <span>NBE Sandbox Compliant</span>
            </p>
            <p className="text-[10px] leading-relaxed">
              Escrow funds secured through Chapa FinTech &amp; verified double-blind release.
            </p>
          </div>
        </aside>

        {/* ======================================================== */}
        {/* Main Route View (ONLY ONE VIEW RENDERED AT ANY TIME)    */}
        {/* ======================================================== */}
        <main className="flex-1 p-4 sm:p-8 min-w-0">
          <AnimatePresence mode="wait">
            {currentRoute === 'my-jobs' && (
              <motion.div
                key="my-jobs"
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.18, ease: 'easeOut' }}
              >
                <MyJobsPage />
              </motion.div>
            )}

            {currentRoute === 'post-job' && (
              <motion.div
                key="post-job"
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.18, ease: 'easeOut' }}
              >
                <PostJobPage />
              </motion.div>
            )}

            {currentRoute === 'messages' && (
              <motion.div
                key="messages"
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.18, ease: 'easeOut' }}
              >
                <NegotiationChatPage />
              </motion.div>
            )}

            {currentRoute === 'escrows' && (
              <motion.div
                key="escrows"
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.18, ease: 'easeOut' }}
              >
                <ActiveEscrowsPage />
              </motion.div>
            )}

            {currentRoute === 'saved' && (
              <motion.div
                key="saved"
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.18, ease: 'easeOut' }}
              >
                <SavedDelalasPage />
              </motion.div>
            )}
          </AnimatePresence>
        </main>
      </div>

      {/* ======================================================== */}
      {/* Bottom Tab Bar (Mobile Only)                             */}
      {/* ======================================================== */}
      <nav className="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-[var(--app-card)]/95 backdrop-blur-md border-t border-[var(--app-border)] h-16 flex items-center justify-around px-2">
        {navItems.map((item) => {
          const isActive = currentRoute === item.id;
          return (
            <button
              key={item.id}
              onClick={() => setRoute(item.id)}
              className={`flex flex-col items-center justify-center p-1 relative cursor-pointer border-0 bg-transparent ${
                isActive ? 'text-[var(--app-primary)]' : 'text-[var(--app-text-muted)]'
              }`}
            >
              <span className="material-symbols-outlined text-[20px]">{item.icon}</span>
              <span className="text-[10px] font-medium tracking-tight mt-0.5">{item.label.split(' ')[0]}</span>
              {item.badgeCount && item.badgeCount > 0 && (
                <span className="absolute top-0 right-1 w-3.5 h-3.5 rounded-full bg-[var(--app-primary)] text-white text-[9px] font-bold flex items-center justify-center">
                  {item.badgeCount}
                </span>
              )}
            </button>
          );
        })}
      </nav>

      {/* JIT Upgrade Modal */}
      <JitUpgradeModal isOpen={jitModalOpen} onClose={() => setJitModalOpen(false)} />
    </div>
  );
};
