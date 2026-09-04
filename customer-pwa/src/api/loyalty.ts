import { ApiEnvelope, get } from './client';

export interface LoyaltyTransaction {
  id: number;
  type: string;
  label: string;
  points: number;
  description: string | null;
  occurred_at: string | null;
}

export interface LoyaltyPayload {
  available_points: number;
  lifetime_earned_points: number;
  lifetime_redeemed_points: number;
  earning_enabled: boolean;
  earning_explanation: string | null;
  recent_transactions: LoyaltyTransaction[];
}

export function fetchLoyalty(): Promise<ApiEnvelope<LoyaltyPayload>> {
  return get<ApiEnvelope<LoyaltyPayload>>('/account/loyalty');
}
