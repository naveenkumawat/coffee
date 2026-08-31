import { ProductCategory } from '../../types/catalog';

interface CategoryPillsProps {
  categories: ProductCategory[];
  selectedCategoryIds: number[];
  onToggle: (categoryId: number | null) => void;
}

export function CategoryPills({ categories, selectedCategoryIds, onToggle }: CategoryPillsProps) {
  const noneSelected = selectedCategoryIds.length === 0;

  return (
    <div className="filter-rail-pills" role="group" aria-label="Categories">
      <button
        type="button"
        className={`filter-chip-pill ${noneSelected ? 'is-active' : ''}`}
        aria-pressed={noneSelected}
        onClick={() => onToggle(null)}
      >
        All
      </button>
      {categories.map((category) => {
        const isActive = selectedCategoryIds.includes(category.id);

        return (
          <button
            type="button"
            key={category.id}
            className={`filter-chip-pill ${isActive ? 'is-active' : ''}`}
            aria-pressed={isActive}
            onClick={() => onToggle(category.id)}
          >
            {category.name}
          </button>
        );
      })}
    </div>
  );
}
