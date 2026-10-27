<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Contract;

use KaririCode\PropertyInspector\Exception\PropertyInspectionException;

/**
 * Port that turns an object into the list of its properties carrying a target
 * attribute, with each property's current value and attribute instances.
 * Implementations own any reflection caching; clearCache() resets it.
 *
 * Thread-safety: defined by each implementation; the reference implementation
 * documents its cache behaviour.
 *
 * @see \KaririCode\PropertyInspector\AttributeAnalyzer Reference implementation
 * @since 1.0.0
 */
interface AttributeAnalyzer
{
    /**
     * Analyzes an object for specific attributes on its properties.
     *
     * @param object $object The object to be analyzed
     *
     * @throws PropertyInspectionException If reflection or runtime error occurs during analysis
     *
     * @return array<string, array{value: mixed, attributes: array<object>}> An associative array with the analysis results
     */
    public function analyzeObject(object $object): array;

    /**
     * Clears the internal reflection metadata cache.
     *
     * Useful for long-running processes or after dynamic class modifications
     * to force re-analysis of previously cached class structures.
     *
     * @since 2.0.0
     */
    public function clearCache(): void;
}
