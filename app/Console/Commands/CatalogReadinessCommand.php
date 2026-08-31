<?php

namespace App\Console\Commands;

use App\Services\Product\ProductReadinessServiceInterface;
use Illuminate\Console\Command;

class CatalogReadinessCommand extends Command
{
    protected $signature = 'coffee:catalog-readiness';

    protected $description = 'Report product launch readiness (configuration incomplete vs ready)';

    public function handle(ProductReadinessServiceInterface $readiness): int
    {
        $summary = $readiness->catalogSummary();

        $this->info(sprintf(
            'Products: %d | Ready: %d | Incomplete: %d',
            $summary['total'],
            $summary['ready'],
            $summary['incomplete'],
        ));

        if ($summary['items'] === []) {
            $this->newLine();
            $this->info('All products are launch-ready.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Incomplete:');

        foreach ($summary['items'] as $item) {
            $this->line($item['name']);
            foreach ($item['missing'] as $missing) {
                $this->line("  - {$missing}");
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
