<?php

namespace App\Http\Requests\Campaign;

use App\Enums\CampaignImpressionEvent;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class CampaignInteractionRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'integer', 'min:1', 'exists:campaigns,id'],
            'event_type' => ['required', 'string', Rule::in(CampaignImpressionEvent::values())],
            'visitor_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'session_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'placement' => ['nullable', 'string', 'max:40'],
            'request_id' => ['nullable', 'string', 'max:80'],
            'cta_type' => ['nullable', 'string', 'max:40'],
        ];
    }
}
