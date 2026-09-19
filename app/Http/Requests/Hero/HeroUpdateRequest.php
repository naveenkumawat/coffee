<?php

namespace App\Http\Requests\Hero;

use App\Enums\WebsiteSettingKey;
use App\Models\WebsiteSetting;
use App\Support\PublicMedia;
use Illuminate\Foundation\Http\FormRequest;

class HeroUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('update', WebsiteSetting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            WebsiteSettingKey::HeroTitle->value => ['nullable', 'string', 'max:'.WebsiteSettingKey::HeroTitle->maxLength()],
            WebsiteSettingKey::HeroImagePath->value => ['nullable', 'string', 'max:'.WebsiteSettingKey::HeroImagePath->maxLength()],
            'hero_image' => PublicMedia::uploadRules(),
            'remove_hero_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            WebsiteSettingKey::HeroTitle->value => WebsiteSettingKey::HeroTitle->label(),
            WebsiteSettingKey::HeroImagePath->value => WebsiteSettingKey::HeroImagePath->label(),
            'hero_image' => 'Hero image',
            'remove_hero_image' => 'Remove hero image',
        ];
    }
}
