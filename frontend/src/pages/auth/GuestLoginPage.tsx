import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AuthLayout } from '../../components/auth/AuthLayout';
import { useAuth } from '../../context/useAuth';
import { useAuthFlow } from '../../context/useAuthFlow';
import { ApiError } from '../../services/apiClient';

export const GuestLoginPage: React.FC = () => {
  const navigate = useNavigate();
  const { sendOtp } = useAuth();
  const { flowData, updateFlowData } = useAuthFlow();

  const [phone, setPhone] = useState(flowData.phone || '');
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const formatPhoneNumber = (val: string) => {
    let clean = val.replace(/\D/g, '');
    if (clean.startsWith('251')) clean = clean.substring(3);
    if (clean.startsWith('0')) clean = clean.substring(1);

    let formatted = '';
    if (clean.length > 0) formatted += clean.substring(0, 3);
    if (clean.length >= 4) formatted += ' ' + clean.substring(3, 6);
    if (clean.length >= 7) formatted += ' ' + clean.substring(6, 9);
    return formatted;
  };

  const handlePhoneChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const formatted = formatPhoneNumber(e.target.value);
    setPhone(formatted);
    updateFlowData({ phone: formatted });
    if (errorMessage) setErrorMessage(null);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const rawDigits = phone.replace(/\s+/g, '');
    if (rawDigits.length !== 9) {
      setErrorMessage('Please enter a valid 9-digit Ethiopian mobile number (e.g. 911 234 567)');
      return;
    }

    const normalizedPhone = `+251${rawDigits}`;
    setIsLoading(true);
    setErrorMessage(null);

    try {
      await sendOtp({
        phone: normalizedPhone,
      });
      updateFlowData({ phone: normalizedPhone, role: 'guest' });
      navigate('/auth/verify-otp');
    } catch (err) {
      if (err instanceof ApiError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Failed to send SMS code. Please check your network connection.');
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <AuthLayout>
      <div className="relative w-full overflow-hidden py-10 px-6 sm:px-12 flex justify-center items-center">
        {/* Ambient background accents */}
        <div className="absolute -top-32 -left-20 w-96 h-96 rounded-full bg-primary-fixed/30 blur-3xl pointer-events-none"></div>
        <div className="absolute -bottom-24 -right-16 w-80 h-80 rounded-full bg-surface-container-high/60 blur-2xl pointer-events-none"></div>

        <div className="w-full max-w-[460px] relative z-10">
          <div className="mb-8">
            <Link
              to="/auth/choose-role"
              className="inline-flex items-center gap-1.5 text-on-surface-variant hover:text-primary transition-colors text-xs font-semibold group no-underline"
            >
              <span className="material-symbols-outlined text-[16px] transition-transform group-hover:-translate-x-0.5">
                arrow_back
              </span>
              <span>Back to role selection</span>
            </Link>
          </div>

          <div className="bg-surface-container-lowest p-8 sm:p-10 rounded-xl shadow-xl shadow-on-surface/[0.03]">
            <header className="mb-8">
              <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-container-low text-primary text-xs font-semibold mb-3">
                <span className="material-symbols-outlined text-[14px]">shield_person</span>
                <span>Single Transaction Mode</span>
              </div>
              <h1 className="font-headline text-2xl text-on-surface mb-2 font-bold tracking-tight">
                Continue as Guest Buyer
              </h1>
              <p className="text-sm text-on-surface-variant leading-relaxed">
                Enter your phone number to get started. No password needed.
              </p>
            </header>

            {errorMessage && (
              <div className="mb-5 p-3 rounded-lg bg-error-container text-on-error-container text-xs flex items-center gap-2">
                <span className="material-symbols-outlined text-[16px] shrink-0">error</span>
                <span>{errorMessage}</span>
              </div>
            )}

            <form className="space-y-5" onSubmit={handleSubmit}>
              <div className="space-y-1.5">
                <label className="block text-sm font-semibold text-on-surface" htmlFor="phoneNumber">
                  Ethiopian Phone Number <span className="text-error">*</span>
                </label>
                <div className="relative flex rounded-lg bg-surface-container-low/50 transition-all duration-200 focus-within:shadow-[0_0_0_3px_rgba(0,105,72,0.15)] focus-within:ring-1 focus-within:ring-primary focus-within:bg-surface-container-lowest">
                  <div className="relative flex items-center">
                    <div className="h-[46px] pl-3.5 pr-2.5 flex items-center gap-1.5 font-financial-numeric text-sm text-on-surface select-none rounded-l-lg">
                      <span className="w-5 h-3.5 rounded-[2px] overflow-hidden flex flex-col justify-between shadow-xs">
                        <span className="w-full h-1 bg-[#009639]"></span>
                        <span className="w-full h-1 bg-[#FFDE00]"></span>
                        <span className="w-full h-1 bg-[#DA121A]"></span>
                      </span>
                      <span className="font-medium">+251</span>
                      <span className="material-symbols-outlined text-[16px] text-on-surface-variant">
                        keyboard_arrow_down
                      </span>
                    </div>
                  </div>
                  <input
                    id="phoneNumber"
                    type="tel"
                    inputMode="numeric"
                    maxLength={11}
                    value={phone}
                    onChange={handlePhoneChange}
                    placeholder="911 234 567"
                    required
                    disabled={isLoading}
                    className="w-full h-[46px] pr-3.5 pl-1.5 bg-transparent text-on-surface font-financial-numeric text-sm placeholder:text-outline/70 outline-none tracking-wide"
                  />
                </div>
                <p className="pt-1 flex items-center gap-1.5 text-xs text-on-surface-variant">
                  <span className="material-symbols-outlined text-[15px] text-primary">sms</span>
                  <span>We will send a 6-digit verification code via SMS.</span>
                </p>
              </div>

              <div className="pt-3">
                <button
                  type="submit"
                  disabled={isLoading}
                  className="w-full h-12 rounded-lg bg-primary text-on-primary hover:bg-primary-container text-sm font-semibold transition-all duration-150 flex items-center justify-center gap-2 shadow-md shadow-primary/20 active:scale-[0.99] disabled:opacity-80 cursor-pointer border-0"
                >
                  {isLoading ? (
                    <>
                      <span className="material-symbols-outlined text-[18px] animate-spin">
                        progress_activity
                      </span>
                      <span>Sending SMS Code...</span>
                    </>
                  ) : (
                    <>
                      <span>Continue to SMS Verification</span>
                      <span className="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </>
                  )}
                </button>
              </div>
            </form>

            <div className="mt-8 pt-6 border-t border-outline-variant/20 flex flex-col items-center justify-center text-center space-y-2">
              <p className="text-sm text-on-surface-variant">
                Already have an account?{' '}
                <Link
                  to="/auth/login"
                  className="font-semibold text-primary hover:underline transition-all"
                >
                  Log in
                </Link>
              </p>
              <p className="text-xs text-on-surface-variant">
                Want a permanent account instead?{' '}
                <Link
                  to="/auth/register/buyer"
                  className="font-semibold text-primary hover:underline transition-all"
                >
                  Register as Buyer
                </Link>
              </p>
            </div>
          </div>

          <div className="mt-6 flex items-center justify-center gap-4 text-on-surface-variant text-xs">
            <span className="flex items-center gap-1">
              <span className="material-symbols-outlined text-[14px]">lock</span>
              256-bit Bank Grade Security
            </span>
            <span className="w-1 h-1 rounded-full bg-outline-variant"></span>
            <span>Instant Activation</span>
          </div>
        </div>
      </div>
    </AuthLayout>
  );
};
