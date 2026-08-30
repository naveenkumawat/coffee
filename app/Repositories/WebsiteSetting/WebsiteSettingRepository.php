<?php

namespace App\Repositories\WebsiteSetting;

use App\Enums\WebsiteSettingKey;
use App\Models\WebsiteSetting;
use App\Repositories\AbstractRepository;
use Illuminate\Support\Collection;

class WebsiteSettingRepository extends AbstractRepository implements WebsiteSettingRepositoryInterface
{
    public function __construct(
        protected WebsiteSetting $model,
    ) {}

    public function keyedValues(): Collection
    {
        return $this->model->newQuery()
            ->pluck('value', 'key');
    }

    public function upsertValues(array $values): void
    {
        $now = now();

        foreach ($values as $key => $value) {
            $settingKey = WebsiteSettingKey::tryFrom($key);

            if ($settingKey === null) {
                continue;
            }

            $this->model->newQuery()->updateOrCreate(
                ['key' => $settingKey->value],
                [
                    'section' => $settingKey->section(),
                    'value_type' => $settingKey->valueType(),
                    'value' => $value,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
