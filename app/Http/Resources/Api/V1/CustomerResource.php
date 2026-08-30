<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $customer */
        $customer = $this->resource;

        return [
            'id' => $customer->getKey(),
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'role' => $customer->role?->value,
            'is_active' => (bool) $customer->is_active,
            'last_login_at' => $customer->last_login_at?->toIso8601String(),
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
        ];
    }
}
