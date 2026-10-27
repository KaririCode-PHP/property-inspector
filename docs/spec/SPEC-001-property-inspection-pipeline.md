# SPEC-001 — Property Inspection Pipeline

| Field | Value |
|---|---|
| Component | kariricode/property-inspector 2.1.0 |
| Status | Final for 2.1.0 |
| Standards | KaririCode Spec V4.3.3 · ARFA 1.3 Level 1 · OWASP ASVS 5.0 Level 1 |
| Date | 2026-10-02 |

## 1. Purpose

Find the properties of an object that carry a given PHP attribute, read their current values, and hand each (property, attribute, value) triple to a processor-defined handler. Sanitizers, validators and transformers use it to drive their rules from declarations instead of mapping code.

## 2. Architecture

```
caller ── inspect($object, $handler) ──▶ Utility\PropertyInspector
                                            │
                                            ▼
                               AttributeAnalyzer::analyzeObject($object)
                               ├─ cache hit?  no → ReflectionClass::getProperties()
                               │                    getAttributes(class, IS_INSTANCEOF)
                               │                    newInstance() → cache per class
                               └─ read current values (never cached)
                                            │
                                            ▼
              foreach property, foreach attribute → $handler->handleAttribute(...)
                                            │
                                            ▼
                         handler (processor semantics, accumulates results)
                                            │  optional
                                            ▼
                         PropertyChangeApplier → Utility\PropertyAccessor (in place)
```

## 3. Contracts and Invariants

```php
interface AttributeAnalyzer {
    public function analyzeObject(object $object): array; // array<string, array{value: mixed, attributes: list<object>}>
    public function clearCache(): void;
}
interface PropertyInspector {
    public function inspect(object $object, PropertyAttributeHandler $handler): PropertyAttributeHandler;
}
interface PropertyAttributeHandler {
    public function handleAttribute(string $propertyName, object $attribute, mixed $value): mixed;
    public function getProcessedPropertyValues(): array;
    public function getProcessingResultMessages(): array;
    public function getProcessingResultErrors(): array;
}
interface PropertyChangeApplier {
    public function applyChanges(object $object): void;
}
```

| ID | Invariant | Verified by |
|---|---|---|
| INV-001 | Only properties carrying the target attribute are returned | `AttributeAnalyzerTest` |
| INV-002 | Matching uses `IS_INSTANCEOF` (interfaces and base classes match) | `testAnalyzeObjectSupportsIsInstanceOfFiltering` |
| INV-003 | `clearCache()` resets all cached metadata | `testAnalyzeObjectReusesAttributeInstancesUntilCacheIsCleared` |
| INV-004 | `analyzeObject()` never leaks a raw throwable (2501, 2503, 2506) | fail-closed group |
| INV-005 | `inspect()` calls the handler once per attribute instance per property | `PropertyInspectorTest` |
| INV-006 | `inspect()` returns the handler it received | `testInspectReturnsSameHandlerInstance` |
| INV-007 | An analyzer `PropertyInspectionException` propagates unchanged; handler failures are wrapped (2502, 2504, 2505) | ADR-004, fail-closed group |
| INV-008 | The handler receives the value at inspection time | `testAnalyzeObjectReflectsCurrentValueAfterMutation` |
| INV-009 | Handler results are keyed by property name | `InspectionPipelineTest` |
| INV-010 | `PropertyAccessor` writes any visibility, including `private(set)`; `set` hooks run; an initialized readonly property raises `\Error` | `PropertyAccessorTest` |
| INV-011 | Annotated typed properties must be initialized, otherwise 2503 | `testAnalyzeObjectWrapsUninitializedTypedPropertyAsCode2503` |
| INV-012 | Attribute instances are shared per class per analyzer and must not be mutated | `testAnalyzeObjectReusesAttributeInstancesUntilCacheIsCleared` |
| INV-013 | Property values are never cached | `testAnalyzeObjectReflectsCurrentValueAfterMutation` |

## 4. Caching

The cache key is `$object::class`, scoped to one analyzer instance. The entry holds the `ReflectionProperty` and the attribute instances of each matching property. Nothing evicts entries automatically; long-running workers call `clearCache()`.

## 5. Error Model

Every failure surfaces as `PropertyInspectionException`, with the cause kept as previous. The code range is declared in `composer.json` as `PI-2500–2599`; it is pending registration in the ecosystem registry.

| Code | Name | Phase | Cause |
|---|---|---|---|
| 2501 | REFLECTION_ANALYSIS_ERROR | analysis | `\ReflectionException` |
| 2502 | REFLECTION_INSPECTION_ERROR | inspection | `\ReflectionException` raised by the handler |
| 2503 | GENERAL_ANALYSIS_ERROR | analysis | `\Error`, e.g. an uninitialized typed property |
| 2504 | GENERAL_INSPECTION_ERROR | inspection | `\Exception` raised by the handler |
| 2505 | CRITICAL_INSPECTION_ERROR | inspection | `\Error` raised by the handler |
| 2506 | GENERAL_ANALYSIS_EXCEPTION | analysis | `\Exception`, e.g. an attribute constructor throws |

## 6. Ecosystem Integration

Checked on Packagist on 2026-10-02:

- kariricode/sanitizer 1.2.3 requires `^2.0`;
- kariricode/validator 1.0.0 requires `^1.0`.

Both are direct dependencies between sibling components, which Spec §31 rules out. ADR-009 plans the move to `kariricode/contract`.

## 7. Dependencies

| Package | Constraint | Use |
|---|---|---|
| php | ^8.4 | runtime |
| kariricode/contract | ^2.8 | none in `src/` yet; family exceptions in 3.0 (ADR-009) |
| kariricode/exception | ^1.2 | base class of `PropertyInspectionException` (deviation, ADR-009) |

## 8. Conformance

| Rule | Status |
|---|---|
| ARFA P1, immutable state | Partial: the analyzer cache is instance state; the applier mutates in place (ADR-006) |
| ARFA P4, protocol agnostic | Pass |
| ARFA P5, observability | Gap: no telemetry; decision pending (ADR-009) |
| §8.4 exception family | Deviation until contract 2.11.0 is tagged (ADR-009) |
| §18 getters, array returns, `object` parameters | Exact deviation lists in `SpecConformanceTest` (ADR-008, ADR-009) |
| §29, §31, §32 tooling and supply chain | Pass (ADR-007) |

## 9. Security

ASVS 5.0 Level 1 is declared. Threats and mitigations are in `docs/security/THREAT-MODEL.md`.

## 10. References

- KaririCode Specification V4.3.3; ARFA 1.3.
- ADR-001 to ADR-009 in `docs/adr/`.
