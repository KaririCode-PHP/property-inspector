<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Utility;

use KaririCode\PropertyInspector\Contract\AttributeAnalyzer;
use KaririCode\PropertyInspector\Contract\PropertyAttributeHandler;
use KaririCode\PropertyInspector\Contract\PropertyInspector as PropertyInspectorContract;
use KaririCode\PropertyInspector\Exception\PropertyInspectionException;

/**
 * Drives attribute-based processing of an object: asks an AttributeAnalyzer
 * which properties carry the target attribute, then hands every
 * (property name, attribute instance, current value) triple to a handler.
 *
 * This is the orchestration seam of the DPO preparation pipeline
 * (Sanitizer -> Validator -> Transformer): processing semantics live entirely
 * in the handler, so one inspector serves any attribute-driven processor.
 *
 * Side effects: none of its own. The handler receives one call per attribute
 * instance per property, in the order returned by
 * ReflectionClass::getProperties() (own properties first, then inherited),
 * and may accumulate state. The same handler instance is returned.
 *
 * Failures are fail-closed: every failure surfaces as
 * PropertyInspectionException. An exception already raised by the analyzer
 * propagates unchanged, keeping its original code (ADR-004); anything thrown
 * by the handler is wrapped as 2502, 2504 or 2505.
 *
 * Example:
 *
 *     $inspector = new PropertyInspector(new AttributeAnalyzer(Sanitize::class));
 *     $handler = $inspector->inspect($dpo, new SanitizeHandler());
 *     $sanitized = $handler->getProcessedPropertyValues();
 *
 * Thread-safety: holds no state besides the injected analyzer; it is as safe
 * to share as that analyzer is.
 *
 * @see docs/ADR-004-prevent-exception-double-wrapping.md
 * @see docs/SPEC-001-property-inspection-pipeline.md
 * @since 1.0.0
 */
final class PropertyInspector implements PropertyInspectorContract
{
    public function __construct(private readonly AttributeAnalyzer $attributeAnalyzer) {}

    #[\Override]
    public function inspect(object $object, PropertyAttributeHandler $handler): PropertyAttributeHandler
    {
        try {
            $analysisResults = $this->attributeAnalyzer->analyzeObject($object);
            foreach ($analysisResults as $propertyName => $propertyData) {
                foreach ($propertyData['attributes'] as $attribute) {
                    $handler->handleAttribute($propertyName, $attribute, $propertyData['value']);
                }
            }

            return $handler;
        } catch (PropertyInspectionException $e) {
            // Already wrapped by the analyzer: re-throw as-is so the original
            // code (2501/2503/2506) reaches the caller (ADR-004).
            throw $e;
        } catch (\ReflectionException $e) {
            throw PropertyInspectionException::failedToInspectObjectReflection($e);
        } catch (\Exception $e) {
            throw PropertyInspectionException::failedToInspectObjectException($e);
        } catch (\Error $e) {
            throw PropertyInspectionException::failedToInspectObjectError($e);
        }
    }
}
