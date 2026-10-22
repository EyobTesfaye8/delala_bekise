<?php

namespace App\Http\Requests\Proposal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'commission_amount_santim' => ['required', 'integer', 'min:5000'],
            'note'                     => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
