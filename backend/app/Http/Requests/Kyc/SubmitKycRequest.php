<?php

namespace App\Http\Requests\Kyc;

use App\Services\MediaService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates KYC submission.
 * SRS FR-2.3: Front + Back of ID doc + Selfie, all as S3 keys.
 */
class SubmitKycRequest extends FormRequest
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
            'document_type'          => ['required', 'string', 'in:kebele_id,national_id,passport'],
            'id_document_s3_key'     => ['required', 'string', 'max:500'],
            'id_document_back_s3_key' => ['required_unless:document_type,passport', 'nullable', 'string', 'max:500'],
            'selfie_s3_key'          => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Custom after-validation: verify all S3 keys actually exist.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $mediaService = app(MediaService::class);

                $keysToCheck = array_filter([
                    'id_document_s3_key'      => $this->input('id_document_s3_key'),
                    'id_document_back_s3_key' => $this->input('id_document_back_s3_key'),
                    'selfie_s3_key'           => $this->input('selfie_s3_key'),
                ]);

                foreach ($keysToCheck as $field => $key) {
                    if ($key && ! $mediaService->objectExists($key)) {
                        $validator->errors()->add($field, "The file for {$field} was not found in storage. Please upload it first.");
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_document_back_s3_key.required_unless' => 'The back side of your ID document is required for Kebele ID and National ID.',
        ];
    }
}
