import React, { useState } from 'react';
import { useAuth } from '../../context/useAuth';
import { ApiError } from '../../services/apiClient';

interface MigrateGuestModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export const MigrateGuestModal: React.FC<MigrateGuestModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
}) => {
  const { migrateGuest } = useAuth();
  const [fullName, setFullName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (password !== passwordConfirmation) {
      setErrorMessage('Passwords do not match. Please ensure both fields are identical.');
      return;
    }

    if (password.length < 8) {
      setErrorMessage('Password must be at least 8 characters.');
      return;
    }

    setIsLoading(true);
    setErrorMessage(null);

    try {
      await migrateGuest({
        full_name: fullName,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      onSuccess();
      onClose();
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.errorCode === 'EMAIL_ALREADY_REGISTERED' || err.status === 409) {
          setErrorMessage(
            'The provided email is already registered to another account. Please use a different email.'
          );
        } else {
          setErrorMessage(err.message);
        }
      } else {
        setErrorMessage('Failed to upgrade account. Please try again.');
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-xs">
      <div className="w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl p-6 sm:p-8 border border-outline-variant/30 relative animate-in fade-in zoom-in-95 duration-200">
        <div className="flex items-center justify-between pb-3 border-b border-surface-container-low mb-4">
          <div className="flex items-center gap-2">
            <span className="w-8 h-8 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
              <span className="material-symbols-outlined text-[20px]">upgrade</span>
            </span>
            <h2 className="text-lg font-bold text-on-surface font-headline">
              Upgrade to Permanent Account
            </h2>
          </div>
          <button
            onClick={onClose}
            className="text-on-surface-variant hover:text-on-surface p-1 rounded cursor-pointer border-0 bg-transparent"
          >
            <span className="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        <p className="text-xs text-on-surface-variant mb-4 leading-relaxed">
          Your current guest session expires in 24 hours. Save your active jobs, escrows, and chat histories by creating your permanent credentials.
        </p>

        {errorMessage && (
          <div className="mb-4 p-3 rounded-lg bg-error-container text-on-error-container text-xs flex items-center gap-2">
            <span className="material-symbols-outlined text-[16px] shrink-0">error</span>
            <span>{errorMessage}</span>
          </div>
        )}

        <form className="space-y-3.5" onSubmit={handleSubmit}>
          <div>
            <label className="block text-xs font-semibold text-on-surface mb-1" htmlFor="migrate-name">
              Full Legal Name <span className="text-error">*</span>
            </label>
            <input
              id="migrate-name"
              type="text"
              value={fullName}
              onChange={(e) => setFullName(e.target.value)}
              placeholder="e.g. Abebe Bikila"
              required
              disabled={isLoading}
              className="w-full h-10 px-3.5 bg-surface-container-low text-on-surface text-xs rounded-lg border border-outline-variant/30 focus:outline-none focus:bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 transition-all"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-on-surface mb-1" htmlFor="migrate-email">
              Email Address <span className="text-error">*</span>
            </label>
            <input
              id="migrate-email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="buyer@delala.et"
              required
              disabled={isLoading}
              className="w-full h-10 px-3.5 bg-surface-container-low text-on-surface text-xs rounded-lg border border-outline-variant/30 focus:outline-none focus:bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 transition-all"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-on-surface mb-1" htmlFor="migrate-password">
              Password <span className="text-error">*</span>
            </label>
            <input
              id="migrate-password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Minimum 8 characters"
              required
              disabled={isLoading}
              className="w-full h-10 px-3.5 bg-surface-container-low text-on-surface text-xs rounded-lg border border-outline-variant/30 focus:outline-none focus:bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 transition-all"
            />
          </div>

          <div>
            <label
              className="block text-xs font-semibold text-on-surface mb-1"
              htmlFor="migrate-confirm-password"
            >
              Confirm Password <span className="text-error">*</span>
            </label>
            <input
              id="migrate-confirm-password"
              type="password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              placeholder="Re-enter password"
              required
              disabled={isLoading}
              className="w-full h-10 px-3.5 bg-surface-container-low text-on-surface text-xs rounded-lg border border-outline-variant/30 focus:outline-none focus:bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 transition-all"
            />
          </div>

          <div className="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={onClose}
              disabled={isLoading}
              className="px-4 py-2 text-xs font-semibold text-on-surface-variant hover:bg-surface-container rounded-lg cursor-pointer border-0 bg-transparent"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isLoading}
              className="px-5 py-2.5 text-xs font-semibold text-on-primary bg-primary hover:bg-primary-container rounded-lg shadow-sm transition-all cursor-pointer border-0"
            >
              {isLoading ? 'Saving...' : 'Upgrade Account'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
