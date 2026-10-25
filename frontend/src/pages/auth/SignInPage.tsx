import React, { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AuthLayout } from '../../components/auth/AuthLayout';
import { useAuth } from '../../context/useAuth';
import { useAuthFlow } from '../../context/useAuthFlow';
import { ApiError } from '../../services/apiClient';

export const SignInPage: React.FC = () => {
  const navigate = useNavigate();
  const { login } = useAuth();
  const { flowData, updateFlowData } = useAuthFlow();

  const [email, setEmail] = useState(flowData.email || '');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // 423 Lockout State
  const [lockedUntil, setLockedUntil] = useState<Date | null>(null);
  const [lockoutSecondsRemaining, setLockoutSecondsRemaining] = useState<number>(0);

  useEffect(() => {
    if (!lockedUntil) return;

    const updateTimer = () => {
      const diffMs = lockedUntil.getTime() - Date.now();
      const diffSec = Math.max(0, Math.ceil(diffMs / 1000));
      setLockoutSecondsRemaining(diffSec);

      if (diffSec <= 0) {
        setLockedUntil(null);
        setErrorMessage(null);
      }
    };

    updateTimer();
    const interval = setInterval(updateTimer, 1000);
    return () => clearInterval(interval);
  }, [lockedUntil]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (lockedUntil) return;

    setIsLoading(true);
    setErrorMessage(null);

    try {
      const res = await login({ email, password });
      const userRole = res.user?.type === 'delala' ? 'delala' : res.user?.type === 'guest' ? 'guest' : 'buyer';
      updateFlowData({ email, role: userRole });
      if (res.user?.type === 'delala') {
        navigate('/delala/feed');
      } else {
        navigate('/buyer');
      }
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.status === 423 && err.lockedUntil) {
          const lockDate = new Date(err.lockedUntil);
          setLockedUntil(lockDate);
          setErrorMessage(
            err.message ||
              'Account is temporarily locked due to 5 consecutive failed login attempts.'
          );
        } else {
          setErrorMessage(err.message);
        }
      } else {
        setErrorMessage('The provided credentials do not match our records.');
      }
    } finally {
      setIsLoading(false);
    }
  };

  const formatLockoutTime = (totalSec: number) => {
    const mins = Math.floor(totalSec / 60);
    const secs = totalSec % 60;
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
  };

  const isLocked = lockedUntil !== null && lockoutSecondsRemaining > 0;

  return (
    <AuthLayout>
      <div className="relative w-full py-10 px-4 sm:px-6 flex flex-col items-center justify-center overflow-hidden">
        {/* Ambient lighting accents */}
        <div className="absolute -top-32 left-1/2 -translate-x-1/2 w-96 h-96 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
        <div className="absolute -bottom-24 right-1/4 w-80 h-80 bg-secondary-container/5 rounded-full blur-3xl pointer-events-none"></div>

        <div className="w-full max-w-lg z-10 flex flex-col gap-6">
          <div className="flex items-center justify-start">
            <Link
              to="/auth/choose-role"
              className="inline-flex items-center gap-1.5 text-on-surface-variant hover:text-primary transition-colors text-xs font-semibold group no-underline"
            >
              <span className="material-symbols-outlined text-[16px] group-hover:-translate-x-0.5 transition-transform">
                arrow_back
              </span>
              <span>Back to role selection</span>
            </Link>
          </div>

          <div className="w-full bg-surface-container-lowest rounded-xl shadow-xl p-6 sm:p-10 flex flex-col gap-6 relative">
            <div className="flex flex-col items-start gap-1">
              <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-container text-primary text-xs font-semibold tracking-wide">
                <span className="inline-block w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                <span>SECURE ESCROW ACCESS</span>
              </div>
              <h1 className="font-headline text-2xl sm:text-3xl font-bold text-on-surface tracking-tight mt-1">
                Sign in to your account
              </h1>
              <p className="text-sm text-on-surface-variant">
                Welcome back! Enter your registered email to access Delala Bekise.
              </p>
            </div>

            {/* Brute-force Lockout Alert (HTTP 423) */}
            {isLocked && (
              <div className="p-4 rounded-xl bg-error-container text-on-error-container text-xs space-y-1.5">
                <div className="flex items-center gap-2 font-bold text-sm">
                  <span className="material-symbols-outlined text-[20px]">lock_clock</span>
                  <span>Account Temporarily Locked</span>
                </div>
                <p className="leading-relaxed">
                  Too many consecutive failed login attempts. Submit is locked for your security.
                </p>
                <div className="pt-1 font-mono font-bold text-sm text-error">
                  Try again in: {formatLockoutTime(lockoutSecondsRemaining)}
                </div>
              </div>
            )}

            {errorMessage && !isLocked && (
              <div className="p-3 rounded-lg bg-error-container text-on-error-container text-xs flex items-center gap-2">
                <span className="material-symbols-outlined text-[16px] shrink-0">error</span>
                <span>{errorMessage}</span>
              </div>
            )}

            <form className="flex flex-col gap-4" onSubmit={handleSubmit}>
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-on-surface" htmlFor="login-email">
                  Email Address <span className="text-error">*</span>
                </label>
                <div className="relative flex items-center">
                  <span className="material-symbols-outlined absolute left-3.5 text-outline text-[20px] pointer-events-none">
                    alternate_email
                  </span>
                  <input
                    id="login-email"
                    type="email"
                    value={email}
                    onChange={(e) => {
                      setEmail(e.target.value);
                      updateFlowData({ email: e.target.value });
                    }}
                    placeholder="e.g. user@example.com"
                    required
                    disabled={isLoading || isLocked}
                    className="w-full h-11 pl-11 pr-4 bg-surface-container-low text-on-surface placeholder:text-outline/70 text-sm rounded-lg focus:outline-none focus:bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 transition-all"
                  />
                </div>
              </div>

              <div className="flex flex-col gap-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold text-on-surface" htmlFor="login-password">
                    Password <span className="text-error">*</span>
                  </label>
                  <Link
                    to="/auth/forgot-password"
                    className="text-xs font-semibold text-primary hover:underline transition-all"
                  >
                    Forgot password?
                  </Link>
                </div>
                <div className="relative flex items-center">
                  <span className="material-symbols-outlined absolute left-3.5 text-outline text-[20px] pointer-events-none">
                    lock
                  </span>
                  <input
                    id="login-password"
                    type={showPassword ? 'text' : 'password'}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Enter your password"
                    required
                    disabled={isLoading || isLocked}
                    className="w-full h-11 pl-11 pr-11 bg-surface-container-low text-on-surface placeholder:text-outline/70 text-sm rounded-lg focus:outline-none focus:bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 transition-all"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    aria-label="Toggle password visibility"
                    className="absolute right-3 text-outline hover:text-on-surface transition-colors p-1 flex items-center justify-center rounded cursor-pointer border-0 bg-transparent"
                  >
                    <span className="material-symbols-outlined text-[19px]">
                      {showPassword ? 'visibility_off' : 'visibility'}
                    </span>
                  </button>
                </div>
              </div>

              <button
                type="submit"
                disabled={isLoading || isLocked}
                className="w-full h-11 mt-2 bg-primary hover:bg-primary-container text-on-primary text-sm font-semibold rounded-lg shadow-sm flex items-center justify-center gap-2 transition-all active:scale-[0.99] disabled:opacity-50 cursor-pointer border-0"
              >
                {isLoading ? (
                  <>
                    <span className="material-symbols-outlined text-[18px] animate-spin">
                      progress_activity
                    </span>
                    <span>Signing In...</span>
                  </>
                ) : isLocked ? (
                  <span>Locked ({formatLockoutTime(lockoutSecondsRemaining)})</span>
                ) : (
                  <>
                    <span>Sign In</span>
                    <span className="material-symbols-outlined text-[18px]">arrow_forward</span>
                  </>
                )}
              </button>
            </form>

            {/* Divider */}
            <div className="relative flex items-center justify-center my-1">
              <div className="w-full h-px bg-surface-container"></div>
              <span className="absolute px-3 bg-surface-container-lowest text-xs text-outline font-medium">
                or log in with
              </span>
            </div>

            {/* Alternative Login: SMS OTP */}
            <div className="flex flex-col gap-2">
              <Link
                to="/auth/guest"
                className="w-full h-11 bg-surface-container-low hover:bg-surface-container text-on-surface text-sm font-semibold rounded-lg flex items-center justify-center gap-2 transition-colors no-underline"
              >
                <span className="material-symbols-outlined text-primary text-[20px]">sms</span>
                <span>Log in with SMS Code (OTP)</span>
              </Link>
            </div>

            {/* Footer inside Card */}
            <div className="pt-4 border-t border-transparent bg-surface-container-low -mx-6 sm:-mx-10 -mb-6 sm:-mb-10 p-4 sm:px-10 text-center rounded-b-xl flex items-center justify-center gap-1.5 text-xs text-on-surface-variant">
              <span>Don't have an account?</span>
              <Link to="/auth/choose-role" className="font-semibold text-primary hover:underline">
                Sign up
              </Link>
            </div>
          </div>

          <div className="flex flex-wrap items-center justify-center gap-y-2 gap-x-6 text-outline text-xs">
            <div className="flex items-center gap-1.5">
              <span className="material-symbols-outlined text-[18px] text-primary">verified_user</span>
              <span>256-bit Bank Grade Security</span>
            </div>
            <span>•</span>
            <div className="flex items-center gap-1.5">
              <span className="material-symbols-outlined text-[18px] text-secondary">
                shield_with_heart
              </span>
              <span>Instant Escrow Safeguard</span>
            </div>
          </div>
        </div>
      </div>
    </AuthLayout>
  );
};
