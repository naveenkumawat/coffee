<?php

namespace App\Console\Commands;

use App\Services\Launch\LaunchReadinessServiceInterface;
use Illuminate\Console\Command;

class LaunchReadinessCommand extends Command
{
    protected $signature = 'coffee:launch-readiness {--json : Output machine-readable JSON}';

    protected $description = 'Read-only café launch readiness audit (blockers / required / optional). Does not mutate data.';

    public function handle(LaunchReadinessServiceInterface $readiness): int
    {
        $report = $readiness->evaluate();
        $payload = $report->toArray();

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report->hasBlockers() ? self::FAILURE : self::SUCCESS;
        }

        $summary = $payload['summary'];
        $this->info(sprintf(
            'Launch readiness · env=%s · blockers=%d · required=%d · products ready=%d/%d · active=%d',
            $summary['environment'] ?? 'unknown',
            $summary['blocker_count'] ?? 0,
            $summary['required_count'] ?? 0,
            $summary['products_ready'] ?? 0,
            $summary['products_total'] ?? 0,
            $summary['active_products'] ?? 0,
        ));

        $this->newLine();
        $this->line('Area status:');
        foreach ($payload['areas'] as $area) {
            $this->line(sprintf(
                '  [%s] %s — %s',
                strtoupper((string) $area['status']),
                $area['area'],
                $area['notes'],
            ));
        }

        if ($payload['blockers'] !== []) {
            $this->newLine();
            $this->error('BLOCKERS (must fix before production):');
            foreach ($payload['blockers'] as $finding) {
                $this->line('  - ['.$finding['code'].'] '.$finding['message']);
            }
        }

        if ($payload['required_before_production'] !== []) {
            $this->newLine();
            $this->warn('REQUIRED BEFORE PRODUCTION:');
            foreach ($payload['required_before_production'] as $finding) {
                $this->line('  - ['.$finding['code'].'] '.$finding['message']);
            }
        }

        if ($payload['optional_deferred'] !== []) {
            $this->newLine();
            $this->comment('OPTIONAL / DEFERRED:');
            foreach ($payload['optional_deferred'] as $finding) {
                $this->line('  - ['.$finding['code'].'] '.$finding['message']);
            }
        }

        $this->newLine();
        if ($report->hasBlockers()) {
            $this->error('Result: NOT READY (blocking findings present).');

            return self::FAILURE;
        }

        $this->info('Result: No blockers detected. Review required/optional items before go-live.');

        return self::SUCCESS;
    }
}
