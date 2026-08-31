interface ServiceWorkerUpdateBannerProps {
  visible: boolean;
  onRefresh: () => void;
  onDismiss: () => void;
}

export function ServiceWorkerUpdateBanner({ visible, onRefresh, onDismiss }: ServiceWorkerUpdateBannerProps) {
  if (!visible) {
    return null;
  }

  return (
    <div className="sw-update-banner" role="status" aria-live="polite">
      <p>A new The88Coffees update is ready.</p>
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
