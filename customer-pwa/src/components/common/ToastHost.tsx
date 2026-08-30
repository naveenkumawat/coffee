import { useToastStore } from '../../stores/toastStore';

interface ToastHostProps {
  elevateForStickyCta?: boolean;
}

export function ToastHost({ elevateForStickyCta = false }: ToastHostProps) {
  const toasts = useToastStore((state) => state.toasts);
  const dismiss = useToastStore((state) => state.dismiss);

  if (toasts.length === 0) {
    return null;
  }

  return (
    <div
      className={`toast-host ${elevateForStickyCta ? 'has-sticky-cta' : ''}`}
      role="region"
      aria-label="Notifications"
      aria-live="polite"
    >
      {toasts.map((toast) => (
        <div key={toast.id} className={`toast-item is-${toast.variant}`} role="status">
          <p>{toast.message}</p>
          <button
            type="button"
            className="toast-dismiss"
            aria-label="Dismiss notification"
            onClick={() => dismiss(toast.id)}
          >
            <i className="bi bi-x-lg" aria-hidden="true"></i>
          </button>
        </div>
      ))}
    </div>
  );
}
