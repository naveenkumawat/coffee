<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Realtime\RealtimeHealthService;
use Illuminate\Console\Command;

class RealtimeHealthCommand extends Command
{
    protected $signature = 'coffee:realtime-health
                            {--probe : Dispatch a minimal RealtimeConnectionProbe event}
                            {--user= : User id for --probe (defaults to first active user)}
                            {--metrics : Print recent recipient delay samples (computed, not stored)}
                            {--json : Emit machine-readable JSON}';

    protected $description = 'R1.7 realtime health/diagnostics (config, auth, optional probe/metrics)';

    public function handle(RealtimeHealthService $health): int
    {
        $report = $health->inspect();

        if ($this->option('probe')) {
            $userId = $this->option('user');
            $user = is_numeric($userId)
                ? User::query()->find((int) $userId)
                : null;
            $report['probe'] = $health->dispatchProbe($user);
            if (! ($report['probe']['ok'] ?? false)) {
                $report['ok'] = false;
            }
        }

        if ($this->option('metrics')) {
            $report['metrics_samples'] = $health->recentRecipientDelaySamples(5);
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ($report['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Coffee realtime health');
        $this->newLine();

        foreach ($report['checks'] as $check) {
            $mark = $check['ok'] ? '[ok]' : '[!!]';
            $this->line(sprintf('%s %-18s %s', $mark, $check['key'], $check['detail']));
        }

        $this->newLine();
        $this->line('Config snapshot (no secrets):');
        foreach ($report['config'] as $key => $value) {
            $this->line(sprintf('  %s=%s', $key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value));
        }

        if (isset($report['probe'])) {
            $this->newLine();
            $probe = $report['probe'];
            $this->line(sprintf(
                'Probe: %s — %s%s',
                ($probe['ok'] ?? false) ? 'dispatched' : 'failed',
                $probe['detail'] ?? '',
                isset($probe['probe_id']) && $probe['probe_id'] ? ' (id='.$probe['probe_id'].')' : '',
            ));
        }

        if (isset($report['metrics_samples'])) {
            $this->newLine();
            $this->line('Recent recipient delay samples (computed from timestamps):');
            foreach ($report['metrics_samples'] as $sample) {
                $this->line(sprintf(
                    '  #%d %s reminders=%d delivery=%s first_seen=%s ack=%s resolution=%s',
                    $sample['recipient_id'],
                    $sample['type'] ?? 'unknown',
                    $sample['reminder_count'],
                    $this->formatDelay($sample['delays']['delivery_delay_seconds'] ?? null),
                    $this->formatDelay($sample['delays']['first_seen_delay_seconds'] ?? null),
                    $this->formatDelay($sample['delays']['acknowledge_delay_seconds'] ?? null),
                    $this->formatDelay($sample['delays']['resolution_delay_seconds'] ?? null),
                ));
            }
        }

        $this->newLine();
        if ($report['ok']) {
            $this->info('Realtime configuration looks healthy. REST remains authoritative if Reverb is down.');

            return self::SUCCESS;
        }

        $this->error('One or more realtime checks failed. See docs/realtime-runbook.md.');

        return self::FAILURE;
    }

    protected function formatDelay(mixed $seconds): string
    {
        return $seconds === null ? '-' : ((string) $seconds).'s';
    }
}
