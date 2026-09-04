<?php

namespace App\Http\Resources\Api\V1\Dining;

use App\Models\DiningServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiningServiceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DiningServiceRequest $serviceRequest */
        $serviceRequest = $this->resource;
        $tableLabel = $serviceRequest->diningSession?->tableDisplayLabel()
            ?? $serviceRequest->table?->displayLabel()
            ?? null;

        return [
            'id' => $serviceRequest->getKey(),
            'dining_session_id' => (int) $serviceRequest->dining_session_id,
            'table_id' => (int) $serviceRequest->table_id,
            'table_label' => $tableLabel,
            'type' => $serviceRequest->type?->value,
            'type_label' => $serviceRequest->type?->label(),
            'status' => $serviceRequest->status?->value,
            'status_label' => $serviceRequest->status?->label(),
            'preferred_waiter_user_id' => $serviceRequest->preferred_waiter_user_id,
            'claimed_by_user_id' => $serviceRequest->claimed_by_user_id,
            'acknowledged_at' => $serviceRequest->acknowledged_at?->toIso8601String(),
            'escalated_at' => $serviceRequest->escalated_at?->toIso8601String(),
            'completed_at' => $serviceRequest->completed_at?->toIso8601String(),
            'cancelled_at' => $serviceRequest->cancelled_at?->toIso8601String(),
            'created_at' => $serviceRequest->created_at?->toIso8601String(),
            'customer_message' => match ($serviceRequest->status?->value) {
                'pending' => 'We’ve notified a waiter.',
                'claimed' => 'A waiter is on the way.',
                'completed' => 'Assistance completed.',
                'cancelled' => 'Waiter request cancelled.',
                default => null,
            },
            'is_escalated' => $serviceRequest->escalated_at !== null,
            'action_url' => $serviceRequest->dining_session_id
                ? '/waiter/sessions/'.$serviceRequest->dining_session_id
                : '/waiter',
        ];
    }
}
