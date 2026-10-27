<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Core;

/**
 * One property that carries attributes of the requested class: its name, its
 * reflection handle and the attribute instances, in declaration order. The
 * same instance is shared by every inspection of the class, so neither the
 * handle nor the attributes may be changed (SPEC-001 INV-012).
 *
 * Thread-safety: immutable; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     foreach ($analyzer->analyze(SignUp::class, Sanitize::class)->properties as $property) {
 *         echo $property->name, ': ', \count($property->attributes), PHP_EOL;
 *     }
 *
 * @internal Part of AttributeAnalyzer's result; not a public API (ADR-009).
 *
 * @see AttributeAnalyzer
 *
 * @since 3.0.0
 */
final readonly class AnalyzedProperty
{
    /**
     * @param list<object> $attributes
     */
    public function __construct(
        public string $name,
        public \ReflectionProperty $reflection,
        public array $attributes,
    ) {}
}
