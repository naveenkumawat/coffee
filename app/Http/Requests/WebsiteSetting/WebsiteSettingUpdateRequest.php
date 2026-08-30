<?php

namespace App\Http\Requests\WebsiteSetting;

use App\Enums\WebsiteSettingKey;
use App\Models\WebsiteSetting;
use App\Support\PublicMedia;
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
        $rules = [
            'hero_image' => PublicMedia::uploadRules(),
            'payment_qr_image' => PublicMedia::uploadRules(),
            'remove_hero_image' => ['nullable', 'boolean'],
            'remove_payment_qr_image' => ['nullable', 'boolean'],
        ];

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
        $attributes = [
            'hero_image' => 'Hero image',
            'payment_qr_image' => 'Payment QR image',
            'remove_hero_image' => 'Remove hero image',
            'remove_payment_qr_image' => 'Remove payment QR image',
        ];

        foreach (WebsiteSettingKey::ordered() as $key) {
            $attributes[$key->value] = $key->label();
        }

        return $attributes;
    }
}
