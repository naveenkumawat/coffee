<?php

namespace App\Http\Requests\Dining;

use App\Enums\DiningRoundCancellationReason;
use App\Http\Requests\AbstractRequest;
use App\Models\DiningSession;
use Illuminate\Validation\Rule;

class DiningRoundCancelRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session')
            ?? $this->route('diningSession')
            ?? $this->route('dining_session');

        if (! $session instanceof DiningSession) {
            return false;
        }

        $user = $this->user() ?? $this->user('admin');

        return $user?->can('cancelRound', $session) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', Rule::in(array_keys(DiningRoundCancellationReason::options()))],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
