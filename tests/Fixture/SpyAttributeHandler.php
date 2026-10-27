<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Contract\PropertyAttributeHandler;

final class SpyAttributeHandler implements PropertyAttributeHandler
{
    /** @var list<array{propertyName: string, attribute: object, value: mixed}> */
    private array $calls = [];

    /** @var array<string, mixed> */
    private array $processedValues = [];

    /** @var array<string, array<string, string>> */
    private array $messages = [];

    /** @var array<string, array<string, string>> */
    private array $errors = [];

    public function handleAttribute(string $propertyName, object $attribute, mixed $value): mixed
    {
        $this->calls[] = [
            'propertyName' => $propertyName,
            'attribute' => $attribute,
            'value' => $value,
        ];
        $this->processedValues[$propertyName] = $value;

        return $value;
    }

    public function getProcessedPropertyValues(): array
    {
        return $this->processedValues;
    }

    public function getProcessingResultMessages(): array
    {
        return $this->messages;
    }

    public function getProcessingResultErrors(): array
    {
        return $this->errors;
    }

    /** @return list<array{propertyName: string, attribute: object, value: mixed}> */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getCallCount(): int
    {
        return \count($this->calls);
    }

    /**
     * Returns calls filtered by property name.
     *
     * @return list<array{propertyName: string, attribute: object, value: mixed}>
     */
    public function getCallsForProperty(string $propertyName): array
    {
        return array_values(
            array_filter(
                $this->calls,
                static fn(array $call): bool => $call['propertyName'] === $propertyName,
            ),
        );
    }
}
