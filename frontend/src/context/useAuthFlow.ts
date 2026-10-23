import { useContext } from 'react';
import { AuthFlowContext, type AuthFlowContextType } from './authFlowContextDef';

export const useAuthFlow = (): AuthFlowContextType => {
  const context = useContext(AuthFlowContext);
  if (!context) {
    throw new Error('useAuthFlow must be used within an AuthFlowProvider');
  }
  return context;
};
