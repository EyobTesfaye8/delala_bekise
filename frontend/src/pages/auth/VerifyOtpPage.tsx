import React, { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { AuthLayout } from '../../components/auth/AuthLayout';
import { useAuth } from '../../context/useAuth';
import { useAuthFlow } from '../../context/useAuthFlow';
import { ApiError } from '../../services/apiClient';

export const VerifyOtpPage: React.FC = () => {
  const navigate = useNavigate();
  const { verifyOtp, sendOtp } = useAuth();
  const { flowData } = useAuthFlow();

  const phone = flowData.phone || '+251911000000';
  const [digits, setDigits] = useState<string[]>(['', '', '', '', '', '']);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [timeLeft, setTimeLeft] = useState(48);
  const inputsRef = useRef<(HTMLInputElement | null)[]>([]);

  useEffect(() => {
    inputsRef.current[0]?.focus();
  }, []);

  useEffect(() => {
    if (timeLeft <= 0) return;
    const interval = setInterval(() => {
      setTimeLeft((prev) => prev - 1);
    }, 1000);
    return () => clearInterval(interval);
  }, [timeLeft]);

  const handleChange = (index: number, value: string) => {
    const clean = value.replace(/[^0-9]/g, '');
    if (!clean) {
      const newDigits = [...digits];
      newDigits[index] = '';
      setDigits(newDigits);
      return;
    }

    const newDigits = [...digits];
    newDigits[index] = clean[0];
    setDigits(newDigits);

    if (errorMessage) setErrorMessage(null);

    if (index < 5) {
      inputsRef.current[index + 1]?.focus();
    } else {
      const fullCode = newDigits.join('');
      if (fullCode.length === 6) {
        submitOtp(fullCode);
      }
    }
  };

  const handleKeyDown = (index: number, e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Backspace' && !digits[index] && index > 0) {
      inputsRef.current[index - 1]?.focus();
    } else if (e.key === 'ArrowLeft' && index > 0) {
      inputsRef.current[index - 1]?.focus();
    } else if (e.key === 'ArrowRight' && index < 5) {
      inputsRef.current[index + 1]?.focus();
    }
  };

  const handlePaste = (e: React.ClipboardEvent) => {
    e.preventDefault();
    const pasted = e.clipboardData.getData('text').replace(/[^0-9]/g, '');
    if (!pasted) return;

    const chars = pasted.slice(0, 6).split('');
    const newDigits = [...digits];
    chars.forEach((char, i) => {
      newDigits[i] = char;
    });
    setDigits(newDigits);

    const nextIndex = Math.min(chars.length, 5);
    inputsRef.current[nextIndex]?.focus();

    if (chars.length === 6) {
      submitOtp(chars.join(''));
    }
  };

  const submitOtp = async (codeToSubmit: string) => {
    setIsLoading(true);
    setErrorMessage(null);
    try {
      const authRes = await verifyOtp({ phone, code: codeToSubmit });

      // Determine destination based on role
      const role = flowData.role || authRes.user?.type;
      const isDev = import.meta.env.DEV;

      // In production mode, if user registered with email, proceed to verify email page
      if (!isDev && (role === 'buyer' || role === 'delala')) {
        navigate('/auth/verify-email');
        return;
      }

      // In development mode (or for one-time buyers):
      // Delalas redirect to Feed Page; both kinds of Buyers redirect to Posted Deals (/buyer)
      if (role === 'delala') {
        navigate('/delala/feed');
      } else {
        navigate('/buyer');
      }
    } catch (err) {
      if (err instanceof ApiError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('The provided verification code is invalid or expired.');
      }
    } finally {
      setIsLoading(false);
    }
  };

  const handleResend = async () => {
    if (timeLeft > 0 || isLoading) return;
    setIsLoading(true);
    setErrorMessage(null);
    try {
      await sendOtp({ phone });
      setTimeLeft(48);
      setDigits(['', '', '', '', '', '']);
      inputsRef.current[0]?.focus();
    } catch (err) {
      if (err instanceof ApiError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Failed to resend code. Please try again.');
      }
    } finally {
      setIsLoading(false);
    }
  };

  const handleBack = () => {
    if (flowData.role === 'buyer') {
      navigate('/auth/register/buyer');
    } else if (flowData.role === 'delala') {
      navigate('/auth/register/delala');
    } else {
      navigate('/auth/guest');
    }
  };

  const isComplete = digits.every((d) => d.length === 1);

  return (
    <AuthLayout>
      <div className="relative w-full min-h-[calc(100vh-8rem)] flex items-center justify-center px-6 py-12">
        <div className="absolute -top-16 -left-16 w-96 h-96 rounded-full bg-primary-fixed-dim/20 blur-3xl pointer-events-none"></div>
        <div className="absolute bottom-10 right-4 w-80 h-80 rounded-full bg-secondary-fixed/30 blur-3xl pointer-events-none"></div>

        <div className="relative w-full max-w-lg mx-auto flex flex-col">
          <div className="mb-8">
            <button
              onClick={handleBack}
              className="inline-flex items-center gap-2 text-on-surface-variant hover:text-primary transition-colors duration-200 group text-sm font-semibold cursor-pointer border-0 bg-transparent"
            >
              <span className="material-symbols-outlined text-[18px] transition-transform duration-200 group-hover:-translate-x-1">
                arrow_back
              </span>
              <span>Change phone number</span>
            </button>
          </div>

          <div className="bg-surface-container-lowest rounded-xl shadow-xl shadow-surface-variant/40 p-8 sm:p-10 flex flex-col">
            <div className="flex items-center justify-between mb-6">
              <div className="w-12 h-12 rounded-xl bg-surface-container flex items-center justify-center text-primary">
                <span className="material-symbols-outlined text-[26px]">phonelink_lock</span>
              </div>
              <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-low text-primary">
                <span className="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                <span className="text-xs font-medium">SMS verification</span>
              </div>
            </div>

            <div className="flex flex-col gap-2 mb-8">
              <h1 className="font-headline text-2xl sm:text-3xl font-bold text-on-surface tracking-tight">
                Enter verification code
              </h1>
              <p className="text-sm text-on-surface-variant leading-relaxed">
                We sent a 6-digit code to{' '}
                <span className="text-on-surface font-semibold tracking-normal font-financial-numeric">
                  {phone}
                </span>.
              </p>
            </div>

            {errorMessage && (
              <div className="mb-6 p-3 rounded-lg bg-error-container text-on-error-container text-xs flex items-center gap-2">
                <span className="material-symbols-outlined text-[16px] shrink-0">error</span>
                <span>{errorMessage}</span>
              </div>
            )}

            <form
              className="flex flex-col gap-8"
              onSubmit={(e) => {
                e.preventDefault();
                if (isComplete) submitOtp(digits.join(''));
              }}
            >
              <div className="flex items-center justify-between gap-2.5 sm:gap-3.5" onPaste={handlePaste}>
                {digits.map((digit, i) => (
                  <React.Fragment key={i}>
                    <input
                      ref={(el) => {
                        inputsRef.current[i] = el;
                      }}
                      type="text"
                      inputMode="numeric"
                      maxLength={1}
                      value={digit}
                      onChange={(e) => handleChange(i, e.target.value)}
                      onKeyDown={(e) => handleKeyDown(i, e)}
                      disabled={isLoading}
                      className="w-11 h-14 sm:w-14 sm:h-16 text-center text-xl font-bold text-on-surface bg-surface-container-low focus:bg-surface-container-lowest rounded-lg outline-none transition-all duration-200 shadow-xs focus:shadow-md focus:shadow-primary/10 select-all"
                    />
                    {i === 2 && <span className="w-1.5 h-1 rounded-full bg-outline-variant/60"></span>}
                  </React.Fragment>
                ))}
              </div>

              <div className="flex items-center justify-between pt-1">
                {timeLeft > 0 ? (
                  <div className="flex items-center gap-2 text-on-surface-variant text-xs">
                    <span className="material-symbols-outlined text-[16px] text-outline">history</span>
                    <span>
                      Resend code in{' '}
                      <span className="font-financial-numeric font-bold text-on-surface">
                        00:{timeLeft < 10 ? `0${timeLeft}` : timeLeft}
                      </span>
                    </span>
                  </div>
                ) : (
                  <div></div>
                )}

                <button
                  type="button"
                  onClick={handleResend}
                  disabled={timeLeft > 0 || isLoading}
                  className={`text-primary text-xs font-semibold inline-flex items-center gap-1 cursor-pointer border-0 bg-transparent ${
                    timeLeft > 0 ? 'hidden' : 'hover:underline'
                  }`}
                >
                  <span className="material-symbols-outlined text-[16px]">sync</span>
                  <span>Resend SMS</span>
                </button>
              </div>

              <button
                type="submit"
                disabled={!isComplete || isLoading}
                className="w-full py-3.5 px-6 rounded-lg bg-primary text-on-primary text-sm font-semibold flex items-center justify-center gap-2 shadow-sm hover:bg-primary-container active:scale-[0.99] transition-all duration-150 cursor-pointer disabled:opacity-50 border-0"
              >
                {isLoading ? (
                  <>
                    <span className="material-symbols-outlined text-[20px] animate-spin">
                      progress_activity
                    </span>
                    <span>Verifying...</span>
                  </>
                ) : (
                  <>
                    <span>Verify &amp; Continue</span>
                    <span className="material-symbols-outlined text-[20px]">arrow_forward</span>
                  </>
                )}
              </button>
            </form>
          </div>

          <div className="mt-6 flex items-center justify-center gap-2 text-on-surface-variant text-xs">
            <span className="material-symbols-outlined text-[15px] text-primary">verified_user</span>
            <span>Secured via Delala Bekise Multi-Sig Vault Protocol</span>
          </div>
        </div>
      </div>
    </AuthLayout>
  );
};
