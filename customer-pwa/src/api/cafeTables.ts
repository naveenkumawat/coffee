import { ApiEnvelope, get } from './client';

export interface CafeTableOption {
  id: number;
  code: string;
  name: string | null;
  label: string;
}

export function fetchCafeTables(): Promise<ApiEnvelope<CafeTableOption[]>> {
  return get<ApiEnvelope<CafeTableOption[]>>('/cafe-tables');
}
