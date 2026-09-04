import { useMemo } from 'react';
import { addDiningDraft } from '../api/dining';
import { ApiError } from '../api/client';
import { ProductOrderHandler } from '../components/catalog/ProductOrderControl';
import { useToastStore } from '../stores/toastStore';
import {
  diningDraftItemCount,
  isDiningOrderingMode,
  writeOrderingContext,
} from '../utils/orderingContext';
import { useOrderingContext } from './useOrderingContext';

/**
 * When ordering mode is Dining, route ProductOrderControl adds into the dining draft.
 * Takeaway mode leaves orderHandler undefined so retail cartStore is used.
 */
export function useOrderingAddHandler(): {
  orderHandler?: ProductOrderHandler;
  sheetCtaLabel?: string;
} {
  const context = useOrderingContext();
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);
  const diningMode = isDiningOrderingMode(context);
  const sessionId = context.diningSession?.diningSessionId;

  const orderHandler = useMemo<ProductOrderHandler | undefined>(() => {
    if (!diningMode || !sessionId) {
      return undefined;
    }

    return {
      add: async (payload) => {
        try {
          const response = await addDiningDraft(sessionId, payload);
          writeOrderingContext({
            mode: 'dining',
            diningSession: {
              diningSessionId: sessionId,
              tableLabel: response.data.table.label,
              draftItemCount: diningDraftItemCount(response.data.drafts),
            },
          });
          toastSuccess('Added to your next order');
        } catch (error) {
          toastError(error instanceof ApiError ? error.message : 'Unable to add item.');
          throw error;
        }
      },
    };
  }, [diningMode, sessionId, toastError, toastSuccess]);

  return {
    orderHandler,
    sheetCtaLabel: orderHandler ? 'Add to order' : undefined,
  };
}
