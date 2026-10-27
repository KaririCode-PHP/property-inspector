<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Contract\PropertyAttributeHandler;

/**
 * Handler that throws the configured failure on the first attribute it
 * receives; drives the inspection-side wrapping paths (2504, 2505).
 */
final readonly class FailingAttributeHandler implements PropertyAttributeHandler
{
    public function __construct(
        private \Throwable $failure,
    ) {}

    #[\Override]
    public function handleAttribute(string $propertyName, object $attribute, mixed $value): mixed
    {
        throw $this->failure;
    }

    #[\Override]
    public function getProcessedPropertyValues(): array
    {
        return [];
    }

    #[\Override]
    public function getProcessingResultMessages(): array
    {
        return [];
    }

    #[\Override]
    public function getProcessingResultErrors(): array
    {
        return [];
    }
}
