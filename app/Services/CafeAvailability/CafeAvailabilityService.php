<?php

namespace App\Services\CafeAvailability;

use App\Enums\CafeAvailabilityCode;
use App\Enums\CafeClosureType;
use App\Enums\WebsiteSettingKey;
use App\Exceptions\OrderSecurityException;
use App\Models\CafeClosure;
use App\Models\CafeOperatingHour;
use App\Repositories\WebsiteSetting\WebsiteSettingRepositoryInterface;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CafeAvailabilityService implements CafeAvailabilityServiceInterface
{
    public const PUBLIC_CACHE_KEY = 'cafe_availability:public';

    public function __construct(
        protected WebsiteSettingRepositoryInterface $settings,
    ) {}

    public function timezone(): string
    {
        $raw = $this->settings->keyedValues()->get(WebsiteSettingKey::BusinessTimezone->value);
        $timezone = is_string($raw) && filled(trim($raw)) ? trim($raw) : (string) config('coffee.timezone', 'Asia/Kolkata');

        try {
            CarbonImmutable::now($timezone);

            return $timezone;
        } catch (\Throwable) {
            return 'Asia/Kolkata';
        }
    }

    public function now(): CarbonInterface
    {
        return CarbonImmutable::now($this->timezone());
    }

    public function status(?CarbonInterface $at = null): CafeAvailabilityResult
    {
        $timezone = $this->timezone();
        $moment = $at !== null
            ? CarbonImmutable::parse($at)->timezone($timezone)
            : CarbonImmutable::now($timezone);

        $weeklyHours = $this->weeklySchedule();
        $todayHoursLabel = $this->hoursLabelForWeekday($moment->dayOfWeek, $weeklyHours);

        $manual = $this->evaluateManualOverride($moment);
        if ($manual !== null) {
            return $manual;
        }

        $closure = $this->findActiveClosure($moment);
        if ($closure !== null) {
            $nextOpen = $this->nextOpenAfter($closure->ends_at->timezone($timezone));
            $code = $closure->type === CafeClosureType::Holiday
                ? CafeAvailabilityCode::Holiday
                : CafeAvailabilityCode::ScheduledClosure;
            $message = filled($closure->customer_message)
                ? (string) $closure->customer_message
                : $this->defaultClosedMessage($code, $nextOpen);

            return new CafeAvailabilityResult(
                available: false,
                code: $code,
                message: $message,
                nextOpenAt: $nextOpen,
                reopensAt: $closure->ends_at->timezone($timezone),
                timezone: $timezone,
                weeklyHours: $weeklyHours,
                todayHoursLabel: $todayHoursLabel,
            );
        }

        if ($this->isWithinWeeklyHours($moment)) {
            return new CafeAvailabilityResult(
                available: true,
                code: CafeAvailabilityCode::Open,
                message: 'Ordering is open.',
                nextOpenAt: null,
                reopensAt: null,
                timezone: $timezone,
                weeklyHours: $weeklyHours,
                todayHoursLabel: $todayHoursLabel,
            );
        }

        // No weekly hours configured yet → do not lock production/dev checkouts by accident.
        if (! CafeOperatingHour::query()->exists()) {
            return new CafeAvailabilityResult(
                available: true,
                code: CafeAvailabilityCode::Open,
                message: 'Ordering is open.',
                nextOpenAt: null,
                reopensAt: null,
                timezone: $timezone,
                weeklyHours: $weeklyHours,
                todayHoursLabel: $todayHoursLabel,
            );
        }

        $nextOpen = $this->nextOpenAfter($moment);

        return new CafeAvailabilityResult(
            available: false,
            code: CafeAvailabilityCode::OutsideHours,
            message: $this->defaultClosedMessage(CafeAvailabilityCode::OutsideHours, $nextOpen),
            nextOpenAt: $nextOpen,
            reopensAt: $nextOpen,
            timezone: $timezone,
            weeklyHours: $weeklyHours,
            todayHoursLabel: $todayHoursLabel,
        );
    }

    public function assertOrderingAvailable(?CarbonInterface $at = null): void
    {
        $status = $this->status($at);

        if ($status->available) {
            return;
        }

        throw new OrderSecurityException(
            'cafe_closed',
            $status->message,
            errorKey: 'ordering',
        );
    }

    public function weeklySchedule(): array
    {
        $grouped = CafeOperatingHour::query()
            ->orderBy('weekday')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('weekday');

        $days = [];

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $intervals = ($grouped->get($weekday) ?? collect())
                ->map(fn (CafeOperatingHour $hour): array => [
                    'opens_at' => $hour->opensAtHm(),
                    'closes_at' => $hour->closesAtHm(),
                ])
                ->values()
                ->all();

            $days[] = [
                'weekday' => $weekday,
                'label' => CarbonImmutable::now()->startOfWeek(Carbon::SUNDAY)->addDays($weekday)->format('l'),
                'is_open' => $intervals !== [],
                'intervals' => $intervals,
            ];
        }

        return $days;
    }

    public function syncWeeklyHours(array $days): void
    {
        DB::transaction(function () use ($days): void {
            CafeOperatingHour::query()->delete();

            foreach ($days as $weekday => $intervals) {
                $weekday = (int) $weekday;

                if ($weekday < 0 || $weekday > 6) {
                    continue;
                }

                if (! is_array($intervals)) {
                    continue;
                }

                $sort = 0;

                foreach ($intervals as $interval) {
                    if (! is_array($interval)) {
                        continue;
                    }

                    $opens = $this->normalizeTime($interval['opens_at'] ?? null);
                    $closes = $this->normalizeTime($interval['closes_at'] ?? null);

                    if ($opens === null || $closes === null) {
                        continue;
                    }

                    if ($closes <= $opens) {
                        throw ValidationException::withMessages([
                            "days.$weekday" => 'Closing time must be after opening time for each interval.',
                        ]);
                    }

                    CafeOperatingHour::query()->create([
                        'weekday' => $weekday,
                        'opens_at' => $opens,
                        'closes_at' => $closes,
                        'sort_order' => $sort++,
                    ]);
                }
            }
        });

        $this->flushAvailabilityCache();
    }

    public function storeClosure(array $data): CafeClosure
    {
        $closure = CafeClosure::query()->create($this->prepareClosureAttributes($data));
        $this->flushAvailabilityCache();

        return $closure;
    }

    public function updateClosure(CafeClosure $closure, array $data): CafeClosure
    {
        $closure->fill($this->prepareClosureAttributes($data))->save();
        $this->flushAvailabilityCache();

        return $closure->fresh();
    }

    public function setClosureActive(CafeClosure $closure, bool $isActive): CafeClosure
    {
        $closure->forceFill(['is_active' => $isActive])->save();
        $this->flushAvailabilityCache();

        return $closure->fresh();
    }

    public function archiveClosure(CafeClosure $closure): void
    {
        $closure->forceFill(['is_active' => false])->save();
        $closure->delete();
        $this->flushAvailabilityCache();
    }

    public function closeOrdering(?CarbonInterface $until = null, ?string $customerMessage = null): void
    {
        $payload = [
            WebsiteSettingKey::OrderingManualClosed->value => '1',
            WebsiteSettingKey::OrderingManualClosedMessage->value => filled($customerMessage)
                ? trim($customerMessage)
                : null,
            WebsiteSettingKey::OrderingManualClosedUntil->value => $until !== null
                ? CarbonImmutable::parse($until)->timezone('UTC')->toIso8601String()
                : null,
        ];

        $this->settings->upsertValues($payload);
        $this->flushAvailabilityCache();
    }

    public function reopenOrdering(): void
    {
        $this->settings->upsertValues([
            WebsiteSettingKey::OrderingManualClosed->value => '0',
            WebsiteSettingKey::OrderingManualClosedUntil->value => null,
            WebsiteSettingKey::OrderingManualClosedMessage->value => null,
        ]);
        $this->flushAvailabilityCache();
    }

    public function flushAvailabilityCache(): void
    {
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }

    /**
     * Cached public snapshot for content/API (always flushed on schedule changes).
     */
    public function publicStatus(): CafeAvailabilityResult
    {
        /** @var array<string, mixed> $cached */
        $cached = Cache::remember(self::PUBLIC_CACHE_KEY, now()->addSeconds(30), function (): array {
            return $this->status()->toPublicArray();
        });

        return new CafeAvailabilityResult(
            available: (bool) ($cached['available'] ?? false),
            code: CafeAvailabilityCode::tryFrom((string) ($cached['code'] ?? '')) ?? CafeAvailabilityCode::OutsideHours,
            message: (string) ($cached['message'] ?? 'Ordering is currently closed.'),
            nextOpenAt: isset($cached['next_open_at']) && is_string($cached['next_open_at'])
                ? CarbonImmutable::parse($cached['next_open_at'])
                : null,
            reopensAt: isset($cached['reopens_at']) && is_string($cached['reopens_at'])
                ? CarbonImmutable::parse($cached['reopens_at'])
                : null,
            timezone: (string) ($cached['timezone'] ?? $this->timezone()),
            weeklyHours: is_array($cached['weekly_hours'] ?? null) ? $cached['weekly_hours'] : [],
            todayHoursLabel: isset($cached['today_hours']) && is_string($cached['today_hours'])
                ? $cached['today_hours']
                : null,
        );
    }

    protected function evaluateManualOverride(CarbonImmutable $moment): ?CafeAvailabilityResult
    {
        $values = $this->settings->keyedValues();
        $closed = in_array(
            strtolower(trim((string) ($values->get(WebsiteSettingKey::OrderingManualClosed->value) ?? ''))),
            ['1', 'true', 'yes', 'on'],
            true,
        );

        if (! $closed) {
            return null;
        }

        $untilRaw = $values->get(WebsiteSettingKey::OrderingManualClosedUntil->value);
        $until = null;

        if (filled($untilRaw)) {
            $until = CarbonImmutable::parse((string) $untilRaw)->timezone($moment->timezoneName);

            if ($moment->greaterThanOrEqualTo($until)) {
                return null;
            }
        }

        $customMessage = $this->filledString($values->get(WebsiteSettingKey::OrderingManualClosedMessage->value));
        $nextOpen = $until !== null ? $this->nextOpenAfter($until) : null;
        $message = $customMessage ?? ($until !== null
            ? $this->defaultClosedMessage(CafeAvailabilityCode::ManualClosed, $nextOpen)
            : 'Temporarily unavailable. Please check again later.');

        return new CafeAvailabilityResult(
            available: false,
            code: CafeAvailabilityCode::ManualClosed,
            message: $message,
            nextOpenAt: $until !== null ? $nextOpen : null,
            reopensAt: $until,
            timezone: $moment->timezoneName,
            weeklyHours: $this->weeklySchedule(),
            todayHoursLabel: $this->hoursLabelForWeekday($moment->dayOfWeek, $this->weeklySchedule()),
        );
    }

    protected function findActiveClosure(CarbonImmutable $moment): ?CafeClosure
    {
        $utcMoment = $moment->timezone('UTC');

        return CafeClosure::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', $utcMoment)
            ->where('ends_at', '>', $utcMoment)
            ->orderBy('starts_at')
            ->first();
    }

    protected function isWithinWeeklyHours(CarbonImmutable $moment): bool
    {
        $seconds = ($moment->hour * 3600) + ($moment->minute * 60) + $moment->second;

        $intervals = CafeOperatingHour::query()
            ->where('weekday', $moment->dayOfWeek)
            ->orderBy('sort_order')
            ->get();

        foreach ($intervals as $interval) {
            $open = $this->timeToSeconds((string) $interval->opens_at);
            $close = $this->timeToSeconds((string) $interval->closes_at);

            if ($seconds >= $open && $seconds < $close) {
                return true;
            }
        }

        return false;
    }

    protected function nextOpenAfter(CarbonInterface $from): ?CarbonImmutable
    {
        $timezone = $this->timezone();
        $fromLocal = CarbonImmutable::parse($from)->timezone($timezone);

        if ($this->isWithinWeeklyHours($fromLocal) && $this->findActiveClosure($fromLocal) === null) {
            return $fromLocal;
        }

        $schedule = collect($this->weeklySchedule())->keyBy('weekday');

        for ($dayOffset = 0; $dayOffset < 21; $dayOffset++) {
            $day = $fromLocal->startOfDay()->addDays($dayOffset);
            $daySchedule = $schedule->get($day->dayOfWeek);

            if (! is_array($daySchedule) || ! ($daySchedule['is_open'] ?? false)) {
                continue;
            }

            foreach ($daySchedule['intervals'] as $interval) {
                $candidate = $day->setTimeFromTimeString($interval['opens_at'].':00');

                if ($candidate->lessThanOrEqualTo($fromLocal)) {
                    continue;
                }

                $blocked = $this->findActiveClosure($candidate);

                if ($blocked !== null) {
                    return $this->nextOpenAfter($blocked->ends_at->timezone($timezone));
                }

                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  list<array{weekday: int, label: string, is_open: bool, intervals: list<array{opens_at: string, closes_at: string}>}>  $weeklyHours
     */
    protected function hoursLabelForWeekday(int $weekday, array $weeklyHours): ?string
    {
        foreach ($weeklyHours as $day) {
            if ((int) $day['weekday'] !== $weekday) {
                continue;
            }

            if (! $day['is_open'] || $day['intervals'] === []) {
                return 'Closed';
            }

            return collect($day['intervals'])
                ->map(fn (array $interval): string => $interval['opens_at'].' – '.$interval['closes_at'])
                ->implode(', ');
        }

        return 'Closed';
    }

    protected function defaultClosedMessage(CafeAvailabilityCode $code, ?CarbonInterface $nextOpen): string
    {
        if ($nextOpen === null) {
            return match ($code) {
                CafeAvailabilityCode::ManualClosed => 'Temporarily unavailable. Please check again later.',
                default => 'Ordering is currently closed.',
            };
        }

        $formatted = $this->formatCustomerTime($nextOpen);

        return match ($code) {
            CafeAvailabilityCode::Holiday => 'Holiday — ordering resumes '.$formatted.'.',
            CafeAvailabilityCode::ScheduledClosure => 'Temporarily unavailable until '.$formatted.'.',
            CafeAvailabilityCode::ManualClosed => 'Temporarily unavailable until '.$formatted.'.',
            CafeAvailabilityCode::OutsideHours => 'Ordering is currently closed. Opens '.$formatted.'.',
            default => 'Ordering is currently closed. Opens '.$formatted.'.',
        };
    }

    protected function formatCustomerTime(CarbonInterface $at): string
    {
        $local = CarbonImmutable::parse($at)->timezone($this->timezone());
        $today = $this->now()->startOfDay();
        $target = $local->startOfDay();

        if ($target->equalTo($today)) {
            return 'today at '.$local->format('g:i A');
        }

        if ($target->equalTo($today->addDay())) {
            return 'tomorrow at '.$local->format('g:i A');
        }

        return $local->format('l').' at '.$local->format('g:i A');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareClosureAttributes(array $data): array
    {
        $starts = CarbonImmutable::parse((string) $data['starts_at'], $this->timezone())->timezone('UTC');
        $ends = CarbonImmutable::parse((string) $data['ends_at'], $this->timezone())->timezone('UTC');

        if ($ends->lessThanOrEqualTo($starts)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The end time must be after the start time.',
            ]);
        }

        $type = CafeClosureType::tryFrom((string) ($data['type'] ?? '')) ?? CafeClosureType::Other;

        return [
            'title' => trim((string) $data['title']),
            'type' => $type->value,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'customer_message' => $this->filledString($data['customer_message'] ?? null),
            'internal_note' => $this->filledString($data['internal_note'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    protected function normalizeTime(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', trim($value))) {
            return null;
        }

        $parts = explode(':', trim($value));

        return sprintf('%02d:%02d:00', (int) $parts[0], (int) $parts[1]);
    }

    protected function timeToSeconds(string $time): int
    {
        $parts = array_map('intval', explode(':', substr($time, 0, 8)));

        return (($parts[0] ?? 0) * 3600) + (($parts[1] ?? 0) * 60) + ($parts[2] ?? 0);
    }

    protected function filledString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
