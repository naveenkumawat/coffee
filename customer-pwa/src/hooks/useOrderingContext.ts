import { useSyncExternalStore } from 'react';
import {
  OrderingContext,
  RETAIL_ORDERING_CONTEXT,
  readOrderingContext,
  subscribeOrderingContext,
} from '../utils/orderingContext';

/**
 * Live ordering context for navigation/shell (dining vs retail).
 * getSnapshot must return a cached reference when data is unchanged —
 * otherwise React enters an infinite re-render loop.
 */
export function useOrderingContext(): OrderingContext {
  return useSyncExternalStore(
    subscribeOrderingContext,
    readOrderingContext,
    () => RETAIL_ORDERING_CONTEXT,
  );
}
