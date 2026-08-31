<?php

namespace App\Http\Requests\HomeSection;

use App\Http\Requests\AbstractRequest;

class HomeSectionIndexRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canViewProducts() ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
