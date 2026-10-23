import React from 'react';
import { BuyerProvider } from '../../context/BuyerContext';
import { BuyerShell } from '../../components/buyer/BuyerShell';

export const BuyerPortal: React.FC = () => {
  return (
    <BuyerProvider>
      <BuyerShell />
    </BuyerProvider>
  );
};

export default BuyerPortal;
