import React, { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { AuthLayout } from '../../components/auth/AuthLayout';
import { authService } from '../../services/authService';
import { useAuthFlow } from '../../context/useAuthFlow';
import { ApiError } from '../../services/apiClient';

export const ResetPasswordPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { flowData } = useAuthFlow();

  const urlToken = searchParams.get('token') || '';
  const urlEmail = searchParams.get('email') || flowData.email || '';

  const [resetToken, setResetToken] = useState(urlToken);
  const [email, setEmail] = useState(urlEmail);
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

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
      const res = await authService.resetPassword({
        token: resetToken,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      setSuccessMessage(res.message || 'Your password has been reset successfully.');
      setTimeout(() => {
        navigate('/auth/login');
      }, 1500);
    } catch (err) {
      if (err instanceof ApiError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Failed to reset password. The link or token may be invalid or expired.');
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <AuthLayout>
      <div className="relative w-full py-10 px-4 sm:px-6 flex flex-col items-center justify-center overflow-hidden">
        {/* Ambient lighting accents */}
        <div className="absolute -top-32 left-1/2 -translate-x-1/2 w-96 h-96 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
        <div className="absolute -bottom-24 right-1/4 w-80 h-80 bg-secondary-container/5 rounded-full blur-3xl pointer-events-none"></div>

        <div className="w-full max-w-lg z-10 flex flex-col gap-6">
          <div className="flex items-center justify-start">
            <Link
              to="/auth/login"
              className="inline-flex items-center gap-1.5 text-on-surface-variant hover:text-primary transition-colors text-xs font-semibold group no-underline"
            >
              <span className="material-symbols-outlined text-[16px] group-hover:-translate-x-0.5 transition-transform">
                arrow_back
              </span>
              <span>Back to sign in</span>
            </Link>
          </div>

          <div className="w-full bg-surface-container-lowest rounded-xl shadow-xl p-6 sm:p-10 flex flex-col gap-6 relative">
            <div className="flex flex-col items-start gap-1">
              <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-container text-primary text-xs font-semibold tracking-wide">
                <span className="material-symbols-outlined text-[14px]">password</span>
                <span>SECURE CREDENTIAL RESET</span>
              </div>
              <h1 className="font-headline text-2xl sm:text-3xl font-bold text-on-surface tracking-tight mt-1">
                Set new password
              </h1>
              <p className="text-sm text-on-surface-variant">
                Create a new secure password for your Delala account.
              </p>
            </div>

            {successMessage && (
              <div className="p-3.5 rounded-lg bg-primary-fixed text-on-primary-fixed-variant text-xs flex items-center gap-2">
                <span className="material-symbols-outlined text-[18px] shrink-0">check_circle</span>
                <span className="font-semibold">{successMessage}</span>
              </div>
            )}

            {errorMessage && (
              <div className="p-3 rounded-lg bg-error-container text-on-error-container text-xs flex items-center gap-2">
                <span className="material-symbols-outlined text-[16px] shrink-0">error</span>
                <span>{errorMessage}</span>
              </div>
            )}

            <form className="flex flex-col gap-4" onSubmit={handleSubmit}>
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-on-surface" htmlFor="reset-email">
                  Email Address <span className="text-error">*</span>
                </label>
                <input
                  id="reset-email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="user@delala.et"
                  required
                  disabled={isLoading}
                  className="w-full h-11 px-3.5 bg-surface-container-low text-on-surface text-sm rounded-lg focus:outline-none focus:bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 transition-all"
                />
              </div>

              {!urlToken && (
                <div className="flex flex-col gap-1.5">
                  <label className="text-xs font-semibold text-on-surface" htmlFor="reset-token">
                    Reset Token <span className="text-error">*</span>
                  </label>
                  <input
                    id="reset-token"
                    type="text"
                    value={resetToken}
                    onChange={(e) => setResetToken(e.target.value)}
                    placeholder="Paste token from email link"
                    required
                    disabled={isLoading}
                    className="w-full h-11 px-3.5 bg-surface-container-low text-on-surface text-sm font-mono rounded-lg focus:outline-none focus:bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 transition-all"
                  />
                </div>
              )}

              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-on-surface" htmlFor="new-password">
                  New Password <span className="text-error">*</span>
                </label>
                <div className="relative flex items-center">
                  <input
                    id="new-password"
                    type={showPassword ? 'text' : 'password'}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Minimum 8 characters"
                    required
                    disabled={isLoading}
                    className="w-full h-11 pl-4 pr-11 bg-surface-container-low text-on-surface text-sm rounded-lg focus:outline-none focus:bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 transition-all"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    aria-label="Toggle password visibility"
                    className="absolute right-3 text-outline hover:text-on-surface p-1 rounded cursor-pointer border-0 bg-transparent"
                  >
                    <span className="material-symbols-outlined text-[19px]">
                      {showPassword ? 'visibility_off' : 'visibility'}
                    </span>
                  </button>
                </div>
              </div>

              <div className="flex flex-col gap-1.5">
                <label
                  className="text-xs font-semibold text-on-surface"
                  htmlFor="confirm-new-password"
                >
                  Confirm New Password <span className="text-error">*</span>
                </label>
                <input
                  id="confirm-new-password"
                  type={showPassword ? 'text' : 'password'}
                  value={passwordConfirmation}
                  onChange={(e) => setPasswordConfirmation(e.target.value)}
                  placeholder="Re-enter password"
                  required
                  disabled={isLoading}
                  className="w-full h-11 px-4 bg-surface-container-low text-on-surface text-sm rounded-lg focus:outline-none focus:bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 transition-all"
                />
              </div>

              <button
                type="submit"
                disabled={isLoading}
                className="w-full h-11 mt-2 bg-primary hover:bg-primary-container text-on-primary text-sm font-semibold rounded-lg shadow-sm flex items-center justify-center gap-2 transition-all active:scale-[0.99] disabled:opacity-50 cursor-pointer border-0"
              >
                {isLoading ? (
                  <>
                    <span className="material-symbols-outlined text-[18px] animate-spin">
                      progress_activity
                    </span>
                    <span>Updating Password...</span>
                  </>
                ) : (
                  <>
                    <span>Save New Password</span>
                    <span className="material-symbols-outlined text-[18px]">check</span>
                  </>
                )}
              </button>
            </form>

            <div className="pt-4 border-t border-transparent bg-surface-container-low -mx-6 sm:-mx-10 -mb-6 sm:-mb-10 p-4 sm:px-10 text-center rounded-b-xl flex items-center justify-center gap-1.5 text-xs text-on-surface-variant">
              <span>Back to</span>
              <Link to="/auth/login" className="font-semibold text-primary hover:underline">
                Sign in
              </Link>
            </div>
          </div>

          <div className="flex flex-wrap items-center justify-center gap-y-2 gap-x-6 text-outline text-xs">
            <div className="flex items-center gap-1.5">
              <span className="material-symbols-outlined text-[18px] text-primary">verified_user</span>
              <span>Cryptographic Salted Hash</span>
            </div>
            <span>•</span>
            <div className="flex items-center gap-1.5">
              <span className="material-symbols-outlined text-[18px] text-secondary">shield</span>
              <span>Instant Session Termination</span>
            </div>
          </div>
        </div>
      </div>
    </AuthLayout>
  );
};
