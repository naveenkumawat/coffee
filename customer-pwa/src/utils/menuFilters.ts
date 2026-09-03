import { Product } from '../types/catalog';

export interface MenuProductFilters {
  search?: string;
  categoryIds?: number[];
  flavourIds?: number[];
}

/**
 * Client-side menu filters over the full cached catalogue.
 * Category/flavour multi-select is OR within each facet and AND across facets.
 */
export function filterMenuProducts(products: Product[], filters: MenuProductFilters): Product[] {
  const search = filters.search?.trim().toLowerCase() ?? '';
  const categoryIds = (filters.categoryIds ?? []).filter((id) => id > 0);
  const flavourIds = (filters.flavourIds ?? []).filter((id) => id > 0);

  return products.filter((product) => {
    if (categoryIds.length > 0) {
      const categoryId = product.category?.id;

      if (!categoryId || !categoryIds.includes(categoryId)) {
        return false;
      }
    }

    if (flavourIds.length > 0) {
      const productFlavourIds = product.flavours.map((flavour) => flavour.id);
      const matchesFlavour = flavourIds.some((id) => productFlavourIds.includes(id));

      if (!matchesFlavour) {
        return false;
      }
    }

    if (!search) {
      return true;
    }

    const haystack = [
      product.name,
      product.short_description,
      product.description,
      product.customer_ingredient_summary,
      product.category?.name,
      ...product.flavours.map((flavour) => flavour.name),
      ...(Array.isArray(product.tags) ? product.tags : []).map((tag) => tag.label),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return haystack.includes(search);
  });
}
