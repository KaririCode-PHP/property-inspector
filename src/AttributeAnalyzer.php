<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector;

use KaririCode\PropertyInspector\Contract\AttributeAnalyzer as AttributeAnalyzerContract;
use KaririCode\PropertyInspector\Exception\PropertyInspectionException;

/**
 * Discovers which properties of an object carry a given attribute type and
 * extracts their current values, so processors (sanitization, validation,
 * transformation) are driven by declarative metadata instead of hand-written
 * mapping code.
 *
 * Matching uses ReflectionAttribute::IS_INSTANCEOF: passing an interface or a
 * base class selects every attribute that implements or extends it.
 *
 * Caching and side effects:
 * - Reflection metadata and attribute instances are cached per class, per
 *   analyzer instance. Attribute constructors therefore run once per class, on
 *   the first analysis; later analyses hand out the same attribute objects,
 *   which callers MUST treat as immutable.
 * - Property values are never cached; they are read on every call.
 * - The cache is unbounded. Long-running workers (ARFA Level 2) that analyze
 *   many distinct classes should call clearCache() periodically.
 *
 * Preconditions:
 * - Every matching property must be initialized. Reading an uninitialized
 *   typed property raises \Error, surfaced as code 2503.
 *
 * Failures are fail-closed: no raw throwable escapes. Every failure surfaces
 * as PropertyInspectionException with code 2501, 2503 or 2506.
 *
 * Example:
 *
 *     $analyzer = new AttributeAnalyzer(Validate::class);
 *     $result = $analyzer->analyzeObject($userDpo);
 *     // ['email' => ['value' => 'a@b.c', 'attributes' => [Validate {...}]]]
 *
 * Thread-safety: the per-class cache is written on the first analysis of each
 * class. Concurrent first analyses (fibers, coroutines) store equal metadata,
 * so sharing an instance inside one process is safe; attribute instance
 * identity (INV-012) holds once that first analysis has completed.
 *
 * @see docs/SPEC-001-property-inspection-pipeline.md
 * @see docs/ADR-005-symmetric-analysis-exception-handling.md
 * @since 1.0.0
 */
final class AttributeAnalyzer implements AttributeAnalyzerContract
{
    /** @var array<class-string, array<string, array{attributes: list<object>, property: \ReflectionProperty}>> */
    private array $cache = [];

    /** @param class-string $attributeClass Attribute class, interface or base class to match (IS_INSTANCEOF) */
    public function __construct(private readonly string $attributeClass) {}

    #[\Override]
    public function analyzeObject(object $object): array
    {
        try {
            $className = $object::class;

            if (! isset($this->cache[$className])) {
                $this->cacheObjectMetadata($object);
            }

            return $this->extractValues($object);
        } catch (\ReflectionException $e) {
            throw PropertyInspectionException::failedToAnalyzeObjectReflection($e);
        } catch (\Exception $e) {
            throw PropertyInspectionException::failedToAnalyzeObjectException($e);
        } catch (\Error $e) {
            throw PropertyInspectionException::failedToAnalyzeObjectError($e);
        }
    }

    #[\Override]
    public function clearCache(): void
    {
        $this->cache = [];
    }

    private function cacheObjectMetadata(object $object): void
    {
        $className = $object::class;
        $reflection = new \ReflectionClass($object);
        $cachedProperties = [];

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes($this->attributeClass, \ReflectionAttribute::IS_INSTANCEOF);

            if (! empty($attributes)) {
                $attributeInstances = array_map(
                    static fn(\ReflectionAttribute $attr): object => $attr->newInstance(),
                    $attributes,
                );

                $cachedProperties[$property->getName()] = [
                    'attributes' => $attributeInstances,
                    'property' => $property,
                ];
            }
        }

        $this->cache[$className] = $cachedProperties;
    }

    /** @return array<string, array{value: mixed, attributes: list<object>}> */
    private function extractValues(object $object): array
    {
        $results = [];
        $className = $object::class;

        foreach ($this->cache[$className] as $propertyName => $data) {
            $results[$propertyName] = [
                'value' => $data['property']->getValue($object),
                'attributes' => $data['attributes'],
            ];
        }

        return $results;
    }
}
