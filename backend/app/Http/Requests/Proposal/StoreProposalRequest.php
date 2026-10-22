<?php

namespace App\Http\Requests\Proposal;

use Illuminate\Foundation\Http\FormRequest;

class StoreProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDelala() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cover_note'                        => ['sometimes', 'nullable', 'string', 'max:1000'],
            'items'                             => ['required', 'array', 'min:1', 'max:3'],
            'items.*.item_name'                 => ['required', 'string', 'max:200'],
            'items.*.description'               => ['sometimes', 'nullable', 'string', 'max:1000'],
            'items.*.text_location'             => ['sometimes', 'nullable', 'string', 'max:300'],
            'items.*.image_s3_keys'             => ['sometimes', 'array', 'max:5'],
            'items.*.image_s3_keys.*'           => ['string', 'max:500'],
            'items.*.estimated_item_price_santim' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'items.*.commission_amount_santim'  => ['required', 'integer', 'min:5000'], // min 50 ETB
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.*.commission_amount_santim.min' => 'Minimum commission is 50 ETB (5000 Santim).',
        ];
    }
}
