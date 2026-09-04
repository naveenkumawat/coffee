import { RealtimeConnectionState } from '../realtime/types';

export function realtimeStatusLabel(state: RealtimeConnectionState): string {
  if (state === 'connected' || state === 'reconnected') {
    return 'Realtime connected';
  }

  if (state === 'connecting' || state === 'reconnecting') {
    return 'Realtime reconnecting';
  }

  return 'Realtime offline';
}

export function realtimeStatusTone(
  state: RealtimeConnectionState,
): 'connected' | 'reconnecting' | 'offline' {
  if (state === 'connected' || state === 'reconnected') {
    return 'connected';
  }

  if (state === 'connecting' || state === 'reconnecting') {
    return 'reconnecting';
  }

  return 'offline';
}
