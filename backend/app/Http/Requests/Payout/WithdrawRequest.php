<?php

namespace App\Http\Requests\Payout;

use App\Models\PayoutRequest;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDelala() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount_santim'            => ['required', 'integer', 'min:'.PayoutRequest::minimumSantim()],
            'payout_destination_uuid'  => ['required', 'string', 'exists:payout_destinations,uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount_santim.min' => 'Minimum withdrawal is 500 ETB (50000 Santim).',
        ];
    }
}
