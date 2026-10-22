<?php

namespace App\Http\Requests\Escrow;

use Illuminate\Foundation\Http\FormRequest;

class DisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', 'in:item_not_found,item_damaged,item_mismatch,fraud_suspected,non_delivery,other'],
        ];
    }
}
