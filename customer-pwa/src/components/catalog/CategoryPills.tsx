import { ProductCategory } from '../../types/catalog';

interface CategoryPillsProps {
  categories: ProductCategory[];
  activeCategoryId: number | null;
  onSelect: (categoryId: number | null) => void;
}

export function CategoryPills({ categories, activeCategoryId, onSelect }: CategoryPillsProps) {
  return (
    <div className="category-pills">
      <button
        type="button"
        className={`category-pill ${activeCategoryId === null ? 'active' : ''}`}
        onClick={() => onSelect(null)}
      >
        All
      </button>
      {categories.map((category) => (
        <button
          type="button"
          key={category.id}
          className={`category-pill ${activeCategoryId === category.id ? 'active' : ''}`}
          onClick={() => onSelect(category.id)}
        >
          {category.name}
        </button>
      ))}
    </div>
  );
}
