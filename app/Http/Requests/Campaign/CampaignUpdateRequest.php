<?php

namespace App\Http\Requests\Campaign;

class CampaignUpdateRequest extends CampaignStoreRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'remove_image' => ['nullable', 'boolean'],
        ]);
    }
}
