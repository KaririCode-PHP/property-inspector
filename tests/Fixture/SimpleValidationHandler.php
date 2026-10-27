<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Contract\PropertyAttributeHandler;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Validate;

/**
 * Test double that runs a minimal rule set (required, email, min) declared by
 * the Validate attribute, accumulating errors keyed by property and rule.
 */
final class SimpleValidationHandler implements PropertyAttributeHandler
{
    /** @var array<string, mixed> */
    private array $processed = [];

    /** @var array<string, array<string, string>> */
    private array $errors = [];

    public function handleAttribute(string $propertyName, object $attribute, mixed $value): mixed
    {
        $this->processed[$propertyName] = $value;

        if ($attribute instanceof Validate) {
            foreach ($attribute->processors as $rule) {
                $error = $this->validateRule($rule, $value);
                if ($error !== null) {
                    $this->errors[$propertyName][$rule] = $error;
                }
            }
        }

        return $value;
    }

    private function validateRule(string $rule, mixed $value): ?string
    {
        return match (true) {
            $rule === 'required' && ($value === '' || $value === null) => 'Field is required',
            $rule === 'email' && \is_string($value) && ! str_contains($value, '@') => 'Invalid email',
            str_starts_with($rule, 'min:') && \is_int($value) => $this->validateMin($rule, $value),
            str_starts_with($rule, 'min:') && \is_string($value) => $this->validateMinLength($rule, $value),
            default => null,
        };
    }

    private function validateMin(string $rule, int $value): ?string
    {
        $min = (int) substr($rule, 4);

        return $value < $min ? "Value must be at least {$min}" : null;
    }

    private function validateMinLength(string $rule, string $value): ?string
    {
        $min = (int) substr($rule, 4);

        return \strlen($value) < $min ? "Must be at least {$min} characters" : null;
    }

    public function getProcessedPropertyValues(): array
    {
        return $this->processed;
    }

    public function getProcessingResultMessages(): array
    {
        return [];
    }

    public function getProcessingResultErrors(): array
    {
        return $this->errors;
    }
}
