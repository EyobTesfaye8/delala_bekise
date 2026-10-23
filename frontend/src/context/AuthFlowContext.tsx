import React, { useState } from 'react';
import {
  AuthFlowContext,
  defaultFlowState,
  type AuthFlowState,
} from './authFlowContextDef';

export const AuthFlowProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [flowData, setFlowData] = useState<AuthFlowState>(() => {
    try {
      const saved = sessionStorage.getItem('delala_auth_flow');
      return saved ? JSON.parse(saved) : defaultFlowState;
    } catch {
      return defaultFlowState;
    }
  });

  const updateFlowData = (data: Partial<AuthFlowState>) => {
    setFlowData((prev) => {
      const updated = { ...prev, ...data };
      try {
        sessionStorage.setItem('delala_auth_flow', JSON.stringify(updated));
      } catch {
        // Ignore session storage errors
      }
      return updated;
    });
  };

  const resetFlowData = () => {
    setFlowData(defaultFlowState);
    try {
      sessionStorage.removeItem('delala_auth_flow');
    } catch {
      // Ignore
    }
  };

  return (
    <AuthFlowContext.Provider value={{ flowData, updateFlowData, resetFlowData }}>
      {children}
    </AuthFlowContext.Provider>
  );
};
