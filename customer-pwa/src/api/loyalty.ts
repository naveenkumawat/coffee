import { ApiEnvelope, get } from './client';

export type LoyaltyRewardState = 'available' | 'locked' | 'limit_reached' | 'scheduled' | 'debt';

export interface LoyaltyTransaction {
  id: number;
  type: string;
  label: string;
  points: number;
  description: string | null;
  order_number?: string | null;
  occurred_at: string | null;
}

export interface LoyaltyRewardOption {
  id: number;
  name: string;
  description: string;
  reward_type: string;
  points_cost: number;
  eligible: boolean;
  state: LoyaltyRewardState;
  unavailable_reason: string | null;
  unavailable_message: string | null;
  points_needed: number;
  preview_discount_amount: string;
  benefit_label: string;
  minimum_spend: string | null;
  starts_at: string | null;
  ends_at: string | null;
  image_url: string | null;
}

export type LoyaltyNextRewardState = 'progress' | 'ready' | 'debt' | 'none' | 'disabled' | 'locked';

export interface LoyaltyNextReward {
  state: LoyaltyNextRewardState;
  reward_id: number | null;
  name: string | null;
  points_cost: number | null;
  points_have: number;
  points_needed: number | null;
  progress_percent: number;
  message: string;
}

export interface LoyaltyRecentlyRedeemed {
  transaction_id: number;
  reward_id: number | null;
  name: string;
  points: number;
  order_number: string | null;
  occurred_at: string | null;
}

export interface LoyaltyPersonalisationSummary {
  available_points: number;
  has_points_debt: boolean;
  reward_available: boolean;
  nearest_reward_id: number | null;
  nearest_reward_progress_percent: number | null;
  recently_redeemed: boolean;
}

export interface LoyaltyPayload {
  available_points: number;
  display_available_points: number;
  lifetime_earned_points: number;
  lifetime_redeemed_points: number;
  lifetime_adjusted_points?: number;
  has_points_debt?: boolean;
  debt_message?: string | null;
  debt_explanation?: string | null;
  earning_enabled: boolean;
  redemption_enabled?: boolean;
  earning_explanation: string | null;
  recent_transactions: LoyaltyTransaction[];
  rewards?: LoyaltyRewardOption[];
  available_now?: LoyaltyRewardOption[];
  locked?: LoyaltyRewardOption[];
  recently_redeemed?: LoyaltyRecentlyRedeemed[];
  next_reward?: LoyaltyNextReward | null;
  personalisation_summary?: LoyaltyPersonalisationSummary;
}

export function fetchLoyalty(): Promise<ApiEnvelope<LoyaltyPayload>> {
  return get<ApiEnvelope<LoyaltyPayload>>('/account/loyalty');
}

export function fetchLoyaltyRewards(fulfilmentMethod?: string | null): Promise<ApiEnvelope<LoyaltyPayload>> {
  const query = fulfilmentMethod ? `?fulfilment_method=${encodeURIComponent(fulfilmentMethod)}` : '';

  return get<ApiEnvelope<LoyaltyPayload>>(`/account/loyalty/rewards${query}`);
}
