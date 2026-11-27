<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Core;

use KaririCode\Contract\Inspection\PropertyChangeApplier;
use KaririCode\PropertyInspector\Exception\ChangeRejectedException;

/**
 * Writes processed values back by copy: returns a new instance of the
 * object's class in which the named properties hold their new values and
 * every other property keeps the original's, leaving the original untouched.
 * It implements `Contract\Inspection\PropertyChangeApplier`, which is how a
 * sanitizer or transformer updates a readonly DPO (ARFA 1.3 P1, ADR-006).
 *
 * Preconditions: $object is an instance of a user-defined class whose state
 * lives in declared properties; each key of $changes names a property the
 * inspector would inspect, and each value fits that property's type. The
 * constructor of the copy does not run, so validate the copy, not the
 * original.
 *
 * Thread-safety: stateless; one instance serves every caller.
 *
 * Side effects: allocates the copy and never writes to $object; the copy is
 * a new instance even when $changes is empty. Names are resolved before
 * anything is allocated. Unchanged state is copied as stored, without
 * running set hooks: every storage slot is written exactly once, the private
 * properties of parent classes included and a property redeclared by a
 * subclass only once. Changed properties are assigned, so their set hooks
 * run; an \Exception thrown by a hook propagates unchanged (ADR-010). An
 * uninitialized property stays uninitialized unless $changes sets it.
 * Failures raise ChangeRejectedException (2520 unknown name, 2521 static
 * property, 2522 rejected value, 2523 class that cannot be copied) instead of
 * returning a partial copy.
 *
 * Example:
 *
 *     $clean = (new ReflectionChangeApplier())->applyChanges($signUp, ['email' => 'walmir@kariricode.org']);
 *     // $clean !== $signUp, and $signUp->email is unchanged
 *
 * @see \KaririCode\Contract\Inspection\PropertyChangeApplier
 * @see https://wiki.php.net/rfc/readonly_properties_v2
 *
 * @since 3.0.0
 */
final readonly class ReflectionChangeApplier implements PropertyChangeApplier
{
    #[\Override]
    public function applyChanges(object $object, array $changes): object
    {
        $class = new \ReflectionClass($object);
        $targets = $this->targets($class, $changes);
        $copy = $this->blank($class);

        $this->copyStoredState($class, $object, $copy, $targets);

        foreach ($targets as $name => $property) {
            try {
                $property->setValue($copy, $changes[$name]);
            } catch (\Error $cause) {
                throw ChangeRejectedException::valueRejected($class->getName(), $name, $cause);
            }
        }

        return $copy;
    }

    /**
     * @param \ReflectionClass<object> $class
     * @param array<string, mixed>     $changes
     *
     * @return array<string, \ReflectionProperty>
     */
    private function targets(\ReflectionClass $class, array $changes): array
    {
        $targets = [];

        foreach (array_keys($changes) as $name) {
            $targets[$name] = $this->target($class, $name);
        }

        return $targets;
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    private function target(\ReflectionClass $class, int|string $name): \ReflectionProperty
    {
        $propertyName = \is_int($name) ? (string) $name : $name;

        try {
            $property = $class->getProperty($propertyName);
        } catch (\ReflectionException $cause) {
            throw ChangeRejectedException::unknownProperty($class->getName(), $propertyName, $cause);
        }

        if ($property->isStatic()) {
            throw ChangeRejectedException::staticProperty($class->getName(), $propertyName);
        }

        return $property;
    }

    /**
     * @template T of object
     *
     * @param \ReflectionClass<T> $class
     *
     * @return T
     */
    private function blank(\ReflectionClass $class): object
    {
        try {
            return $class->newInstanceWithoutConstructor();
        } catch (\ReflectionException|\Error $cause) {
            throw ChangeRejectedException::notCopyable($class->getName(), $cause);
        }
    }

    /**
     * @param \ReflectionClass<object>          $class
     * @param array<string, \ReflectionProperty> $targets
     */
    private function copyStoredState(\ReflectionClass $class, object $source, object $copy, array $targets): void
    {
        foreach ($this->storageSlots($class) as $property) {
            if (! $this->isTarget($property, $targets) && $property->isInitialized($source)) {
                $property->setRawValue($copy, $property->getRawValue($source));
            }
        }
    }

    /**
     * Every storage slot of the class hierarchy, once. The walk is needed
     * because a child's getProperties() omits its parents' private
     * properties. A private property is a slot of its declaring class and is
     * met only at that class's level. A public or protected name is one slot
     * however many classes declare it, so only its most derived declaration
     * is kept.
     *
     * @param \ReflectionClass<object> $class
     *
     * @return list<\ReflectionProperty>
     */
    private function storageSlots(\ReflectionClass $class): array
    {
        $slots = [];
        $shared = [];

        for ($declaring = $class; false !== $declaring; $declaring = $declaring->getParentClass()) {
            foreach ($declaring->getProperties() as $property) {
                if (! $this->isDeclaredStorage($property, $declaring)) {
                    continue;
                }

                if ($property->isPrivate()) {
                    $slots[] = $property;

                    continue;
                }

                if (! isset($shared[$property->getName()])) {
                    $slots[] = $property;
                    $shared[$property->getName()] = $property;
                }
            }
        }

        return $slots;
    }

    /**
     * @param \ReflectionClass<object> $declaring
     */
    private function isDeclaredStorage(\ReflectionProperty $property, \ReflectionClass $declaring): bool
    {
        return $property->getDeclaringClass()->getName() === $declaring->getName()
            && ! $property->isStatic()
            && ! $property->isVirtual();
    }

    /**
     * A change targets the slot of the property the object's class sees under
     * that name. getProperty() returns the most derived declaration, the same
     * one storageSlots() keeps for a shared name, and a class cannot declare
     * two properties with one name, so the declaring class identifies the
     * slot. A parent's private property with the same name is another slot.
     *
     * @param array<string, \ReflectionProperty> $targets
     */
    private function isTarget(\ReflectionProperty $property, array $targets): bool
    {
        $target = $targets[$property->getName()] ?? null;

        return null !== $target && $target->getDeclaringClass()->getName() === $property->getDeclaringClass()->getName();
    }
}
