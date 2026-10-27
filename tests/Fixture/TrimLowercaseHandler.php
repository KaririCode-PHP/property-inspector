<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Contract\PropertyAttributeHandler;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;

/**
 * Test double that applies the trim/lowercase sanitizers declared by the
 * Sanitize attribute and records the sanitized value per property.
 */
final class TrimLowercaseHandler implements PropertyAttributeHandler
{
    /** @var array<string, mixed> */
    private array $processed = [];

    public function handleAttribute(string $propertyName, object $attribute, mixed $value): mixed
    {
        if ($attribute instanceof Sanitize) {
            $sanitized = $value;
            foreach ($attribute->sanitizers as $sanitizer) {
                $sanitized = match ($sanitizer) {
                    'trim' => \is_string($sanitized) ? trim($sanitized) : $sanitized,
                    'lowercase' => \is_string($sanitized) ? strtolower($sanitized) : $sanitized,
                    default => $sanitized,
                };
            }
            $this->processed[$propertyName] = $sanitized;

            return $sanitized;
        }

        return $value;
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
        return [];
    }
}
