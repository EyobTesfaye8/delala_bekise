<?php

namespace App\Http\Requests\Job;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isBuyerOrGuest() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id'       => ['required', 'integer', 'exists:categories,id'],
            'title'             => ['required', 'string', 'min:10', 'max:200'],
            'description'       => ['required', 'string', 'min:20', 'max:3000'],
            'location'          => ['sometimes', 'string', 'max:200'],
            'location_region'   => ['sometimes', 'string', 'max:100'],
            'location_subcity'  => ['sometimes', 'string', 'max:100'],
            'budget_min'        => ['sometimes', 'integer', 'min:0'],
            'budget_max'        => ['sometimes', 'integer', 'min:0', 'gte:budget_min'],
            'attributes'        => ['sometimes', 'array'],
        ];
    }
}
