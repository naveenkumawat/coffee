import { Product } from './catalog';

export interface HomeSection {
  id: number;
  title: string;
  subtitle: string | null;
  slug: string;
  products: Product[];
}

export interface HomePayload {
  sections: HomeSection[];
}
