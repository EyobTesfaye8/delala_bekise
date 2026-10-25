export type UserType = 'guest' | 'buyer' | 'delala' | 'admin';
export type UserStatus = 'unverified' | 'active' | 'suspended';

export interface User {
  id: number;
  full_name?: string | null;
  phone: string;
  email?: string | null;
  type: UserType;
  status: UserStatus;
  guest_uuid?: string | null;
  avatar_s3_key?: string | null;
  phone_verified_at?: string | null;
  email_verified_at?: string | null;
  totp_enabled_at?: string | null;
  fcm_token?: string | null;
  bio?: string | null;
  circumvention_strike_count: number;
  login_attempt_count?: number;
  locked_until?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface AuthResponse {
  message: string;
  token?: string;
  user?: User;
}

export interface SendOtpRequest {
  phone: string;
  'g-recaptcha-response'?: string;
}

export interface VerifyOtpRequest {
  phone: string;
  code: string;
}

export interface RegisterRequest {
  full_name: string;
  phone: string;
  email: string;
  password: string;
  password_confirmation: string;
  type?: 'buyer' | 'delala';
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface ForgotPasswordRequest {
  email: string;
}

export interface ResetPasswordRequest {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface MigrateGuestRequest {
  full_name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface AccountLockedError {
  message: string;
  error_code: 'ACCOUNT_LOCKED';
  locked_until: string;
}
