import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { AuthLayout } from '../../components/auth/AuthLayout';
import { useAuthFlow } from '../../context/useAuthFlow';

export const VerifyEmailPage: React.FC = () => {
  const { flowData } = useAuthFlow();
  const email = flowData.email || 'abebe.b@example.com';
  const phone = flowData.phone;

  const [secondsLeft, setSecondsLeft] = useState(60);
  const [isResent, setIsResent] = useState(false);

  useEffect(() => {
    if (secondsLeft <= 0) return;
    const interval = setInterval(() => {
      setSecondsLeft((prev) => prev - 1);
    }, 1000);
    return () => clearInterval(interval);
  }, [secondsLeft]);

  const handleResend = () => {
    if (secondsLeft > 0) return;
    setIsResent(true);
    setSecondsLeft(60);
  };

  return (
    <AuthLayout>
      <div className="relative w-full min-h-[calc(100vh-8rem)] flex items-center justify-center px-4 py-8 overflow-hidden">
        {/* Ambient subtle illumination spheres */}
        <div className="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-primary-fixed/30 blur-3xl pointer-events-none"></div>
        <div className="absolute -bottom-32 -right-32 w-96 h-96 rounded-full bg-secondary-fixed/40 blur-3xl pointer-events-none"></div>

        {/* Centered Verification Sheet */}
        <div className="relative w-full max-w-md mx-auto flex flex-col items-center text-center">
          {/* Institutional Step Indicator pill */}
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container mb-6 shadow-xs">
            <span className="w-1.5 h-1.5 rounded-full bg-primary-container animate-pulse"></span>
            <span className="text-xs uppercase tracking-wider text-on-surface-variant font-semibold">
              Institutional Onboarding • Step 2 of 2
            </span>
          </div>

          {/* Hero Minimal Verification Icon Art */}
          <div className="relative mb-6 flex items-center justify-center">
            <div className="absolute inset-0 bg-primary/10 rounded-full blur-xl scale-125"></div>
            <div className="relative w-24 h-24 rounded-full bg-surface-container-lowest shadow-xl flex items-center justify-center">
              <svg className="absolute inset-0 w-full h-full text-primary-fixed-dim" fill="none" viewBox="0 0 96 96">
                <circle cx="48" cy="48" opacity="0.6" r="44" stroke="currentColor" strokeDasharray="3 4" strokeWidth="1.5"></circle>
                <circle cx="48" cy="48" opacity="0.3" r="38" stroke="currentColor" strokeWidth="1"></circle>
              </svg>
              <div className="w-16 h-16 rounded-full bg-surface-container flex items-center justify-center text-primary shadow-xs">
                <span className="material-symbols-outlined text-[32px]">mark_email_unread</span>
              </div>
              <div className="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-primary text-on-primary flex items-center justify-center shadow-md">
                <span className="material-symbols-outlined text-[16px] font-bold">verified</span>
              </div>
            </div>
          </div>

          <h1 className="font-headline text-3xl font-bold text-on-surface tracking-tight mb-2">
            Check your email
          </h1>
          <p className="text-sm text-on-surface-variant max-w-sm mb-6 leading-relaxed">
            We sent a confirmation link to{' '}
            <span className="font-financial-numeric font-semibold text-on-surface px-1.5 py-0.5 rounded bg-surface-container-high">
              {email}
            </span>. Click the link in the message to activate your account.
          </p>

          {/* Prompt if Phone OTP is also pending */}
          {phone && (
            <div className="w-full mb-6 p-4 rounded-xl bg-surface-container-low border border-outline-variant/30 text-left flex items-center justify-between">
              <div>
                <div className="text-xs font-bold text-on-surface">Phone SMS Verification</div>
                <div className="text-xs text-on-surface-variant mt-0.5">
                  Code sent to <span className="font-semibold">{phone}</span>
                </div>
              </div>
              <Link
                to="/auth/verify-otp"
                className="px-3 py-1.5 rounded-lg bg-primary text-on-primary text-xs font-semibold hover:bg-primary-container transition no-underline"
              >
                Enter OTP
              </Link>
            </div>
          )}

          {/* Primary & Secondary Interactive CTAs */}
          <div className="w-full flex flex-col gap-2 mb-6">
            <a
              href={`mailto:${email}`}
              className="w-full h-12 rounded-lg bg-primary hover:bg-primary-container text-on-primary font-semibold text-sm flex items-center justify-center gap-2 shadow-md transition-all active:scale-[0.99] group no-underline"
            >
              <span>Open Email Inbox</span>
              <span className="material-symbols-outlined text-[18px] group-hover:translate-x-0.5 transition-transform">
                open_in_new
              </span>
            </a>

            <button
              type="button"
              disabled={secondsLeft > 0}
              onClick={handleResend}
              className="w-full h-11 rounded-lg bg-surface-container hover:bg-surface-container-high disabled:opacity-60 disabled:cursor-not-allowed text-xs font-semibold flex items-center justify-center gap-2 transition-colors text-primary cursor-pointer border-0"
            >
              <span className="material-symbols-outlined text-[18px]">sync</span>
              <span>
                {secondsLeft > 0
                  ? `Resend verification link (${secondsLeft}s)`
                  : isResent
                  ? 'Link resent! Click to resend again'
                  : 'Resend verification link'}
              </span>
            </button>
          </div>

          {/* Footnote Advisory Text */}
          <div className="text-center text-xs text-on-surface-variant mb-6">
            Did not receive it? Check your spam folder or wait for the cooldown timer.
          </div>

          {/* Alternate Switcher Link */}
          <div className="pt-2 flex items-center justify-center">
            <Link
              to="/auth/login"
              className="inline-flex items-center gap-1.5 text-xs font-medium text-on-surface-variant hover:text-primary transition-colors py-1.5 px-3 rounded-full hover:bg-surface-container no-underline"
            >
              <span className="material-symbols-outlined text-[16px]">switch_account</span>
              <span>Log in to another account</span>
            </Link>
          </div>
        </div>
      </div>
    </AuthLayout>
  );
};
