<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates OTP send request.
 * SRS FR-1.1, NFR-SEC-1.
 */
class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone'            => ['required', 'string', 'regex:/^\+251[79]\d{8}$/'],
            'full_name'        => ['sometimes', 'string', 'max:100'],
            'g_recaptcha_response' => ['required', 'string', 'captcha'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex'             => 'Phone must be a valid Ethiopian number in E.164 format (+2519... or +2517...).',
            'g_recaptcha_response.captcha' => 'reCAPTCHA verification failed. Please try again.',
        ];
    }
}
