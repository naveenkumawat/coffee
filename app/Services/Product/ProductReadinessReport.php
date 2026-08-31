<?php

namespace App\Services\Product;

/**
 * Launch-readiness evaluation for a single product (configuration vs inventory).
 */
final class ProductReadinessReport
{
    /**
     * @param  list<string>  $missing
     * @param  list<string>  $inventoryNotes
     */
    public function __construct(
        public readonly array $missing,
        public readonly array $inventoryNotes = [],
    ) {}

    public function isConfigurationComplete(): bool
    {
        return $this->missing === [];
    }

    public function isReady(): bool
    {
        return $this->isConfigurationComplete();
    }

    public function hasInventoryConcern(): bool
    {
        return $this->inventoryNotes !== [];
    }

    public function statusLabel(): string
    {
        return $this->isConfigurationComplete() ? 'Ready' : 'Incomplete';
    }

    /**
     * Operational availability note when configuration is complete but product is paused.
     */
    public function availabilityLabel(bool $isAvailable): ?string
    {
        if (! $this->isConfigurationComplete()) {
            return null;
        }

        if (! $isAvailable) {
            return 'Unavailable (paused)';
        }

        if ($this->hasInventoryConcern()) {
            return 'Stock concern';
        }

        return null;
    }
}
