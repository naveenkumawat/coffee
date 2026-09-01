<?php

namespace App\Services\CafeAvailability;

use App\Models\CafeClosure;
use Carbon\CarbonInterface;

interface CafeAvailabilityServiceInterface
{
    public function timezone(): string;

    public function now(): CarbonInterface;

    public function status(?CarbonInterface $at = null): CafeAvailabilityResult;

    /**
     * Short-lived public snapshot (flushed when schedule changes).
     */
    public function publicStatus(): CafeAvailabilityResult;

    public function assertOrderingAvailable(?CarbonInterface $at = null): void;

    /**
     * @return list<array{weekday: int, label: string, is_open: bool, intervals: list<array{opens_at: string, closes_at: string}>}>
     */
    public function weeklySchedule(): array;

    /**
     * @param  array<int, list<array{opens_at: string, closes_at: string}>>  $days
     */
    public function syncWeeklyHours(array $days): void;

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeClosure(array $data): CafeClosure;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateClosure(CafeClosure $closure, array $data): CafeClosure;

    public function setClosureActive(CafeClosure $closure, bool $isActive): CafeClosure;

    public function archiveClosure(CafeClosure $closure): void;

    public function closeOrdering(?CarbonInterface $until = null, ?string $customerMessage = null): void;

    public function reopenOrdering(): void;

    public function flushAvailabilityCache(): void;
}
