import { useContext } from 'react';
import { BuyerContext, type BuyerContextType } from './buyerContextDef';

export const useBuyer = (): BuyerContextType => {
  const context = useContext(BuyerContext);
  if (!context) {
    throw new Error('useBuyer must be used within a BuyerProvider');
  }
  return context;
};
