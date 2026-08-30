import { ProductFlavour } from '../../types/catalog';

interface FlavourPillsProps {
  flavours: ProductFlavour[];
  activeFlavourId: number | null;
  onSelect: (flavourId: number | null) => void;
}

export function FlavourPills({ flavours, activeFlavourId, onSelect }: FlavourPillsProps) {
  if (flavours.length === 0) {
    return null;
  }

  return (
    <div className="category-pills" aria-label="Flavour filters">
      <button
        type="button"
        className={`category-pill ${activeFlavourId === null ? 'active' : ''}`}
        onClick={() => onSelect(null)}
      >
        Any flavour
      </button>
      {flavours.map((flavour) => (
        <button
          type="button"
          key={flavour.id}
          className={`category-pill ${activeFlavourId === flavour.id ? 'active' : ''}`}
          onClick={() => onSelect(flavour.id)}
        >
          {flavour.name}
        </button>
      ))}
    </div>
  );
}
