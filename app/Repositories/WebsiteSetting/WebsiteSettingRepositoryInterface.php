<?php

namespace App\Repositories\WebsiteSetting;

use Illuminate\Support\Collection;

interface WebsiteSettingRepositoryInterface
{
    /**
     * @return Collection<string, string|null>
     */
    public function keyedValues(): Collection;

    /**
     * @param  array<string, string|null>  $values
     */
    public function upsertValues(array $values): void;
}
