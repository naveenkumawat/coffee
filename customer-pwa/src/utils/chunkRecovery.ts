const RECOVERY_KEY_PREFIX = 'coffee:chunk-recovery:';

export function getAppBuildId(): string {
  return import.meta.env.VITE_APP_BUILD_ID || 'dev';
}

function recoveryStorageKey(): string {
  return `${RECOVERY_KEY_PREFIX}${getAppBuildId()}`;
}

export function isChunkLoadError(error: unknown): boolean {
  const message = error instanceof Error
    ? error.message
    : typeof error === 'string'
      ? error
      : '';

  return /Failed to fetch dynamically imported module|Importing a module script failed|error loading dynamically imported module|Loading chunk [\w-]+ failed|ChunkLoadError/i.test(
    message,
  );
}

export function clearChunkRecoveryFlag(): void {
  try {
    sessionStorage.removeItem(recoveryStorageKey());
  } catch {
    // Ignore quota / private-mode failures.
  }
}

/**
 * One automatic recovery reload per build id for stale lazy chunks.
 * Returns true when a reload was started (caller should not render error UI).
 */
export function tryRecoverFromChunkError(error: unknown): boolean {
  if (!isChunkLoadError(error)) {
    return false;
  }

  if (typeof navigator !== 'undefined' && !navigator.onLine) {
    return false;
  }

  try {
    if (sessionStorage.getItem(recoveryStorageKey()) === '1') {
      return false;
    }

    sessionStorage.setItem(recoveryStorageKey(), '1');
  } catch {
    return false;
  }

  void prepareFreshLoad().finally(() => {
    window.location.reload();
  });

  return true;
}

async function prepareFreshLoad(): Promise<void> {
  try {
    if ('caches' in window) {
      const keys = await caches.keys();
      await Promise.all(
        keys
          .filter((key) => key.startsWith('coffee-shell-'))
          .map((key) => caches.delete(key)),
      );
    }
  } catch {
    // Continue to reload even if cache cleanup fails.
  }

  try {
    if ('serviceWorker' in navigator) {
      const registrations = await navigator.serviceWorker.getRegistrations();
      await Promise.all(registrations.map((registration) => registration.update()));
    }
  } catch {
    // Ignore SW update failures; reload still helps when HTML is stale.
  }
}
