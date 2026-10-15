<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Login request.
 * SRS FR-1.3, NFR-SEC-2.
 */
class LoginRequest extends FormRequest
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
            'login'    => ['required', 'string'], // email or phone
            'password' => ['required', 'string'],
        ];
    }
}
