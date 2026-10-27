<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Contract;

use KaririCode\PropertyInspector\Exception\PropertyInspectionException;

/**
 * Port that feeds every attribute-annotated property of an object to a
 * PropertyAttributeHandler. It is the single entry point processors use to
 * run declarative rules (sanitize, validate, transform) over an object.
 *
 * Thread-safety: defined by each implementation; the reference implementation
 * is stateless apart from its analyzer.
 *
 * @see \KaririCode\PropertyInspector\Utility\PropertyInspector Reference implementation
 * @since 1.0.0
 */
interface PropertyInspector
{
    /**
     * Inspects an object and processes its attributes.
     *
     * @param object $object The object to be inspected
     * @param PropertyAttributeHandler $handler The attribute handler
     *
     * @throws PropertyInspectionException If there's an error inspecting the object
     *
     * @return PropertyAttributeHandler The handler populated with inspection results
     */
    public function inspect(object $object, PropertyAttributeHandler $handler): PropertyAttributeHandler;
}
