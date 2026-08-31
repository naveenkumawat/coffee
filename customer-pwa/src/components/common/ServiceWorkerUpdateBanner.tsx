import { DEFAULT_BRAND_NAME } from '../../types/content';

interface ServiceWorkerUpdateBannerProps {
  visible: boolean;
  onRefresh: () => void;
  onDismiss: () => void;
  brandName?: string;
}

export function ServiceWorkerUpdateBanner({
  visible,
  onRefresh,
  onDismiss,
  brandName = DEFAULT_BRAND_NAME,
}: ServiceWorkerUpdateBannerProps) {
  if (!visible) {
    return null;
  }

  return (
    <div className="sw-update-banner" role="status" aria-live="polite">
      <p>A new {brandName} update is ready.</p>
      <div className="sw-update-actions">
        <button type="button" className="btn btn-primary btn-sm rounded-pill" onClick={onRefresh}>
          Refresh
        </button>
        <button type="button" className="btn btn-outline-dark btn-sm rounded-pill" onClick={onDismiss}>
          Later
        </button>
      </div>
    </div>
  );
}
