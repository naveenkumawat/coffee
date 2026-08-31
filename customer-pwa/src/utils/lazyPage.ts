import { ComponentType, lazy, LazyExoticComponent } from 'react';
import { tryRecoverFromChunkError } from './chunkRecovery';

/**
 * React.lazy wrapper that recovers once from stale Vite chunk URLs
 * (common after a rebuild while a tab stays open).
 */
export function lazyPage<T extends ComponentType<unknown>>(
  factory: () => Promise<{ default: T }>,
): LazyExoticComponent<T> {
  return lazy(async () => {
    try {
      return await factory();
    } catch (error) {
      if (tryRecoverFromChunkError(error)) {
        return new Promise(() => {
          // Stay pending until the recovery reload completes.
        });
      }

      throw error;
    }
  });
}
