<?php

namespace App\Services\Launch;

/**
 * @phpstan-type LaunchFinding array{code: string, severity: 'blocker'|'required'|'optional', message: string, area: string}
 * @phpstan-type LaunchAreaStatus array{area: string, status: 'ready'|'demo_only'|'missing_real_data'|'optional_deferred', notes: string}
 */
class LaunchReadinessReport
{
    /**
     * @param  list<LaunchFinding>  $findings
     * @param  list<LaunchAreaStatus>  $areas
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public array $findings,
        public array $areas,
        public array $summary,
    ) {}

    public function hasBlockers(): bool
    {
        return collect($this->findings)->contains(
            static fn (array $finding): bool => ($finding['severity'] ?? null) === 'blocker',
        );
    }

    /**
     * @return list<LaunchFinding>
     */
    public function blockers(): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (array $finding): bool => ($finding['severity'] ?? null) === 'blocker',
        ));
    }

    /**
     * @return list<LaunchFinding>
     */
    public function required(): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (array $finding): bool => ($finding['severity'] ?? null) === 'required',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => ! $this->hasBlockers(),
            'summary' => $this->summary,
            'areas' => $this->areas,
            'blockers' => $this->blockers(),
            'required_before_production' => $this->required(),
            'optional_deferred' => array_values(array_filter(
                $this->findings,
                static fn (array $finding): bool => ($finding['severity'] ?? null) === 'optional',
            )),
            'findings' => $this->findings,
        ];
    }
}
