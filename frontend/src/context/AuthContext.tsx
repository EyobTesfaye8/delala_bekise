import React, { useEffect, useState } from 'react';
import type {
  AuthResponse,
  LoginRequest,
  MigrateGuestRequest,
  RegisterRequest,
  SendOtpRequest,
  User,
  VerifyOtpRequest,
} from '../types/auth';
import { AuthContext } from './authContextDef';
import { authService } from '../services/authService';
import { tokenStorage } from '../services/apiClient';

const applyUserOverrides = (baseUser: User | null): User | null => {
  if (!baseUser) return null;
  try {
    const raw = localStorage.getItem('delala_user_overrides');
    if (raw) {
      const overrides = JSON.parse(raw);
      return { ...baseUser, ...overrides };
    }
  } catch {
    // ignore
  }
  return baseUser;
};

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(tokenStorage.get());
  const [isLoading, setIsLoading] = useState<boolean>(() => !!tokenStorage.get());

  const refreshUser = async () => {
    const currentToken = tokenStorage.get();
    if (!currentToken) {
      setUser(null);
      setIsLoading(false);
      return;
    }

    try {
      const currentUser = await authService.getCurrentUser();
      setUser(applyUserOverrides(currentUser));
    } catch {
      tokenStorage.clear();
      setToken(null);
      setUser(null);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    let mounted = true;
    const initAuth = async () => {
      const currentToken = tokenStorage.get();
      if (!currentToken) {
        if (mounted) setIsLoading(false);
        return;
      }
      try {
        const currentUser = await authService.getCurrentUser();
        if (mounted) setUser(applyUserOverrides(currentUser));
      } catch {
        if (mounted) {
          tokenStorage.clear();
          setToken(null);
          setUser(null);
        }
      } finally {
        if (mounted) setIsLoading(false);
      }
    };

    initAuth();
    return () => {
      mounted = false;
    };
  }, []);

  const login = async (payload: LoginRequest): Promise<AuthResponse> => {
    const res = await authService.login(payload);
    if (res.token) {
      setToken(res.token);
    }
    if (res.user) {
      setUser(applyUserOverrides(res.user));
    }
    return res;
  };

  const register = async (payload: RegisterRequest): Promise<AuthResponse> => {
    const res = await authService.register(payload);
    if (res.token) {
      setToken(res.token);
    }
    if (res.user) {
      setUser(applyUserOverrides(res.user));
    }
    return res;
  };

  const sendOtp = async (payload: SendOtpRequest): Promise<{ message: string }> => {
    return authService.sendOtp(payload);
  };

  const verifyOtp = async (payload: VerifyOtpRequest): Promise<AuthResponse> => {
    const res = await authService.verifyOtp(payload);
    if (res.token) {
      setToken(res.token);
    }
    if (res.user) {
      setUser(applyUserOverrides(res.user));
    }
    return res;
  };

  const migrateGuest = async (payload: MigrateGuestRequest): Promise<void> => {
    const res = await authService.migrateGuest(payload);
    if (res.user) {
      setUser(applyUserOverrides(res.user));
    }
  };

  const logout = async (): Promise<void> => {
    await authService.logout();
    setToken(null);
    setUser(null);
  };

  const updateUser = (partial: Partial<User>): void => {
    setUser((prev) => {
      if (!prev) return null;
      const updated = { ...prev, ...partial };
      try {
        const raw = localStorage.getItem('delala_user_overrides');
        const overrides = raw ? JSON.parse(raw) : {};
        localStorage.setItem('delala_user_overrides', JSON.stringify({ ...overrides, ...partial }));
      } catch {
        // ignore
      }
      return updated;
    });
  };

  const isGuest = user?.type === 'guest';
  const isBuyer = user?.type === 'buyer' || isGuest;
  const isDelala = user?.type === 'delala';
  const isAuthenticated = !!token && !!user;

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        isLoading,
        isAuthenticated,
        isGuest,
        isBuyer,
        isDelala,
        login,
        register,
        sendOtp,
        verifyOtp,
        migrateGuest,
        logout,
        refreshUser,
        updateUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};
