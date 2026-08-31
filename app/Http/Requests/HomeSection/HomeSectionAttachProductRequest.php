<?php

namespace App\Http\Requests\HomeSection;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class HomeSectionAttachProductRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProducts() ?? false;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
