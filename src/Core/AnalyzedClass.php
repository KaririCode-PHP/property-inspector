<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Core;

/**
 * What AttributeAnalyzer found on one class for one attribute class: the
 * properties that carry at least one matching attribute, in the order
 * `ReflectionClass::getProperties()` returns them (the class's own
 * properties first, then the public and protected ones it inherits).
 *
 * Thread-safety: immutable; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $analysis = $analyzer->analyze(SignUp::class, Sanitize::class);
 *     $names = array_map(static fn(AnalyzedProperty $p): string => $p->name, $analysis->properties);
 *
 * @internal Result type of AttributeAnalyzer; not a public API (ADR-009).
 *
 * @see AnalyzedProperty
 *
 * @since 3.0.0
 */
final readonly class AnalyzedClass
{
    /**
     * @param list<AnalyzedProperty> $properties
     */
    public function __construct(
        public array $properties,
    ) {}
}
