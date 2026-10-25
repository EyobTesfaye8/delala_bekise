import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AuthLayout } from '../../components/auth/AuthLayout';
import { useAuth } from '../../context/useAuth';
import { useAuthFlow } from '../../context/useAuthFlow';
import { ApiError } from '../../services/apiClient';

export const SignUpDelalaPage: React.FC = () => {
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

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!agreed) {
      setErrorMessage(
        'You must agree to the 5% platform escrow commission & regulatory guidelines.'
      );
      return;
    }

    if (password !== passwordConfirmation) {
      setErrorMessage('Passwords do not match. Please ensure both fields are identical.');
      return;
    }

    const rawDigits = phone.replace(/\s+/g, '');
    if (rawDigits.length !== 9) {
      setErrorMessage('Please enter a valid 9-digit Ethiopian mobile number (e.g. 91 123 4567)');
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
        type: 'delala',
      });
      updateFlowData({
        fullName,
        phone: normalizedPhone,
        email,
        role: 'delala',
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
      <div className="relative w-full overflow-hidden py-12 px-6 flex items-center justify-center">
        {/* Ambient background accents */}
        <div className="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-primary-fixed/30 blur-3xl pointer-events-none"></div>
        <div className="absolute -bottom-40 -right-20 w-80 h-80 rounded-full bg-secondary-fixed/40 blur-3xl pointer-events-none"></div>

        <div className="w-full max-w-xl relative z-10">
          <div className="mb-8">
            <Link
              to="/auth/choose-role"
              className="inline-flex items-center gap-1.5 text-xs font-semibold text-on-surface-variant hover:text-primary transition-colors group no-underline"
            >
              <span className="material-symbols-outlined text-[16px] transition-transform group-hover:-translate-x-1">
                arrow_back
              </span>
              <span>Back to role selection</span>
            </Link>
          </div>

          <div className="bg-surface-container-lowest rounded-xl shadow-xl p-8 sm:p-10 relative overflow-hidden">
            <div className="flex items-center justify-between gap-4 mb-3">
              <div className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-container text-xs font-semibold text-primary">
                <span className="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                <span>LICENSED BROKER ONBOARDING</span>
              </div>
              <span className="font-financial-numeric text-xs text-on-surface-variant text-right">Step 1 of 2</span>
            </div>

            <div className="mb-8">
              <h1 className="font-headline text-2xl sm:text-3xl text-on-surface tracking-tight mb-2 font-bold">
                Join as a Delala
              </h1>
              <p className="text-sm text-on-surface-variant leading-relaxed">
                Provide your details to start bidding on verified escrow listings across Ethiopia.
              </p>
            </div>

            {errorMessage && (
              <div className="mb-5 p-3 rounded-lg bg-error-container text-on-error-container text-xs flex items-center gap-2">
                <span className="material-symbols-outlined text-[16px] shrink-0">error</span>
                <span>{errorMessage}</span>
              </div>
            )}

            <form className="space-y-5" onSubmit={handleSubmit}>
              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1.5" htmlFor="fullname">
                  Full Name <span className="text-error">*</span>
                </label>
                <div className="relative flex items-center">
                  <span className="material-symbols-outlined absolute left-3.5 text-on-surface-variant text-[20px] pointer-events-none">
                    badge
                  </span>
                  <input
                    id="fullname"
                    type="text"
                    value={fullName}
                    onChange={(e) => {
                      setFullName(e.target.value);
                      updateFlowData({ fullName: e.target.value });
                    }}
                    placeholder="Abebe Bikila Kebede"
                    required
                    disabled={isLoading}
                    className="w-full h-11 pl-11 pr-4 rounded-lg bg-surface-container-low text-sm text-on-surface placeholder:text-outline-variant focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all shadow-xs"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-on-surface mb-1.5" htmlFor="phone">
                    Phone Number <span className="text-error">*</span>
                  </label>
                  <div className="relative flex items-center">
                    <span className="material-symbols-outlined absolute left-3.5 text-on-surface-variant text-[18px] pointer-events-none">
                      call
                    </span>
                    <input
                      id="phone"
                      type="tel"
                      value={phone}
                      onChange={handlePhoneChange}
                      placeholder="+251 91 123 4567"
                      required
                      disabled={isLoading}
                      className="w-full h-11 pl-11 pr-4 rounded-lg bg-surface-container-low font-financial-numeric text-sm text-on-surface placeholder:text-outline-variant focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all shadow-xs"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-on-surface mb-1.5" htmlFor="email">
                    Email Address <span className="text-error">*</span>
                  </label>
                  <div className="relative flex items-center">
                    <span className="material-symbols-outlined absolute left-3.5 text-on-surface-variant text-[18px] pointer-events-none">
                      mail
                    </span>
                    <input
                      id="email"
                      type="email"
                      value={email}
                      onChange={(e) => {
                        setEmail(e.target.value);
                        updateFlowData({ email: e.target.value });
                      }}
                      placeholder="broker@delala.et"
                      required
                      disabled={isLoading}
                      className="w-full h-11 pl-11 pr-4 rounded-lg bg-surface-container-low text-sm text-on-surface placeholder:text-outline-variant focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all shadow-xs"
                    />
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1.5" htmlFor="password">
                  Password <span className="text-error">*</span>
                </label>
                <div className="relative flex items-center">
                  <span className="material-symbols-outlined absolute left-3.5 text-on-surface-variant text-[20px] pointer-events-none">
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
                    className="w-full h-11 pl-11 pr-11 rounded-lg bg-surface-container-low text-sm text-on-surface placeholder:text-outline-variant focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all shadow-xs"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    aria-label="Toggle password visibility"
                    className="absolute right-3.5 text-on-surface-variant hover:text-on-surface transition-colors focus:outline-none cursor-pointer border-0 bg-transparent"
                  >
                    <span className="material-symbols-outlined text-[20px]">
                      {showPassword ? 'visibility_off' : 'visibility'}
                    </span>
                  </button>
                </div>
              </div>

              {/* Password Confirmation */}
              <div>
                <label
                  className="block text-xs font-semibold text-on-surface mb-1.5"
                  htmlFor="password-confirmation"
                >
                  Confirm Password <span className="text-error">*</span>
                </label>
                <div className="relative flex items-center">
                  <span className="material-symbols-outlined absolute left-3.5 text-on-surface-variant text-[20px] pointer-events-none">
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
                    className="w-full h-11 pl-11 pr-4 rounded-lg bg-surface-container-low text-sm text-on-surface placeholder:text-outline-variant focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all shadow-xs"
                  />
                </div>
              </div>

              <div className="pt-2">
                <label className="flex items-start gap-2.5 cursor-pointer group select-none">
                  <input
                    id="agreement"
                    type="checkbox"
                    checked={agreed}
                    onChange={(e) => setAgreed(e.target.checked)}
                    required
                    className="mt-1 h-4 w-4 rounded text-primary focus:ring-primary/30 accent-primary cursor-pointer"
                  />
                  <span className="text-xs text-on-surface-variant leading-relaxed">
                    I agree to the <span className="font-semibold text-primary">5% platform escrow commission</span> &amp; licensing guidelines verified under the National Bank of Ethiopia regulatory sandbox.
                  </span>
                </label>
              </div>

              <div className="pt-4">
                <button
                  type="submit"
                  disabled={isLoading}
                  className="w-full h-12 rounded-lg bg-primary text-on-primary font-headline text-sm font-semibold flex items-center justify-center gap-2 hover:bg-primary-container shadow-md hover:shadow-lg active:scale-[0.99] transition-all disabled:opacity-50 cursor-pointer border-0"
                >
                  {isLoading ? (
                    <>
                      <span className="material-symbols-outlined text-[18px] animate-spin">
                        progress_activity
                      </span>
                      <span>Submitting...</span>
                    </>
                  ) : (
                    <>
                      <span>Submit Broker Application</span>
                      <span className="material-symbols-outlined text-[20px]">arrow_forward</span>
                    </>
                  )}
                </button>
              </div>
            </form>

            <div className="mt-8 pt-6 border-t-0 bg-surface-container-low/50 -mx-8 -mb-8 sm:-mx-10 sm:-mb-10 p-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left text-sm text-on-surface-variant">
              <p>Already have an account?</p>
              <Link
                to="/auth/login"
                className="inline-flex items-center gap-1 font-semibold text-primary hover:text-primary-container transition-colors"
              >
                <span>Log in</span>
                <span className="material-symbols-outlined text-[16px]">login</span>
              </Link>
            </div>
          </div>

          <div className="mt-8 flex items-center justify-center gap-6 text-on-surface-variant opacity-80 text-xs">
            <div className="flex items-center gap-1.5">
              <span className="material-symbols-outlined text-[16px] text-primary">verified_user</span>
              <span>Regulatory Compliance</span>
            </div>
            <span className="inline-block w-1 h-1 rounded-full bg-outline-variant"></span>
            <div className="flex items-center gap-1.5">
              <span className="material-symbols-outlined text-[16px] text-primary">lock</span>
              <span>256-bit Vault Security</span>
            </div>
          </div>
        </div>
      </div>
    </AuthLayout>
  );
};
