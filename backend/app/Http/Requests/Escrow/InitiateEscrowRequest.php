<?php

namespace App\Http\Requests\Escrow;

use Illuminate\Foundation\Http\FormRequest;

class InitiateEscrowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'proposal_item_uuid' => ['required', 'string', 'exists:proposal_items,uuid'],
        ];
    }
}
