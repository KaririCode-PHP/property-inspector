<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Core;

use KaririCode\Contract\Inspection\PropertyAttributeHandler;
use KaririCode\Contract\Inspection\PropertyInspector;
use KaririCode\PropertyInspector\Exception\InspectionFailedException;

/**
 * Reads the declarative rules of an object for a processor: hands every
 * property that carries an attribute of the requested class to the
 * processor's handler, with the attribute instance and the property's current
 * value. It implements `Contract\Inspection\PropertyInspector`, so one
 * instance serves every processor (sanitizer, validator, transformer,
 * serializer) and a container binds it once.
 *
 * Preconditions: as the contract states. An attribute matches when it is an
 * instance of $attributeClass; the object's own non-static properties of any
 * visibility are inspected, plus the public and protected ones it inherits; a
 * property that carries a matching attribute must be initialized.
 *
 * Thread-safety: the only state is the analyzer's per-class cache; no state
 * about inspected objects is kept, so one instance serves every caller.
 *
 * Side effects: calls the handler once per matching attribute instance, the
 * attributes of one property in declaration order, and never writes to the
 * object. Every annotated property is analyzed and read before the handler
 * sees any of them, so a failure of the inspection (InspectionFailedException:
 * 2503, 2506, 2507) never leaves a handler half-filled. Exceptions thrown by
 * the handler, or by the object's own get hooks, propagate unchanged
 * (ADR-010), so a Validation raised by a handler still reaches the edge as a
 * 422.
 *
 * Example:
 *
 *     $inspector = new ReflectionPropertyInspector();
 *     $inspector->inspect($signUp, Sanitize::class, $rules);   // $rules: PropertyAttributeHandler
 *
 * @see \KaririCode\Contract\Inspection\PropertyInspector
 * @see https://github.com/KaririCode-PHP/property-inspector/blob/main/docs/spec/SPEC-001-property-inspection-pipeline.md
 *
 * @since 3.0.0
 */
final class ReflectionPropertyInspector implements PropertyInspector
{
    public function __construct(
        private readonly AttributeAnalyzer $analyzer = new AttributeAnalyzer(),
    ) {}

    #[\Override]
    public function inspect(object $object, string $attributeClass, PropertyAttributeHandler $handler): void
    {
        foreach ($this->read($object, $attributeClass) as $reading) {
            foreach ($reading[0]->attributes as $attribute) {
                $handler->handleAttribute($reading[0]->name, $attribute, $reading[1]);
            }
        }
    }

    /**
     * Drops the cached reflection metadata and attribute instances; the next
     * inspection of each class analyzes it again.
     */
    public function clearCache(): void
    {
        $this->analyzer->clearCache();
    }

    /**
     * @param class-string $attributeClass
     *
     * @return list<array{AnalyzedProperty, mixed}>
     */
    private function read(object $object, string $attributeClass): array
    {
        $readings = [];

        foreach ($this->analyzer->analyze($object::class, $attributeClass)->properties as $property) {
            if (! $property->reflection->isInitialized($object)) {
                throw InspectionFailedException::uninitializedProperty($object::class, $property->name);
            }

            $readings[] = [$property, $property->reflection->getValue($object)];
        }

        return $readings;
    }
}
