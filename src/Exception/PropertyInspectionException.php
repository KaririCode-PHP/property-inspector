<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Exception;

use KaririCode\Contract\Exception\Runtime;

/**
 * Marks every failure this package raises itself, so a caller can catch the
 * package's own faults in one place, the Runtime family of the ecosystem, or
 * the ecosystem root (`Contract\Exception\ExceptionInterface`). Each failure
 * carries a stable code in the range PI-2500–2599 and a message that starts
 * with `[PI_<code>]`.
 *
 * | Code | Raised by                    | Cause                                          |
 * |------|------------------------------|------------------------------------------------|
 * | 2503 | InspectionFailedException    | an attribute cannot be instantiated (\Error)    |
 * | 2506 | InspectionFailedException    | an attribute constructor threw an \Exception   |
 * | 2507 | InspectionFailedException    | an annotated property is not initialized       |
 * | 2520 | ChangeRejectedException      | no writable property has that name             |
 * | 2521 | ChangeRejectedException      | the named property is static                   |
 * | 2522 | ChangeRejectedException      | the property rejects the value (\Error)        |
 * | 2523 | ChangeRejectedException      | the object's class cannot be copied            |
 *
 * Exceptions thrown by a handler, or by the object's own property hooks, are
 * not wrapped: they reach the caller unchanged and keep their family
 * (ADR-010).
 *
 * Thread-safety: an exception is immutable once created.
 *
 * Example:
 *
 *     try {
 *         $inspector->inspect($signUp, Sanitize::class, $rules);
 *     } catch (PropertyInspectionException $fault) {
 *         $logger->error($fault->getMessage(), ['code' => $fault->getCode()]);
 *     }
 *
 * @see https://github.com/KaririCode-PHP/property-inspector/blob/main/docs/adr/ADR-011-exception-model-3-0.md
 * @see \KaririCode\Contract\Exception\Runtime
 *
 * @since 3.0.0
 */
interface PropertyInspectionException extends Runtime {}
