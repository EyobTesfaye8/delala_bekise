import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AuthLayout } from '../../components/auth/AuthLayout';
import { useAuth } from '../../context/useAuth';
import { useAuthFlow } from '../../context/useAuthFlow';
import { ApiError } from '../../services/apiClient';

export const SignUpBuyerPage: React.FC = () => {
  const navigate = useNavigate();
  const { register } = useAuth();
  const { flowData, updateFlowData } = useAuthFlow();

  const [fullName, setFullName] = useState(flowData.fullName || '');
  const [phone, setPhone] = useState(flowData.phone ? flowData.phone.replace('+251', '') : '');
  const [email, setEmail] = useState(flowData.email || '');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [agreed, setAgreed] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handlePhoneChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    let clean = e.target.value.replace(/\D/g, '');
    if (clean.startsWith('251')) clean = clean.substring(3);
    if (clean.startsWith('0')) clean = clean.substring(1);
    if (clean.length > 9) clean = clean.substring(0, 9);

    let formatted = '';
    if (clean.length > 0) formatted += clean.substring(0, 2);
    if (clean.length > 2) formatted += ' ' + clean.substring(2, 5);
    if (clean.length > 5) formatted += ' ' + clean.substring(5, 9);
    setPhone(formatted);
    if (errorMessage) setErrorMessage(null);
  };

  const getPasswordScore = () => {
    let score = 0;
    if (password.length >= 8) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    return score;
  };
  const score = getPasswordScore();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!agreed) {
      setErrorMessage('You must agree to the Terms of Service & Escrow Settlement Policy.');
      return;
    }

    if (password !== passwordConfirmation) {
      setErrorMessage('Passwords do not match. Please ensure both fields are identical.');
      return;
    }

    const rawDigits = phone.replace(/\s+/g, '');
    if (rawDigits.length !== 9) {
      setErrorMessage('Please enter a valid 9-digit Ethiopian mobile number (e.g. 91 234 5678)');
      return;
    }

    const normalizedPhone = `+251${rawDigits}`;
    setIsLoading(true);
    setErrorMessage(null);

    try {
      await register({
        full_name: fullName,
        phone: normalizedPhone,
        email,
        password,
        password_confirmation: passwordConfirmation,
        type: 'buyer',
      });
      updateFlowData({
        fullName,
        phone: normalizedPhone,
        email,
        role: 'buyer',
      });
      navigate('/auth/verify-otp');
    } catch (err) {
      if (err instanceof ApiError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Registration failed. Please check your information.');
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <AuthLayout>
      <div className="w-full max-w-5xl mx-auto px-6 py-8 md:py-12 flex flex-col items-center">
        {/* Top Context & Back Navigation */}
        <div className="w-full max-w-xl flex items-center justify-between mb-8">
          <Link
            to="/auth/choose-role"
            className="group inline-flex items-center gap-1.5 text-on-surface-variant hover:text-primary transition-colors text-xs font-semibold no-underline"
          >
            <span className="material-symbols-outlined text-[18px] group-hover:-translate-x-0.5 transition-transform">
              arrow_back
            </span>
            <span>Back to role selection</span>
          </Link>
          <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-container text-xs text-on-surface-variant font-medium">
            <span className="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
            <span>Tier 1 Buyer Registry</span>
          </div>
        </div>

        {/* Main Card Structure */}
        <div className="w-full max-w-xl bg-surface-container-lowest rounded-xl shadow-xl shadow-slate-900/5 p-8 md:p-10 relative overflow-hidden">
          {/* Trust Indicator Strip */}
          <div className="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-primary via-primary-container to-secondary-container"></div>

          <header className="mb-8">
            <div className="flex items-center gap-2 mb-3">
              <span className="px-2 py-0.5 rounded bg-primary-fixed text-on-primary-fixed-variant text-xs font-medium">
                Institutional escrow
              </span>
              <span className="text-xs text-on-surface-variant">Buyer portal</span>
            </div>
            <h1 className="font-headline text-2xl md:text-3xl text-on-surface font-bold tracking-tight">
              Create Buyer Account
            </h1>
            <p className="text-sm text-on-surface-variant mt-2 leading-relaxed">
              Register to manage escrows, inspect milestones, and hire verified brokers across Ethiopia.
            </p>
          </header>

          {errorMessage && (
            <div className="mb-5 p-3 rounded-lg bg-error-container text-on-error-container text-xs flex items-center gap-2">
              <span className="material-symbols-outlined text-[16px] shrink-0">error</span>
              <span>{errorMessage}</span>
            </div>
          )}

          <form className="space-y-5" onSubmit={handleSubmit}>
            {/* Full Name */}
            <div className="space-y-1.5">
              <label className="block text-xs font-semibold text-on-surface" htmlFor="full-name">
                Full Name <span className="text-error">*</span>
              </label>
              <div className="relative flex items-center">
                <span className="absolute left-3.5 text-on-surface-variant/60 material-symbols-outlined text-[20px] pointer-events-none">
                  person
                </span>
                <input
                  id="full-name"
                  type="text"
                  value={fullName}
                  onChange={(e) => {
                    setFullName(e.target.value);
                    updateFlowData({ fullName: e.target.value });
                  }}
                  placeholder="e.g. Dawit Tadesse"
                  required
                  disabled={isLoading}
                  className="w-full h-11 pl-11 pr-4 rounded-lg bg-surface-container-low focus:bg-surface-container-lowest text-on-surface text-sm placeholder:text-on-surface-variant/40 outline-none focus:ring-2 focus:ring-primary/20 transition-all"
                />
              </div>
            </div>

            {/* Ethiopian Phone Number */}
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label className="block text-xs font-semibold text-on-surface" htmlFor="phone-number">
                  Ethiopian Phone Number <span className="text-error">*</span>
                </label>
                <span className="text-xs text-on-surface-variant">Telebirr / CBE Linked</span>
              </div>
              <div className="relative flex items-center rounded-lg bg-surface-container-low focus-within:bg-surface-container-lowest focus-within:ring-2 focus-within:ring-primary/20 transition-all overflow-hidden">
                <div className="flex items-center gap-1.5 px-3.5 py-2.5 bg-surface-container text-on-surface-variant text-xs font-semibold select-none">
                  <span className="w-4 h-3 rounded-xs bg-gradient-to-b from-[#009b44] via-[#fcd116] to-[#e4002b] flex items-center justify-center text-[7px] text-blue-900 font-bold">
                    ★
                  </span>
                  <span>+251</span>
                </div>
                <input
                  id="phone-number"
                  type="tel"
                  maxLength={10}
                  value={phone}
                  onChange={handlePhoneChange}
                  placeholder="91 234 5678"
                  required
                  disabled={isLoading}
                  className="w-full h-11 px-3.5 bg-transparent text-on-surface font-financial-numeric text-sm placeholder:text-on-surface-variant/40 outline-none"
                />
              </div>
            </div>

            {/* Email Address */}
            <div className="space-y-1.5">
              <label className="block text-xs font-semibold text-on-surface" htmlFor="email-address">
                Email Address <span className="text-error">*</span>
              </label>
              <div className="relative flex items-center">
                <span className="absolute left-3.5 text-on-surface-variant/60 material-symbols-outlined text-[20px] pointer-events-none">
                  mail
                </span>
                <input
                  id="email-address"
                  type="email"
                  value={email}
                  onChange={(e) => {
                    setEmail(e.target.value);
                    updateFlowData({ email: e.target.value });
                  }}
                  placeholder="dawit@enterprise.et"
                  required
                  disabled={isLoading}
                  className="w-full h-11 pl-11 pr-4 rounded-lg bg-surface-container-low focus:bg-surface-container-lowest text-on-surface text-sm placeholder:text-on-surface-variant/40 outline-none focus:ring-2 focus:ring-primary/20 transition-all"
                />
              </div>
            </div>

            {/* Password */}
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label className="block text-xs font-semibold text-on-surface" htmlFor="password">
                  Password <span className="text-error">*</span>
                </label>
                <span className="text-xs text-on-surface-variant">
                  {score === 0 ? 'Security grade' : score <= 2 ? 'Moderate' : 'Optimal Protection'}
                </span>
              </div>
              <div className="relative flex items-center">
                <span className="absolute left-3.5 text-on-surface-variant/60 material-symbols-outlined text-[20px] pointer-events-none">
                  lock
                </span>
                <input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Minimum 8 characters"
                  required
                  disabled={isLoading}
                  className="w-full h-11 pl-11 pr-11 rounded-lg bg-surface-container-low focus:bg-surface-container-lowest text-on-surface text-sm placeholder:text-on-surface-variant/40 outline-none focus:ring-2 focus:ring-primary/20 transition-all"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  aria-label="Toggle password visibility"
                  className="absolute right-3 text-on-surface-variant hover:text-on-surface p-1 rounded transition-colors cursor-pointer border-0 bg-transparent"
                >
                  <span className="material-symbols-outlined text-[20px]">
                    {showPassword ? 'visibility_off' : 'visibility'}
                  </span>
                </button>
              </div>

              {/* Strength Meter Bars */}
              <div className="pt-2">
                <div className="grid grid-cols-4 gap-1.5 h-1.5 w-full">
                  <div
                    className={`h-full rounded-full transition-colors duration-300 ${
                      score >= 1 ? 'bg-primary' : 'bg-surface-container'
                    }`}
                  ></div>
                  <div
                    className={`h-full rounded-full transition-colors duration-300 ${
                      score >= 2 ? 'bg-primary' : 'bg-surface-container'
                    }`}
                  ></div>
                  <div
                    className={`h-full rounded-full transition-colors duration-300 ${
                      score >= 3 ? 'bg-primary' : 'bg-surface-container'
                    }`}
                  ></div>
                  <div
                    className={`h-full rounded-full transition-colors duration-300 ${
                      score >= 4 ? 'bg-primary' : 'bg-surface-container'
                    }`}
                  ></div>
                </div>
                <div className="flex items-center justify-between mt-2 text-xs text-on-surface-variant">
                  <span>8+ chars</span>
                  <span>1 number</span>
                  <span>1 uppercase</span>
                  <span>1 symbol</span>
                </div>
              </div>
            </div>

            {/* Password Confirmation */}
            <div className="space-y-1.5">
              <label
                className="block text-xs font-semibold text-on-surface"
                htmlFor="password-confirmation"
              >
                Confirm Password <span className="text-error">*</span>
              </label>
              <div className="relative flex items-center">
                <span className="absolute left-3.5 text-on-surface-variant/60 material-symbols-outlined text-[20px] pointer-events-none">
                  lock_reset
                </span>
                <input
                  id="password-confirmation"
                  type={showPassword ? 'text' : 'password'}
                  value={passwordConfirmation}
                  onChange={(e) => setPasswordConfirmation(e.target.value)}
                  placeholder="Re-enter password"
                  required
                  disabled={isLoading}
                  className="w-full h-11 pl-11 pr-4 rounded-lg bg-surface-container-low focus:bg-surface-container-lowest text-on-surface text-sm placeholder:text-on-surface-variant/40 outline-none focus:ring-2 focus:ring-primary/20 transition-all"
                />
              </div>
            </div>

            {/* Terms & Policy Checkbox */}
            <div className="pt-2">
              <label className="flex items-start gap-3 cursor-pointer group select-none">
                <input
                  id="terms"
                  type="checkbox"
                  checked={agreed}
                  onChange={(e) => setAgreed(e.target.checked)}
                  required
                  className="mt-0.5 w-4 h-4 rounded text-primary focus:ring-primary/30 accent-primary cursor-pointer"
                />
                <span className="text-xs text-on-surface-variant leading-relaxed">
                  I agree to the <span className="text-primary font-medium hover:underline">Terms of Service</span> &amp;{' '}
                  <span className="text-primary font-medium hover:underline">Escrow Settlement Policy</span> sanctioned by NBE sandbox guidelines.
                </span>
              </label>
            </div>

            {/* Primary CTA Button */}
            <div className="pt-3">
              <button
                type="submit"
                disabled={isLoading}
                className="w-full h-12 rounded-lg bg-primary hover:bg-primary-container active:scale-[0.99] text-on-primary font-headline text-sm font-semibold flex items-center justify-center gap-2 shadow-sm transition-all disabled:opacity-50 cursor-pointer border-0"
              >
                {isLoading ? (
                  <>
                    <span className="material-symbols-outlined text-[20px] animate-spin">
                      progress_activity
                    </span>
                    <span>Creating Account...</span>
                  </>
                ) : (
                  <>
                    <span>Create Account &amp; Continue</span>
                    <span className="material-symbols-outlined text-[20px]">arrow_forward</span>
                  </>
                )}
              </button>
            </div>
          </form>

          {/* Institutional Assurance Footer */}
          <div className="mt-8 pt-6 bg-surface-container-low/50 -mx-8 -mb-8 md:-mx-10 md:-mb-10 px-8 py-5 flex items-center justify-between text-xs text-on-surface-variant">
            <div className="flex items-center gap-2">
              <span className="material-symbols-outlined text-primary text-[18px]">verified_user</span>
              <span>Multi-sig vault custodial assurance</span>
            </div>
            <span className="font-financial-numeric font-mono">AES-256</span>
          </div>
        </div>

        {/* Bottom Role/Sign-In Switch */}
        <div className="mt-8 text-center">
          <p className="text-sm text-on-surface-variant">
            Already have an account?{' '}
            <Link to="/auth/login" className="text-primary font-semibold hover:underline ml-1">
              Log in
            </Link>
          </p>
        </div>
      </div>
    </AuthLayout>
  );
};
