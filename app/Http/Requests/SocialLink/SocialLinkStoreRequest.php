<?php

namespace App\Http\Requests\SocialLink;

use App\Enums\SocialIconKey;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class SocialLinkStoreRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageWebsiteSettings() ?? false;
    }

    public function rules(): array
    {
        return [
            'platform_key' => [
                'required',
                'string',
                'max:32',
                'regex:/^[a-z][a-z0-9_]{0,31}$/',
                Rule::unique('social_links', 'platform_key')->whereNull('deleted_at'),
            ],
            'label' => ['required', 'string', 'max:80'],
            'url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'icon_key' => ['required', 'string', Rule::in(SocialIconKey::values())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platform_key.regex' => 'Use a lowercase key starting with a letter (letters, numbers, underscore).',
            'platform_key.unique' => 'This platform key is already in use.',
        ];
    }
}
