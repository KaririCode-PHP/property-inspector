<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Exception;

/**
 * Signals that the inspection itself could not proceed: an attribute
 * declared on a property cannot be instantiated, or a property that carries
 * a matching attribute is not initialized. It is raised before the handler
 * sees any property, so a handler is never left half-filled.
 *
 * Preconditions: built only through the named factories; the cause, when
 * there is one, is kept as previous and never copied into the message
 * (Spec V4.3.3 E-047). Class and property names come from code, not from
 * input, so they appear in the message.
 *
 * Thread-safety: immutable once created.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     throw InspectionFailedException::uninitializedProperty(SignUp::class, 'email');
 *     // [PI_2507] SignUp::$email carries a matching attribute but is not initialized.
 *
 * @see PropertyInspectionException
 * @see https://github.com/KaririCode-PHP/property-inspector/blob/main/docs/adr/ADR-011-exception-model-3-0.md
 *
 * @since 3.0.0
 */
final class InspectionFailedException extends \RuntimeException implements PropertyInspectionException
{
    private const int CODE_ATTRIBUTE_NOT_INSTANTIABLE = 2503;
    private const int CODE_ATTRIBUTE_CONSTRUCTOR_FAILED = 2506;
    private const int CODE_UNINITIALIZED_PROPERTY = 2507;

    private function __construct(int $code, string $detail, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('[PI_%d] %s', $code, $detail), $code, $previous);
    }

    /**
     * The attribute class is missing, is not an attribute, does not target
     * properties, or its arguments do not fit its constructor.
     */
    public static function attributeNotInstantiable(string $className, string $propertyName, \Error $cause): self
    {
        return new self(
            self::CODE_ATTRIBUTE_NOT_INSTANTIABLE,
            \sprintf('An attribute declared on %s::$%s cannot be instantiated.', $className, $propertyName),
            $cause,
        );
    }

    public static function attributeConstructorFailed(string $className, string $propertyName, \Exception $cause): self
    {
        return new self(
            self::CODE_ATTRIBUTE_CONSTRUCTOR_FAILED,
            \sprintf('The constructor of an attribute declared on %s::$%s threw an exception.', $className, $propertyName),
            $cause,
        );
    }

    public static function uninitializedProperty(string $className, string $propertyName): self
    {
        return new self(
            self::CODE_UNINITIALIZED_PROPERTY,
            \sprintf('%s::$%s carries a matching attribute but is not initialized.', $className, $propertyName),
        );
    }
}
