<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Exception;

/**
 * Signals that a change set cannot be written to a copy of the object: a key
 * names no writable property, names a static property, carries a value the
 * property rejects, or the object's class cannot be copied at all. It is
 * raised instead of returning a partial copy.
 *
 * Preconditions: built only through the named factories; the cause is kept
 * as previous and never copied into the message, and the rejected value is
 * never shown (Spec V4.3.3 E-047). A property name comes from the caller's
 * change set, so it appears in the message only when it is a plain ASCII
 * identifier; anything else is reported as an invalid name.
 *
 * Thread-safety: immutable once created.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     throw ChangeRejectedException::unknownProperty(SignUp::class, 'nickname');
 *     // [PI_2520] SignUp has no writable property named "nickname".
 *
 * @see PropertyInspectionException
 * @see https://github.com/KaririCode-PHP/property-inspector/blob/main/docs/adr/ADR-011-exception-model-3-0.md
 *
 * @since 3.0.0
 */
final class ChangeRejectedException extends \RuntimeException implements PropertyInspectionException
{
    private const int CODE_UNKNOWN_PROPERTY = 2520;
    private const int CODE_STATIC_PROPERTY = 2521;
    private const int CODE_VALUE_REJECTED = 2522;
    private const int CODE_NOT_COPYABLE = 2523;
    private const string IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D';

    private function __construct(int $code, string $detail, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('[PI_%d] %s', $code, $detail), $code, $previous);
    }

    /**
     * Also raised for a private property of a parent class, which is not
     * visible through the object's class.
     */
    public static function unknownProperty(string $className, string $propertyName, ?\ReflectionException $cause = null): self
    {
        return new self(
            self::CODE_UNKNOWN_PROPERTY,
            \sprintf('%s has no writable property %s.', $className, self::describe($propertyName)),
            $cause,
        );
    }

    public static function staticProperty(string $className, string $propertyName): self
    {
        return new self(
            self::CODE_STATIC_PROPERTY,
            \sprintf('%s::$%s is static and is not part of an instance.', $className, $propertyName),
        );
    }

    /**
     * The property's type, a readonly or read-only constraint, or a set hook
     * raised an \Error while the value was assigned.
     */
    public static function valueRejected(string $className, string $propertyName, \Error $cause): self
    {
        return new self(
            self::CODE_VALUE_REJECTED,
            \sprintf('%s::$%s rejects the new value.', $className, $propertyName),
            $cause,
        );
    }

    /**
     * Enums and internal final classes cannot be instantiated without their
     * constructor.
     */
    public static function notCopyable(string $className, \Throwable $cause): self
    {
        return new self(
            self::CODE_NOT_COPYABLE,
            \sprintf('Instances of %s cannot be copied without running a constructor.', $className),
            $cause,
        );
    }

    private static function describe(string $propertyName): string
    {
        return 1 === preg_match(self::IDENTIFIER, $propertyName)
            ? 'named "' . $propertyName . '"'
            : 'with the requested name, which is not a valid identifier';
    }
}
