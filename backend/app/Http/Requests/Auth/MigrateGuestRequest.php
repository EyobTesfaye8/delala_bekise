<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * JIT Guest → Buyer migration request.
 * SRS FR-1.4, FR-1.5.
 */
class MigrateGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGuest() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'email:rfc,dns'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
    }
}
