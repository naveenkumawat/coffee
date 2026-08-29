<?php

namespace App\Http\Requests\User;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'string', Rule::in(['administrator', 'barista', 'customer'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
