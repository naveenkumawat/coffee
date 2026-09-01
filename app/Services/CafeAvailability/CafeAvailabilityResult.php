<?php

namespace App\Services\CafeAvailability;

use App\Enums\CafeAvailabilityCode;
use Carbon\CarbonInterface;

readonly class CafeAvailabilityResult
{
    /**
     * @param  list<array{weekday: int, label: string, is_open: bool, intervals: list<array{opens_at: string, closes_at: string}>}>  $weeklyHours
     */
    public function __construct(
        public bool $available,
        public CafeAvailabilityCode $code,
        public string $message,
        public ?CarbonInterface $nextOpenAt,
        public ?CarbonInterface $reopensAt,
        public string $timezone,
        public array $weeklyHours = [],
        public ?string $todayHoursLabel = null,
    ) {}

    /**
     * Customer-safe public payload.
     *
     * @return array{
     *     available: bool,
     *     code: string,
     *     message: string,
     *     next_open_at: ?string,
     *     reopens_at: ?string,
     *     timezone: string,
     *     today_hours: ?string,
     *     weekly_hours: list<array{weekday: int, label: string, is_open: bool, intervals: list<array{opens_at: string, closes_at: string}>}>
     * }
     */
    public function toPublicArray(): array
    {
        return [
            'available' => $this->available,
            'code' => $this->code->value,
            'message' => $this->message,
            'next_open_at' => $this->nextOpenAt?->toIso8601String(),
            'reopens_at' => $this->reopensAt?->toIso8601String(),
            'timezone' => $this->timezone,
            'today_hours' => $this->todayHoursLabel,
            'weekly_hours' => $this->weeklyHours,
        ];
    }
}
