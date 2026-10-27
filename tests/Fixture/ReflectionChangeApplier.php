<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Contract\PropertyChangeApplier;
use KaririCode\PropertyInspector\Utility\PropertyAccessor;

/**
 * Test double that writes a map of processed values back to an object through
 * PropertyAccessor, exercising the reflection write path of the pipeline.
 */
final class ReflectionChangeApplier implements PropertyChangeApplier
{
    /** @param array<string, mixed> $changes */
    public function __construct(private readonly array $changes) {}

    public function applyChanges(object $object): void
    {
        foreach ($this->changes as $propertyName => $value) {
            $accessor = new PropertyAccessor($object, $propertyName);
            $accessor->setValue($value);
        }
    }
}
