import { ApiEnvelope, get } from './client';

export interface LoyaltyTransaction {
  id: number;
  type: string;
  label: string;
  points: number;
  description: string | null;
  occurred_at: string | null;
}

export interface LoyaltyRewardOption {
  id: number;
  name: string;
  description: string;
  reward_type: string;
  points_cost: number;
  eligible: boolean;
  unavailable_reason: string | null;
  preview_discount_amount: string;
  minimum_spend: string | null;
}

export interface LoyaltyPayload {
  available_points: number;
  lifetime_earned_points: number;
  lifetime_redeemed_points: number;
  lifetime_adjusted_points?: number;
  has_points_debt?: boolean;
  debt_message?: string | null;
  earning_enabled: boolean;
  redemption_enabled?: boolean;
  earning_explanation: string | null;
  recent_transactions: LoyaltyTransaction[];
}

export function fetchLoyalty(): Promise<ApiEnvelope<LoyaltyPayload>> {
  return get<ApiEnvelope<LoyaltyPayload>>('/account/loyalty');
}

export function fetchLoyaltyRewards(fulfilmentMethod?: string | null): Promise<ApiEnvelope<{ rewards: LoyaltyRewardOption[] }>> {
  const query = fulfilmentMethod ? `?fulfilment_method=${encodeURIComponent(fulfilmentMethod)}` : '';

  return get<ApiEnvelope<{ rewards: LoyaltyRewardOption[] }>>(`/account/loyalty/rewards${query}`);
}
