import { Product, ProductCategory } from '../types/catalog';

export interface MenuProductGroup {
  categoryId: number | null;
  categoryName: string;
  products: Product[];
}

/**
 * Group the current product result page by category.
 * Category order follows the catalog categories list (backend sort).
 * Product order within each group is preserved from the API response.
 * Does not fetch extra pages — callers should pass only the loaded page.
 */
export function groupProductsByCategory(
  products: Product[],
  categories: ProductCategory[],
): MenuProductGroup[] {
  const productsByCategoryId = new Map<number, Product[]>();
  const uncategorized: Product[] = [];
  const seenProductIds = new Set<number>();

  for (const product of products) {
    if (seenProductIds.has(product.id)) {
      continue;
    }

    seenProductIds.add(product.id);

    const categoryId = product.category?.id;

    if (!categoryId) {
      uncategorized.push(product);
      continue;
    }

    const bucket = productsByCategoryId.get(categoryId);

    if (bucket) {
      bucket.push(product);
    } else {
      productsByCategoryId.set(categoryId, [product]);
    }
  }

  const groups: MenuProductGroup[] = [];

  for (const category of categories) {
    const groupedProducts = productsByCategoryId.get(category.id);

    if (!groupedProducts?.length) {
      continue;
    }

    groups.push({
      categoryId: category.id,
      categoryName: category.name,
      products: groupedProducts,
    });
    productsByCategoryId.delete(category.id);
  }

  for (const [categoryId, groupedProducts] of productsByCategoryId) {
    if (!groupedProducts.length) {
      continue;
    }

    groups.push({
      categoryId,
      categoryName: groupedProducts[0]?.category?.name?.trim() || 'Other',
      products: groupedProducts,
    });
  }

  if (uncategorized.length > 0) {
    groups.push({
      categoryId: null,
      categoryName: 'Other',
      products: uncategorized,
    });
  }

  return groups;
}
