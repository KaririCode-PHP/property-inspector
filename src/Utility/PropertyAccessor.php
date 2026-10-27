<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Utility;

/**
 * Reads and writes one property of a given object through reflection,
 * bypassing visibility (public, protected, private, and PHP 8.4 asymmetric
 * `private(set)`), so change appliers can write processed values back without
 * requiring setters on the target class.
 *
 * Side effects: setValue() mutates the target object in place. A `set`
 * property hook on the target still runs, so hook-based validation stays
 * enforced and its exceptions propagate unchanged.
 *
 * Preconditions:
 * - The property must exist; otherwise the constructor throws \ReflectionException.
 * - An initialized readonly property cannot be written: setValue() throws
 *   \Error ("Cannot modify readonly property"). Readonly DPOs therefore cannot
 *   be updated in place; see the ARFA P1 proposal in ADR-006.
 * - Reading an uninitialized typed property throws \Error.
 *
 * Security: reflection deliberately breaks encapsulation. Use it only on
 * application-owned objects, never on a class selected from untrusted input
 * (SPEC-001, threat-model note).
 *
 * Since PHP 8.1, ReflectionProperty::setAccessible() is a no-op (ADR-001).
 *
 * Public API, not final in 2.x so existing subclasses keep working; it becomes
 * final in 3.0 (ADR-009).
 *
 * Example:
 *
 *     $accessor = new PropertyAccessor($user, 'email');
 *     $accessor->setValue(trim((string) $accessor->getValue()));
 *
 * Thread-safety: the accessor itself is immutable, but setValue() writes to
 * the target; concurrent writers to one target need external coordination.
 *
 * @see docs/ADR-001-remove-setaccessible-dead-code.md
 * @see docs/ADR-006-immutable-change-application.md
 * @since 1.0.3
 *
 * @api
 */
readonly class PropertyAccessor
{
    private \ReflectionProperty $reflectionProperty;

    public function __construct(private object $object, string $propertyName)
    {
        $this->reflectionProperty = new \ReflectionProperty($this->object, $propertyName);
    }

    public function getValue(): mixed
    {
        return $this->reflectionProperty->getValue($this->object);
    }

    public function setValue(mixed $value): void
    {
        $this->reflectionProperty->setValue($this->object, $value);
    }
}
