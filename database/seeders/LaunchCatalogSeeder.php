<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Placeholder for a future idempotent production catalog import.
 *
 * Do NOT invent café products here. Complete docs/launch-menu.md with café-confirmed
 * categories, products, variants, and prices first, then implement import against that
 * source (prefer Administrator entry or a data-driven seeder with stable slugs).
 *
 * Until then this class intentionally refuses to run.
 */
class LaunchCatalogSeeder extends Seeder
{
    public function run(): void
    {
        throw new RuntimeException(
            'LaunchCatalogSeeder refused: docs/launch-menu.md has no confirmed café menu. '.
            'Enter real catalog via Administrator after decisions are recorded — do not invent products, prices, or recipes.',
        );
    }
}
