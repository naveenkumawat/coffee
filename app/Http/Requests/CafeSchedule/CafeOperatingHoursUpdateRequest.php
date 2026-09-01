<?php

namespace App\Http\Requests\CafeSchedule;

use Illuminate\Foundation\Http\FormRequest;

class CafeOperatingHoursUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageWebsiteSettings() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array'],
            'days.*.enabled' => ['nullable', 'boolean'],
            'days.*.opens_at' => ['nullable', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('days', []) as $weekday => $day) {
                if (! is_array($day) || ! filter_var($day['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $opens = $day['opens_at'] ?? null;
                $closes = $day['closes_at'] ?? null;

                if (! is_string($opens) || ! is_string($closes) || $opens === '' || $closes === '') {
                    $validator->errors()->add("days.$weekday.opens_at", 'Open and close times are required for enabled days.');

                    continue;
                }

                if ($closes <= $opens) {
                    $validator->errors()->add("days.$weekday.closes_at", 'Closing time must be after opening time.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $days = $this->input('days', []);

        if (! is_array($days)) {
            return;
        }

        foreach ($days as $weekday => $day) {
            if (! is_array($day)) {
                continue;
            }

            $days[$weekday]['enabled'] = filter_var($day['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge(['days' => $days]);
    }
}
