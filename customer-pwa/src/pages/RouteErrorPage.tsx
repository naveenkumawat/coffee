import { useEffect, useState } from 'react';
import { isRouteErrorResponse, useRouteError } from 'react-router-dom';
import { BrandLogo, BRAND_DISPLAY_NAME } from '../components/common/BrandLogo';
import { isChunkLoadError, tryRecoverFromChunkError } from '../utils/chunkRecovery';

function readErrorMessage(error: unknown): string {
  if (isRouteErrorResponse(error)) {
    return typeof error.data === 'string' ? error.data : error.statusText;
  }

  if (error instanceof Error) {
    return error.message;
  }

  return '';
}

/**
 * Customer-facing route failure UI (never shows stack traces or asset paths).
 */
export function RouteErrorPage() {
  const error = useRouteError();
  const offline = typeof navigator !== 'undefined' && !navigator.onLine;
  const chunkError = isChunkLoadError(error) || /dynamically imported module|Loading chunk/i.test(readErrorMessage(error));
  const [recovering, setRecovering] = useState(false);

  useEffect(() => {
    if (!chunkError || offline) {
      return;
    }

    if (tryRecoverFromChunkError(error)) {
      setRecovering(true);
    }
  }, [chunkError, error, offline]);

  useEffect(() => {
    if (!import.meta.env.DEV || !error) {
      return;
    }

    // Keep the friendly fallback, but never hide the real failure in development.
    console.error('[RouteErrorPage]', error);
  }, [error]);

  if (recovering) {
    return (
      <div className="page-container route-error-page">
        <BrandLogo size="lg" />
        <section className="state-card" aria-live="polite">
          <span className="state-icon">
            <i className="bi bi-arrow-repeat" aria-hidden="true"></i>
          </span>
          <h1>Refreshing {BRAND_DISPLAY_NAME}</h1>
          <p>Loading the latest version…</p>
        </section>
      </div>
    );
  }

  const title = offline
    ? 'You’re offline'
    : chunkError
      ? 'Something changed while the app was open.'
      : 'Something went wrong';

  const description = offline
    ? 'Reconnect, then refresh to continue browsing the menu.'
    : chunkError
      ? 'Refresh to continue with the latest experience.'
      : 'Please refresh the page or go home to keep ordering.';

  return (
    <div className="page-container route-error-page">
      <BrandLogo size="lg" />
      <section className="state-card state-card-error" role="alert">
        <span className="state-icon">
          <i className={`bi ${offline ? 'bi-wifi-off' : 'bi-exclamation-octagon'}`} aria-hidden="true"></i>
        </span>
        <h1>{title}</h1>
        <p>{description}</p>
        <div className="route-error-actions">
          <button
            type="button"
            className="btn btn-primary rounded-pill px-4"
            onClick={() => window.location.reload()}
          >
            Refresh
          </button>
          <a className="btn btn-outline-dark rounded-pill px-4" href="/">
            Go Home
          </a>
        </div>
      </section>
    </div>
  );
}
