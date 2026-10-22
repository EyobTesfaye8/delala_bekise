<?php

namespace App\Services;

class JsonSchemaValidator
{
    /**
     * Validate data against JSON Schema (Draft-07 subset).
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $data
     * @return array<string> Error messages
     */
    public function validate(array $schema, array $data): array
    {
        $errors = [];

        // 1. Required properties check
        if (! empty($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                if (! array_key_exists($requiredField, $data) || $data[$requiredField] === null || $data[$requiredField] === '') {
                    $errors[] = "The attribute '{$requiredField}' is required.";
                }
            }
        }

        // 2. Properties checks
        if (! empty($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $field => $rules) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $value = $data[$field];

                // Type check
                if (! empty($rules['type'])) {
                    $validType = match ($rules['type']) {
                        'string' => is_string($value),
                        'number' => is_numeric($value),
                        'integer' => is_int($value) || (is_numeric($value) && (int) $value == $value),
                        'boolean' => is_bool($value),
                        'array' => is_array($value),
                        'object' => is_array($value),
                        default => true,
                    };

                    if (! $validType) {
                        $errors[] = "The attribute '{$field}' must be of type {$rules['type']}.";
                    }
                }

                // Enum check
                if (! empty($rules['enum']) && is_array($rules['enum'])) {
                    if (! in_array($value, $rules['enum'], true)) {
                        $allowed = implode(', ', $rules['enum']);
                        $errors[] = "The attribute '{$field}' must be one of: {$allowed}.";
                    }
                }

                // Minimum / Maximum for numbers
                if (is_numeric($value)) {
                    if (isset($rules['minimum']) && $value < $rules['minimum']) {
                        $errors[] = "The attribute '{$field}' must be at least {$rules['minimum']}.";
                    }
                    if (isset($rules['maximum']) && $value > $rules['maximum']) {
                        $errors[] = "The attribute '{$field}' may not be greater than {$rules['maximum']}.";
                    }
                }

                // MinLength / MaxLength for strings
                if (is_string($value)) {
                    if (isset($rules['minLength']) && mb_strlen($value) < $rules['minLength']) {
                        $errors[] = "The attribute '{$field}' must be at least {$rules['minLength']} characters.";
                    }
                    if (isset($rules['maxLength']) && mb_strlen($value) > $rules['maxLength']) {
                        $errors[] = "The attribute '{$field}' may not be greater than {$rules['maxLength']} characters.";
                    }
                }
            }
        }

        return $errors;
    }
}
