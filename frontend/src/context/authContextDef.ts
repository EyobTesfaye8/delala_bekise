import { createContext } from 'react';
import type {
  AuthResponse,
  LoginRequest,
  MigrateGuestRequest,
  RegisterRequest,
  SendOtpRequest,
  User,
  VerifyOtpRequest,
} from '../types/auth';

export interface AuthContextType {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  isGuest: boolean;
  isBuyer: boolean;
  isDelala: boolean;
  login: (payload: LoginRequest) => Promise<AuthResponse>;
  register: (payload: RegisterRequest) => Promise<AuthResponse>;
  sendOtp: (payload: SendOtpRequest) => Promise<{ message: string }>;
  verifyOtp: (payload: VerifyOtpRequest) => Promise<AuthResponse>;
  migrateGuest: (payload: MigrateGuestRequest) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  updateUser: (partial: Partial<User>) => void;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);
