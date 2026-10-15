<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates new payout destination registration.
 * SRS FR-2.4.
 */
class StorePayoutDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDelala() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method'               => ['required', 'string', 'in:bank,telebirr'],
            'bank_name'            => ['required_if:method,bank', 'nullable', 'string', 'max:100',
                'in:CBE,Bank of Abyssinia,Awash Bank,Dashen Bank,Berhan Bank,Cooperative Bank,Lion Bank,Bunna Bank,Abay Bank,Addis International Bank,Enat Bank',
            ],
            'account_holder_name'  => ['required', 'string', 'max:150'],
            'account_number'       => ['required', 'string', 'max:30'],
            'is_default'           => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_name.required_if' => 'Bank name is required when payment method is bank.',
            'bank_name.in'          => 'Unsupported bank. Please contact support if your bank is missing.',
        ];
    }
}
