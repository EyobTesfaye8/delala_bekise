import { apiRequest, tokenStorage } from './apiClient';
import { getRecaptchaToken } from './recaptcha';
import type {
  AuthResponse,
  ForgotPasswordRequest,
  LoginRequest,
  MigrateGuestRequest,
  RegisterRequest,
  ResetPasswordRequest,
  SendOtpRequest,
  User,
  VerifyOtpRequest,
} from '../types/auth';

export const authService = {
  /**
   * Flow 1, Step 1: Send OTP to phone
   */
  async sendOtp(payload: SendOtpRequest): Promise<{ message: string }> {
    const recaptchaToken = payload['g-recaptcha-response'] || await getRecaptchaToken('send_otp');
    return apiRequest<{ message: string }>('/auth/send-otp', {
      method: 'POST',
      body: JSON.stringify({
        phone: payload.phone,
        'g-recaptcha-response': recaptchaToken,
      }),
    });
  },

  /**
   * Flow 1, Step 2: Verify OTP
   */
  async verifyOtp(payload: VerifyOtpRequest): Promise<AuthResponse> {
    const data = await apiRequest<AuthResponse>('/auth/verify-otp', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    if (data.token) {
      tokenStorage.set(data.token);
    }
    return data;
  },

  /**
   * Flow 2, Step 1: Register Buyer or Delala
   */
  async register(payload: RegisterRequest): Promise<AuthResponse> {
    const data = await apiRequest<AuthResponse>('/auth/register', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    if (data.token) {
      tokenStorage.set(data.token);
    }
    return data;
  },

  /**
   * Flow 2, Step 3: Login via Email & Password
   */
  async login(payload: LoginRequest): Promise<AuthResponse> {
    const data = await apiRequest<AuthResponse>('/auth/login', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    if (data.token) {
      tokenStorage.set(data.token);
    }
    return data;
  },

  /**
   * Flow 1, Step 3: Migrate Guest account to permanent Registered Buyer
   */
  async migrateGuest(payload: MigrateGuestRequest): Promise<{ message: string; user: User }> {
    return apiRequest<{ message: string; user: User }>('/auth/migrate', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  /**
   * Flow 3, Step 1: Request password reset link
   */
  async forgotPassword(payload: ForgotPasswordRequest): Promise<{ message: string }> {
    return apiRequest<{ message: string }>('/auth/forgot-password', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  /**
   * Flow 3, Step 2: Reset password using token from email
   */
  async resetPassword(payload: ResetPasswordRequest): Promise<{ message: string }> {
    return apiRequest<{ message: string }>('/auth/reset-password', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  /**
   * Logout and revoke Sanctum token
   */
  async logout(): Promise<void> {
    try {
      await apiRequest('/auth/logout', { method: 'POST', body: JSON.stringify({}) });
    } finally {
      tokenStorage.clear();
    }
  },

  /**
   * Fetch currently authenticated user
   */
  async getCurrentUser(): Promise<User> {
    return apiRequest<User>('/auth/me', { method: 'GET' });
  },
};
