import { useSyncExternalStore } from 'react';
import {
  OrderingContext,
  readOrderingContext,
  subscribeOrderingContext,
} from '../utils/orderingContext';

function getSnapshot(): OrderingContext {
  return readOrderingContext();
}

function getServerSnapshot(): OrderingContext {
  return { type: 'retail' };
}

/** Live ordering context for navigation/shell (dining vs retail). */
export function useOrderingContext(): OrderingContext {
  return useSyncExternalStore(subscribeOrderingContext, getSnapshot, getServerSnapshot);
}
