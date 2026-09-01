<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserOrderingBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('admin');
        $managedUser = $this->route('user');

        return $actor !== null
            && $managedUser instanceof User
            && $actor->can('update', $managedUser);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ordering_blocked_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ordering_blocked_reason.max' => 'The internal reason may not be longer than 500 characters.',
        ];
    }
}
