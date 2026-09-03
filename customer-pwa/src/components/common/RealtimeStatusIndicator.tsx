import { RealtimeConnectionState } from '../../realtime/types';

interface RealtimeStatusIndicatorProps {
  state: RealtimeConnectionState;
}

/** Subtle connection chip — hidden when live or intentionally offline/idle. */
export function RealtimeStatusIndicator({ state }: RealtimeStatusIndicatorProps) {
  if (state === 'connected' || state === 'idle' || state === 'offline' || state === 'reconnected') {
    return null;
  }

  const label =
    state === 'connecting' || state === 'reconnecting'
      ? 'Reconnecting…'
      : 'Realtime offline';

  return (
    <div
      className={`realtime-status-indicator is-${state}`}
      role="status"
      aria-live="polite"
    >
      {label}
    </div>
  );
}
