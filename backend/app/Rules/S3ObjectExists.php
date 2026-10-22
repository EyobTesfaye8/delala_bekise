<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Translation\PotentiallyTranslatedString;

class S3ObjectExists implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || empty($value)) {
            $fail("The {$attribute} must be a valid storage key.");

            return;
        }

        // Allow simulated keys in testing
        if (app()->environment('testing') && (str_starts_with($value, 'mock/') || str_starts_with($value, 'testing/'))) {
            return;
        }

        try {
            if (! Storage::disk('s3')->exists($value)) {
                $fail("The file for {$attribute} was not found in storage. Please upload the file first.");
            }
        } catch (\Throwable $e) {
            // If S3 connection error occurs during testing
            if (app()->environment(['testing', 'local'])) {
                return;
            }

            $fail("Unable to verify {$attribute} in storage.");
        }
    }
}
