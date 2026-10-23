import { createContext } from 'react';

export interface AuthFlowState {
  phone: string;
  email: string;
  fullName: string;
  role: 'guest' | 'buyer' | 'delala' | null;
}

export interface AuthFlowContextType {
  flowData: AuthFlowState;
  updateFlowData: (data: Partial<AuthFlowState>) => void;
  resetFlowData: () => void;
}

export const defaultFlowState: AuthFlowState = {
  phone: '',
  email: '',
  fullName: '',
  role: null,
};

export const AuthFlowContext = createContext<AuthFlowContextType | undefined>(undefined);
