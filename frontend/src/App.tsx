import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import { AuthFlowProvider } from './context/AuthFlowContext';
import { ChooseRolePage } from './pages/auth/ChooseRolePage';
import { GuestLoginPage } from './pages/auth/GuestLoginPage';
import { VerifyOtpPage } from './pages/auth/VerifyOtpPage';
import { SignInPage } from './pages/auth/SignInPage';
import { SignUpBuyerPage } from './pages/auth/SignUpBuyerPage';
import { SignUpDelalaPage } from './pages/auth/SignUpDelalaPage';
import { VerifyEmailPage } from './pages/auth/VerifyEmailPage';
import { ForgotPasswordPage } from './pages/auth/ForgotPasswordPage';
import { ResetPasswordPage } from './pages/auth/ResetPasswordPage';
import { BuyerPortal } from './pages/buyer/BuyerPortal';

export function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <AuthFlowProvider>
          <Routes>
            {/* Default entry goes directly to Buyer Portal */}
            <Route path="/" element={<Navigate to="/buyer" replace />} />

            {/* Buyer Portal (SPA Shell with Framer Motion AnimatePresence) */}
            <Route path="/buyer" element={<BuyerPortal />} />
            <Route path="/buyer/*" element={<BuyerPortal />} />
            <Route path="/portal" element={<Navigate to="/buyer" replace />} />

            {/* Auth Routes */}
            <Route path="/auth/choose-role" element={<ChooseRolePage />} />
            <Route path="/auth/guest" element={<GuestLoginPage />} />
            <Route path="/auth/verify-otp" element={<VerifyOtpPage />} />
            <Route path="/auth/login" element={<SignInPage />} />
            <Route path="/auth/register/buyer" element={<SignUpBuyerPage />} />
            <Route path="/auth/register/delala" element={<SignUpDelalaPage />} />
            <Route path="/auth/verify-email" element={<VerifyEmailPage />} />
            <Route path="/auth/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/auth/reset-password" element={<ResetPasswordPage />} />

            {/* Catch-all redirect */}
            <Route path="*" element={<Navigate to="/buyer" replace />} />
          </Routes>
        </AuthFlowProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
