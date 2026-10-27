<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Contract;

/**
 * Processor-side extension point: receives each (property, attribute, value)
 * triple found during inspection, applies the processor's semantics, and
 * accumulates processed values, messages and errors keyed by property name.
 *
 * Attribute instances passed to handleAttribute() may be shared across
 * inspections of the same class (analyzer cache) and must not be mutated.
 *
 * Thread-safety: handlers accumulate per-inspection state; use one handler
 * instance per inspected object.
 *
 * @see \KaririCode\PropertyInspector\Utility\PropertyInspector Caller of handleAttribute()
 * @see docs/spec/SPEC-001-property-inspection-pipeline.md
 * @since 1.0.0
 */
interface PropertyAttributeHandler
{
    /**
     * Handles an attribute found on a property.
     *
     * @param string $propertyName The name of the property
     * @param object $attribute The found attribute
     * @param mixed $value The property value
     *
     * @return mixed The result of handling the attribute
     */
    public function handleAttribute(string $propertyName, object $attribute, mixed $value): mixed;

    /**
     * Retrieves the values after processing.
     *
     * @return array<string, mixed> The processed values indexed by property name
     */
    public function getProcessedPropertyValues(): array;

    /**
     * Retrieves the messages generated during processing.
     *
     * @return array<string, array<string, string>> The processing messages indexed by property name and processor
     */
    public function getProcessingResultMessages(): array;

    /**
     * Retrieves the errors encountered during processing.
     *
     * @return array<string, array<string, string>> The processing errors indexed by property name and processor
     */
    public function getProcessingResultErrors(): array;
}
