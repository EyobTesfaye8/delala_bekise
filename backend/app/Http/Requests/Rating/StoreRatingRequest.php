<?php

namespace App\Http\Requests\Rating;

use Illuminate\Foundation\Http\FormRequest;

class StoreRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stars'       => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
