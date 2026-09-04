<?php

namespace App\Http\Requests\Segment;

use App\Http\Requests\AbstractRequest;

class AudienceSegmentPreviewRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->canManageWebsiteSettings();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'visitor_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'run_count' => ['nullable', 'boolean'],
        ];
    }
}
