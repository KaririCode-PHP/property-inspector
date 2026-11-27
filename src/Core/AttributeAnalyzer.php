<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Core;

use KaririCode\PropertyInspector\Exception\InspectionFailedException;

/**
 * Finds, once per class and attribute class, the properties that carry
 * matching attributes and builds those attribute instances. Matching uses
 * `ReflectionAttribute::IS_INSTANCEOF`, so an interface or base class selects
 * every attribute that implements or extends it.
 *
 * Preconditions: $className names a loaded class and $attributeClass an
 * attribute class, or an interface or base class of attribute classes.
 * Static properties are ignored. A private property of a parent class is
 * not visible through the child and is not analyzed.
 *
 * Thread-safety: results are cached per class and attribute class for the
 * life of the instance. Concurrent first analyses build equal results;
 * attribute instance identity holds once a first analysis has completed.
 *
 * Side effects: runs attribute constructors on the first analysis of a class;
 * later analyses return the same instances. The cache grows with the number
 * of classes and attribute classes in the process; clearCache() empties it.
 * An attribute that cannot be instantiated raises InspectionFailedException
 * (2503 for an \Error, 2506 for an \Exception) with the cause as previous.
 *
 * Example:
 *
 *     $analysis = (new AttributeAnalyzer())->analyze(SignUp::class, Sanitize::class);
 *
 * @internal Used by ReflectionPropertyInspector; not a public API (ADR-009).
 *
 * @see ReflectionPropertyInspector
 * @see https://www.php.net/manual/en/reflectionproperty.getattributes.php
 *
 * @since 3.0.0
 */
final class AttributeAnalyzer
{
    /** @var array<class-string, array<class-string, AnalyzedClass>> */
    private array $cache = [];

    /**
     * @param class-string $className
     * @param class-string $attributeClass
     */
    public function analyze(string $className, string $attributeClass): AnalyzedClass
    {
        return $this->cache[$className][$attributeClass] ??= $this->build($className, $attributeClass);
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * @param class-string $className
     * @param class-string $attributeClass
     */
    private function build(string $className, string $attributeClass): AnalyzedClass
    {
        $properties = [];

        foreach (new \ReflectionClass($className)->getProperties() as $property) {
            $declared = $property->getAttributes($attributeClass, \ReflectionAttribute::IS_INSTANCEOF);

            if ($property->isStatic() || [] === $declared) {
                continue;
            }

            $properties[] = new AnalyzedProperty($property->getName(), $property, $this->instantiate($property, $declared));
        }

        return new AnalyzedClass($properties);
    }

    /**
     * @param list<\ReflectionAttribute<object>> $declared
     *
     * @return list<object>
     */
    private function instantiate(\ReflectionProperty $property, array $declared): array
    {
        try {
            return array_map(static fn(\ReflectionAttribute $attribute): object => $attribute->newInstance(), $declared);
        } catch (\Exception $cause) {
            throw InspectionFailedException::attributeConstructorFailed($property->class, $property->getName(), $cause);
        } catch (\Error $cause) {
            throw InspectionFailedException::attributeNotInstantiable($property->class, $property->getName(), $cause);
        }
    }
}
