import { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { selectCartEnabled, useContentStore } from '../stores/contentStore';

/**
 * Blocks retail cart and checkout when customer ordering is off.
 * Unknown capability stays open so a missing flag cannot trap the customer in a loading loop.
 */
export function RetailOrderingGate({ children }: { children: ReactNode }) {
  const cartEnabled = useContentStore((state) => selectCartEnabled(state.content, state.cartEnabled));

  if (!cartEnabled) {
    return <Navigate to="/menu" replace />;
  }

  return children;
}
