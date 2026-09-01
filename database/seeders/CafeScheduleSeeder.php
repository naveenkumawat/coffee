<?php

namespace Database\Seeders;

use App\Enums\CafeClosureType;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeClosure;
use App\Models\CafeOperatingHour;
use App\Models\WebsiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class CafeScheduleSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::BusinessTimezone->value],
            [
                'section' => WebsiteSettingKey::BusinessTimezone->section(),
                'value_type' => WebsiteSettingKey::BusinessTimezone->valueType(),
                'value' => 'Asia/Kolkata',
            ],
        );

        CafeOperatingHour::query()->delete();

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            CafeOperatingHour::query()->create([
                'weekday' => $weekday,
                'opens_at' => '08:00:00',
                'closes_at' => '22:00:00',
                'sort_order' => 0,
            ]);
        }

        $timezone = 'Asia/Kolkata';
        $futureStart = CarbonImmutable::now($timezone)->addMonths(2)->startOfDay();
        $pastStart = CarbonImmutable::now($timezone)->subDays(14)->setTime(14, 0);

        CafeClosure::query()->updateOrCreate(
            ['title' => 'DEMO — Future festival holiday'],
            [
                'type' => CafeClosureType::Holiday,
                'starts_at' => $futureStart->timezone('UTC'),
                'ends_at' => $futureStart->addDay()->endOfDay()->timezone('UTC'),
                'customer_message' => 'Closed for the festival holiday.',
                'internal_note' => 'DEMO ONLY — future holiday for schedule UI.',
                'is_active' => true,
            ],
        );

        CafeClosure::query()->updateOrCreate(
            ['title' => 'DEMO — Past maintenance'],
            [
                'type' => CafeClosureType::Maintenance,
                'starts_at' => $pastStart->timezone('UTC'),
                'ends_at' => $pastStart->setTime(17, 0)->timezone('UTC'),
                'customer_message' => 'Closed for maintenance.',
                'internal_note' => 'DEMO ONLY — past closure should not block ordering.',
                'is_active' => true,
            ],
        );
    }
}
