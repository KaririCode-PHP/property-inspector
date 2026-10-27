<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Contract;

/**
 * Applies processed attribute handler results back to the inspected object's properties.
 *
 * Side effects: mutates the given object in place. Initialized readonly
 * properties cannot be written this way, so readonly DPOs need a
 * copy-on-write strategy instead (ADR-006, ARFA P1).
 *
 * Thread-safety: defined by each implementation; writes to the target object
 * must not race with other writers.
 *
 * @see \KaririCode\PropertyInspector\Utility\PropertyAccessor Reflection write primitive
 * @see docs/adr/ADR-006-immutable-change-application.md
 * @since 1.0.4
 */
interface PropertyChangeApplier
{
    /**
     * Applies the processed changes to the given object.
     *
     * @param object $object The object to which the changes will be applied
     */
    public function applyChanges(object $object): void;
}
