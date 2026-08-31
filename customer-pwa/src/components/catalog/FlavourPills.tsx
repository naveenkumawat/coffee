import { ProductFlavour } from '../../types/catalog';

interface FlavourPillsProps {
  flavours: ProductFlavour[];
  selectedFlavourIds: number[];
  onToggle: (flavourId: number | null) => void;
}

export function FlavourPills({ flavours, selectedFlavourIds, onToggle }: FlavourPillsProps) {
  if (flavours.length === 0) {
    return null;
  }

  const noneSelected = selectedFlavourIds.length === 0;

  return (
    <div className="filter-rail-pills" role="group" aria-label="Flavours">
      <button
        type="button"
        className={`filter-chip-pill ${noneSelected ? 'is-active' : ''}`}
        aria-pressed={noneSelected}
        onClick={() => onToggle(null)}
      >
        Any flavour
      </button>
      {flavours.map((flavour) => {
        const isActive = selectedFlavourIds.includes(flavour.id);

        return (
          <button
            type="button"
            key={flavour.id}
            className={`filter-chip-pill ${isActive ? 'is-active' : ''}`}
            aria-pressed={isActive}
            onClick={() => onToggle(flavour.id)}
          >
            {flavour.name}
          </button>
        );
      })}
    </div>
  );
}
