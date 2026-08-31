export interface RatingSummary {
  average: number | null;
  count: number;
  distribution?: Record<string, number> | Record<number, number>;
}

export interface MyProductRating {
  id: number;
  rating: number;
  review: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface PublicProductReview {
  id: number;
  rating: number;
  review: string | null;
  customer_display_name: string;
  is_verified_purchase: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface ProductRatingsPayload {
  rating_summary: RatingSummary;
  my_rating: MyProductRating | null;
  can_rate: boolean;
  reviews: PublicProductReview[];
  meta?: {
    pagination?: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
  };
}

export interface ProductRatingMutationPayload {
  my_rating: MyProductRating | null;
  rating_summary: RatingSummary;
  can_rate: boolean;
}
