<?php

namespace App\Http\Requests\WebsiteSetting;

use App\Enums\WebsiteSettingKey;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Http\FormRequest;

class WebsiteSettingUpdateRequest extends FormRequest
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
        $rules = [];

        foreach (WebsiteSettingKey::ordered() as $key) {
            $fieldRules = ['nullable', 'string', 'max:'.$key->maxLength()];

            if ($key === WebsiteSettingKey::BusinessEmail) {
                $fieldRules[] = 'email';
            }

            $rules[$key->value] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (WebsiteSettingKey::ordered() as $key) {
            $attributes[$key->value] = $key->label();
        }

        return $attributes;
    }
}
