<?php

namespace App\Http\Requests\Segment;

use App\Enums\AudienceSegmentActor;
use App\Enums\AudienceSegmentStatus;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class AudienceSegmentStoreRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->canManageWebsiteSettings();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rules' => $this->decodeJsonField('rules'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'string', Rule::in(AudienceSegmentStatus::values())],
            'actor_scope' => ['required', 'string', Rule::in(AudienceSegmentActor::values())],
            'rules' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonField(string $key): array
    {
        $value = $this->input($key);

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
