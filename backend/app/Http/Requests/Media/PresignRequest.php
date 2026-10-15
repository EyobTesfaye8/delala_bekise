<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates pre-signed URL generation request.
 * SRS §9.1 — file binary never passes through the Laravel server.
 */
class PresignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file_type'        => ['required', 'string', 'in:kyc_id,kyc_selfie,proposal_image,dispute_evidence'],
            'mime_type'        => ['required', 'string', 'in:image/jpeg,image/png,image/webp,application/pdf'],
            'file_size_bytes'  => ['required', 'integer', 'min:1', 'max:5242880'], // max 5MB
            'filename'         => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file_size_bytes.max' => 'File size must not exceed 5MB.',
            'mime_type.in'        => 'Only JPEG, PNG, WebP images and PDF documents are allowed.',
        ];
    }
}
