import React from 'react';
import { useBuyer } from '../../context/useBuyer';

interface NotificationPopoverProps {
  isOpen: boolean;
  onClose: () => void;
}

export const NotificationPopover: React.FC<NotificationPopoverProps> = ({ isOpen, onClose }) => {
  const { notifications, markNotificationsAsRead, setRoute } = useBuyer();

  if (!isOpen) return null;

  return (
    <>
      <div className="fixed inset-0 z-40" onClick={onClose}></div>
      <div className="absolute right-0 top-12 z-50 w-80 sm:w-96 bg-[var(--app-card)] text-[var(--app-text)] rounded-2xl shadow-xl border border-[var(--app-border)] p-4 animate-in fade-in zoom-in-95 duration-150">
        <div className="flex items-center justify-between pb-3 border-b border-[var(--app-border)] mb-3">
          <div className="flex items-center gap-2">
            <span className="material-symbols-outlined text-[18px] text-[var(--app-primary)]">notifications</span>
            <span className="text-xs font-semibold text-[var(--app-text)]">Platform alerts</span>
          </div>
          <button
            onClick={markNotificationsAsRead}
            className="text-[11px] font-medium text-[var(--app-primary)] hover:underline cursor-pointer border-0 bg-transparent"
          >
            Mark all read
          </button>
        </div>

        <div className="space-y-2 max-h-72 overflow-y-auto">
          {notifications.map((n) => (
            <button
              key={n.id}
              onClick={() => {
                if (n.action_route) setRoute(n.action_route);
                onClose();
              }}
              className={`w-full p-2.5 rounded-xl text-left transition-colors cursor-pointer border-0 flex items-start gap-2.5 ${
                n.read ? 'bg-transparent hover:bg-[var(--app-card-subtle)]' : 'bg-[var(--app-primary-light)]/50 hover:bg-[var(--app-primary-light)]'
              }`}
            >
              <div
                className={`w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5 ${
                  n.type === 'escrow'
                    ? 'bg-[var(--app-primary-light)] text-[var(--app-primary)]'
                    : n.type === 'proposal'
                    ? 'bg-[var(--app-accent-light)] text-[var(--app-accent)]'
                    : 'bg-[var(--app-card-subtle)] text-[var(--app-text-muted)]'
                }`}
              >
                <span className="material-symbols-outlined text-[15px]">
                  {n.type === 'escrow' ? 'lock' : n.type === 'proposal' ? 'real_estate_agent' : 'security'}
                </span>
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center justify-between">
                  <h4 className="text-xs font-bold text-[var(--app-text)] truncate">{n.title}</h4>
                  <span className="text-[10px] text-[var(--app-text-muted)] shrink-0 ml-1.5">{n.created_at}</span>
                </div>
                <p className="text-[11px] text-[var(--app-text-muted)] mt-0.5 line-clamp-2 leading-relaxed">
                  {n.description}
                </p>
              </div>
            </button>
          ))}
        </div>
      </div>
    </>
  );
};
