<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Exception;

use KaririCode\Exception\AbstractException;

/**
 * Single failure type of the component: signals that an object could not be
 * analyzed (reflection metadata, attribute instantiation, value reading) or
 * inspected (handler dispatch). Callers catch one type and branch on the
 * stable numeric code; the original throwable is always kept as previous.
 *
 * Codes (component range 2501-2599):
 *
 * | Code | Phase      | Cause                                           |
 * |------|------------|-------------------------------------------------|
 * | 2501 | analysis   | \ReflectionException                            |
 * | 2502 | inspection | \ReflectionException                            |
 * | 2503 | analysis   | \Error (e.g. uninitialized typed property)      |
 * | 2504 | inspection | \Exception thrown by the handler                |
 * | 2505 | inspection | \Error thrown by the handler                    |
 * | 2506 | analysis   | \Exception (e.g. attribute constructor throws)  |
 *
 * Example:
 *
 *     try {
 *         $inspector->inspect($dpo, $handler);
 *     } catch (PropertyInspectionException $e) {
 *         $logger->error($e->getMessage(), ['code' => $e->getCode()]);
 *     }
 *
 * Side effects: none; a factory only captures the cause as previous.
 *
 * Thread-safety: immutable once created; holds no shared state.
 *
 * @see docs/SPEC-001-property-inspection-pipeline.md
 * @see docs/ADR-005-symmetric-analysis-exception-handling.md
 * @since 1.0.0
 */
final class PropertyInspectionException extends AbstractException
{
    private const int CODE_REFLECTION_ANALYSIS_ERROR = 2501;
    private const int CODE_REFLECTION_INSPECTION_ERROR = 2502;
    private const int CODE_GENERAL_ANALYSIS_ERROR = 2503;
    private const int CODE_GENERAL_INSPECTION_ERROR = 2504;
    private const int CODE_CRITICAL_INSPECTION_ERROR = 2505;
    private const int CODE_GENERAL_ANALYSIS_EXCEPTION = 2506;

    public static function failedToAnalyzeObjectReflection(\ReflectionException $e): self
    {
        return self::createException(
            self::CODE_REFLECTION_ANALYSIS_ERROR,
            'REFLECTION_ANALYSIS_ERROR',
            "Failed to analyze object using reflection: {$e->getMessage()}",
            $e,
        );
    }

    /** @since 2.1.0 */
    public static function failedToAnalyzeObjectException(\Exception $e): self
    {
        return self::createException(
            self::CODE_GENERAL_ANALYSIS_EXCEPTION,
            'GENERAL_ANALYSIS_EXCEPTION',
            "An exception occurred during object analysis: {$e->getMessage()}",
            $e,
        );
    }

    public static function failedToAnalyzeObjectError(\Error $e): self
    {
        return self::createException(
            self::CODE_GENERAL_ANALYSIS_ERROR,
            'GENERAL_ANALYSIS_ERROR',
            "An error occurred during object analysis: {$e->getMessage()}",
            $e,
        );
    }

    public static function failedToInspectObjectReflection(\ReflectionException $e): self
    {
        return self::createException(
            self::CODE_REFLECTION_INSPECTION_ERROR,
            'REFLECTION_INSPECTION_ERROR',
            "Failed to inspect object using reflection: {$e->getMessage()}",
            $e,
        );
    }

    public static function failedToInspectObjectException(\Exception $e): self
    {
        return self::createException(
            self::CODE_GENERAL_INSPECTION_ERROR,
            'GENERAL_INSPECTION_ERROR',
            "An exception occurred during object inspection: {$e->getMessage()}",
            $e,
        );
    }

    public static function failedToInspectObjectError(\Error $e): self
    {
        return self::createException(
            self::CODE_CRITICAL_INSPECTION_ERROR,
            'CRITICAL_INSPECTION_ERROR',
            "A critical error occurred during object inspection: {$e->getMessage()}",
            $e,
        );
    }
}
