import { RecommendationReason } from '../api/recommendations';

const LABELS: Record<string, string> = {
  buy_again: 'Buy again',
  favourite: 'From your favourites',
  because_you_viewed: 'Because you viewed',
  based_on_your_interests: 'Picked for you',
  similar_product: 'You may also like',
  frequently_bought_together: 'Frequently bought together',
  trending: 'Trending now',
  popular: 'Popular picks',
  bestseller: 'Customer favourites',
  new_arrival: 'New on the menu',
  featured: 'Featured',
  complete_your_order: 'Complete your order',
};

export function recommendationReasonLabel(reason: RecommendationReason | string): string {
  return LABELS[reason] ?? 'Recommended for you';
}
