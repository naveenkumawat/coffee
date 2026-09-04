export type CartAttributionSourceType = 'recommendation' | 'campaign';

export interface CartAttributionPayload {
  source_type: CartAttributionSourceType;
  source_id?: number | null;
  request_id: string;
  strategy?: string;
  reason?: string;
  placement?: string;
  context?: string;
}

const STORAGE_KEY = 'coffee.pending-attribution.v1';

type StashMap = Record<string, CartAttributionPayload>;

function readMap(): StashMap {
  try {
    const raw = window.sessionStorage.getItem(STORAGE_KEY);

    if (!raw) {
      return {};
    }

    const parsed = JSON.parse(raw) as StashMap;

    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch {
    return {};
  }
}

function writeMap(map: StashMap): void {
  window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(map));
}

export function stashCartAttribution(productId: number, attribution: CartAttributionPayload): void {
  if (!Number.isFinite(productId) || productId <= 0 || !attribution.request_id) {
    return;
  }

  const map = readMap();
  map[String(productId)] = attribution;
  writeMap(map);
}

export function peekCartAttribution(productId: number): CartAttributionPayload | null {
  return readMap()[String(productId)] ?? null;
}

export function takeCartAttribution(productId: number): CartAttributionPayload | null {
  const map = readMap();
  const key = String(productId);
  const value = map[key] ?? null;

  if (value) {
    delete map[key];
    writeMap(map);
  }

  return value;
}
