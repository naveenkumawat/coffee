<?php

namespace App\Console\Commands;

use App\Models\CustomerBehaviourEvent;
use App\Services\Behaviour\BehaviourEventServiceInterface;
use Illuminate\Console\Command;

class BehaviourEventsPruneCommand extends Command
{
    protected $signature = 'coffee:behaviour-events-prune
                            {--dry-run : Show how many rows would be deleted without deleting}
                            {--stats : Show current behaviour event diagnostics}';

    protected $description = 'Prune expired first-party customer behaviour events (does not touch orders or financial records)';

    public function handle(BehaviourEventServiceInterface $behaviour): int
    {
        if ($this->option('stats')) {
            $summary = $behaviour->diagnosticsSummary();
            $this->table(
                ['Metric', 'Value'],
                [
                    ['events', (string) $summary['events']],
                    ['visitor_claims', (string) $summary['visitors']],
                    ['oldest_occurred_at', (string) ($summary['oldest_occurred_at'] ?? '—')],
                    ['newest_occurred_at', (string) ($summary['newest_occurred_at'] ?? '—')],
                    ['enabled', $behaviour->isEnabled() ? 'yes' : 'no'],
                    ['retention_days', (string) config('coffee.behaviour.retention_days', 180)],
                ],
            );
        }

        if ($this->option('dry-run')) {
            $days = max(1, (int) config('coffee.behaviour.retention_days', 180));
            $count = CustomerBehaviourEvent::query()
                ->where('occurred_at', '<', now()->subDays($days))
                ->count();
            $this->info("Would delete {$count} behaviour event(s) older than {$days} day(s).");

            return self::SUCCESS;
        }

        $deleted = $behaviour->pruneExpired();
        $this->info("Deleted {$deleted} expired behaviour event(s).");

        return self::SUCCESS;
    }
}
