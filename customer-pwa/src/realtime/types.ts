export type RealtimeConnectionState =
  | 'idle'
  | 'connecting'
  | 'connected'
  | 'reconnecting'
  | 'reconnected'
  | 'disconnected'
  | 'failed'
  | 'offline';

export type RealtimeConnectionListener = (state: RealtimeConnectionState) => void;

export type RealtimeProbePayload = {
  probe_id: string;
  user_id: number;
};
