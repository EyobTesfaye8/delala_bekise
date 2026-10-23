import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AuthLayout } from '../../components/auth/AuthLayout';
import { authService } from '../../services/authService';
import { useAuthFlow } from '../../context/useAuthFlow';
import { ApiError } from '../../services/apiClient';

export const ForgotPasswordPage: React.FC = () => {
  const navigate = useNavigate();
  const { flowData, updateFlowData } = useAuthFlow();

  const [email, setEmail] = useState(flowData.email || '');
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setErrorMessage(null);
    setSuccessMessage(null);

    try {
      const res = await authService.forgotPassword({ email });
      updateFlowData({ email });
      setSuccessMessage(res.message || 'We have emailed your password reset link.');
      setTimeout(() => {
        navigate('/auth/reset-password');
      }, 2000);
    } catch (err) {
      if (err instanceof ApiError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Failed to send reset link. Please check the email address.');
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
                <span className="material-symbols-outlined text-[14px]">lock_reset</span>
                <span>PASSWORD RECOVERY</span>
              </div>
              <h1 className="font-headline text-2xl sm:text-3xl font-bold text-on-surface tracking-tight mt-1">
                Forgot your password?
              </h1>
              <p className="text-sm text-on-surface-variant">
                Enter your registered email address and we'll send you an encrypted password reset link.
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
                <label className="text-xs font-semibold text-on-surface" htmlFor="forgot-email">
                  Registered Email Address <span className="text-error">*</span>
                </label>
                <div className="relative flex items-center">
                  <span className="material-symbols-outlined absolute left-3.5 text-outline text-[20px] pointer-events-none">
                    mail
                  </span>
                  <input
                    id="forgot-email"
                    type="email"
                    value={email}
                    onChange={(e) => {
                      setEmail(e.target.value);
                      updateFlowData({ email: e.target.value });
                    }}
                    placeholder="e.g. user@delala.et"
                    required
                    disabled={isLoading}
                    className="w-full h-11 pl-11 pr-4 bg-surface-container-low text-on-surface placeholder:text-outline/70 text-sm rounded-lg focus:outline-none focus:bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 transition-all"
                  />
                </div>
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
                    <span>Sending Reset Link...</span>
                  </>
                ) : (
                  <>
                    <span>Send Reset Link</span>
                    <span className="material-symbols-outlined text-[18px]">arrow_forward</span>
                  </>
                )}
              </button>
            </form>

            <div className="pt-4 border-t border-transparent bg-surface-container-low -mx-6 sm:-mx-10 -mb-6 sm:-mb-10 p-4 sm:px-10 text-center rounded-b-xl flex items-center justify-center gap-1.5 text-xs text-on-surface-variant">
              <span>Remembered your password?</span>
              <Link to="/auth/login" className="font-semibold text-primary hover:underline">
                Sign in
              </Link>
            </div>
          </div>

          <div className="flex flex-wrap items-center justify-center gap-y-2 gap-x-6 text-outline text-xs">
            <div className="flex items-center gap-1.5">
              <span className="material-symbols-outlined text-[18px] text-primary">verified_user</span>
              <span>256-bit Encrypted Token</span>
            </div>
            <span>•</span>
            <div className="flex items-center gap-1.5">
              <span className="material-symbols-outlined text-[18px] text-secondary">lock</span>
              <span>Single-Use Expiration</span>
            </div>
          </div>
        </div>
      </div>
    </AuthLayout>
  );
};
