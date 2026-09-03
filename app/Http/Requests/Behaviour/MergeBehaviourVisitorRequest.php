<?php

namespace App\Http\Requests\Behaviour;

use App\Enums\UserRole;
use App\Http\Requests\AbstractRequest;
use App\Models\User;

class MergeBehaviourVisitorRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user?->hasRole(UserRole::Customer) ?? false;
    }

    public function rules(): array
    {
        return [
            'visitor_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }
}
