# SPEC-001 — Property Inspection Pipeline

| Field | Value |
|---|---|
| Component | kariricode/property-inspector 3.0.0 |
| Status | Final for 3.0.0 |
| Standards | KaririCode Spec V4.3.3 · ARFA 1.3 Level 1 · OWASP ASVS 5.0 Level 1 · kariricode/contract 2.12.0 |
| Date | 2026-10-27 |

## 1. Purpose

Implement the property inspection contracts of kariricode/contract (`KaririCode\Contract\Inspection`).

- **Inspection.** Find the properties of an object that carry an attribute of a given class, read their current values, and hand each (property, attribute, value) triple to a processor's handler.
- **Change application.** Write processed values back to a copy of the object, so readonly DPOs work.

Sanitizers, validators, transformers and serializers drive their rules from these declarations instead of mapping code, and depend only on the contract.

## 2. Architecture

```
processor ── inspect($dpo, Sanitize::class, $handler) ──▶ Core\ReflectionPropertyInspector
                                                            │ (Contract\Inspection\PropertyInspector)
                                                            ▼
                                       Core\AttributeAnalyzer (internal, cached per class × attribute class)
                                       ├─ getProperties() → getAttributes(class, IS_INSTANCEOF)
                                       └─ newInstance()   → AnalyzedClass / AnalyzedProperty
                                                            │
                                    read every value first (uninitialized → 2507), then
                                                            ▼
              handler->handleAttribute(name, attribute, value)   ← processor's PropertyAttributeHandler
                                                            │
processor ── applyChanges($dpo, $changes) ───────────────▶ Core\ReflectionChangeApplier
                                                            │ (Contract\Inspection\PropertyChangeApplier)
                                                            ▼
                                 new instance without constructor · every slot copied once · changes assigned
```

## 3. Contracts and Invariants

The contracts are defined in kariricode/contract 2.12.0:

```php
namespace KaririCode\Contract\Inspection;
interface PropertyInspector       { public function inspect(object $object, string $attributeClass, PropertyAttributeHandler $handler): void; }
interface PropertyAttributeHandler { public function handleAttribute(string $propertyName, object $attribute, mixed $value): void; }
interface PropertyChangeApplier   { public function applyChanges(object $object, array $changes): object; } // @template T: T in, T out
```

| ID | Invariant | Verified by |
|---|---|---|
| INV-001 | Only properties carrying an attribute that is an instance of the requested class reach the handler | `everyMatchingAttributeReachesTheHandlerInDeclarationOrder` |
| INV-002 | Matching uses `IS_INSTANCEOF`: an interface or base class matches every implementer | `anInterfaceMatchesEveryAttributeThatImplementsIt` |
| INV-003 | The object's own properties of any visibility, then inherited public and protected ones; never a parent's private property; never a static property | `inheritedProtectedPropertiesAreInspectedButAParentsPrivateOnesAreNot`, `staticPropertiesAreNotInspected` |
| INV-004 | One `handleAttribute()` per matching attribute instance, a property's attributes in declaration order | `everyMatchingAttributeReachesTheHandlerInDeclarationOrder` |
| INV-005 | The handler receives the value held during the call; values are never cached; a virtual property is read through its get hook | `theHandlerReceivesTheValueHeldDuringTheCall`, `aVirtualPropertyIsReadThroughItsGetHook` |
| INV-006 | Inspection never writes to the object | `inspectingNeverWritesToTheObject` |
| INV-007 | The inspection's own failures (2503, 2506, 2507) are raised before the handler runs | `anAttributeThatCannotBeInstantiatedIsARuntimeFaultRaisedBeforeTheHandlerRuns`, `anUninitializedAnnotatedPropertyIsARuntimeFault` |
| INV-008 | An exception thrown by the handler, or by the object's hooks, propagates unchanged (ADR-010) | `anExceptionFromTheHandlerKeepsItsFamily`, `anExceptionFromASetHookPropagatesUnchanged` |
| INV-009 | Analysis is cached per class and attribute class; attribute instances are shared until `clearCache()` and must not be changed | `attributeInstancesAreSharedUntilTheCacheIsCleared`, `eachAttributeClassIsAnalyzedOnItsOwn` |
| INV-010 | `applyChanges()` returns a new instance, also for an empty change set, and never writes to its argument | `anEmptyChangeSetStillYieldsANewEqualInstance`, `theCopyHoldsTheChangesAndTheReadonlyOriginalIsUntouched` |
| INV-011 | Unchanged state is copied as stored, without set hooks, every storage slot exactly once: a parent's private properties included, a redeclared property once, virtual properties skipped | `unchangedStateIsCopiedIncludingAParentsPrivateProperties`, `aPropertyRedeclaredByASubclassIsWrittenOnce`, `aVirtualPropertyIsComputedOnTheCopyNotCopied` |
| INV-012 | Changes are assigned, so set hooks run; an uninitialized property stays uninitialized unless changed | `aChangeRunsTheSetHookAndACopiedValueDoesNot`, `anUninitializedPropertyStaysUninitializedUnlessChanged` |
| INV-013 | Names are resolved before anything is written; a rejected change set raises instead of returning a partial copy | `namesAreResolvedBeforeAnyValueIsWritten`, `anUnknownPropertyIsARuntimeFault` |

## 4. Caching

- **Key.** The cache is keyed by `[$object::class][$attributeClass]` and scoped to one `AttributeAnalyzer`. `ReflectionPropertyInspector` builds its own analyzer unless one is injected.
- **Entry.** Each entry holds the `ReflectionProperty` and the attribute instances of each matching property. Values are read on every call.
- **Eviction.** None is automatic. The cache grows with the classes and attribute classes a process inspects, which is bounded by its code. `ReflectionPropertyInspector::clearCache()` empties it.

## 5. Error Model

- **Package failures.** Every failure the package raises implements `Exception\PropertyInspectionException`, which extends `Contract\Exception\Runtime` (ADR-011).
- **Messages.** They read `[PI_<code>] <detail>`. They never contain the cause's message or a rejected value; the cause stays available as previous (E-047).
- **Range.** Declared in `composer.json` as `PI-2500–2599`; pending registration in the ecosystem registry.

| Code | Name | Raised by | Cause |
|---|---|---|---|
| 2503 | ATTRIBUTE_NOT_INSTANTIABLE | InspectionFailedException | an attribute cannot be instantiated (missing class, not an attribute, wrong target, bad arguments) |
| 2506 | ATTRIBUTE_CONSTRUCTOR_FAILED | InspectionFailedException | an attribute constructor threw an `\Exception` |
| 2507 | UNINITIALIZED_PROPERTY | InspectionFailedException | a property carrying a matching attribute is not initialized |
| 2520 | UNKNOWN_PROPERTY | ChangeRejectedException | a change names no writable property (a parent's private property included) |
| 2521 | STATIC_PROPERTY | ChangeRejectedException | a change names a static property |
| 2522 | VALUE_REJECTED | ChangeRejectedException | the assignment raised an `\Error` (type, readonly, read-only) |
| 2523 | NOT_COPYABLE | ChangeRejectedException | the class cannot be instantiated without its constructor (enum, internal final class) |

**Retired in 3.0.0:**
- **2501** is folded into 2506.
- **2502, 2504 and 2505** were handler failures; handler exceptions now propagate unchanged (ADR-010).
- **Uninitialized property.** It was 2503 in 2.x and is now 2507.

## 6. Ecosystem Integration

| Package | Role | Through |
|---|---|---|
| kariricode/contract 2.12.0 | defines the three interfaces | `Contract\Inspection\*` |
| sanitizer, transformer, serializer, validator | consume `PropertyInspector` and `PropertyChangeApplier`; their handlers implement `PropertyAttributeHandler` | the contract only (Spec §31) |

Migration from 2.x: UPGRADE-3.0.md. Today's dependents, checked on Packagist on 2026-10-27, still require the 2.x line: kariricode/sanitizer 1.2.3 (`^2.0`) and kariricode/validator 1.0.0 (`^1.0`).

## 7. Dependencies

| Package | Constraint | Use |
|---|---|---|
| php | ^8.4 | runtime: `ReflectionProperty::getRawValue()`, `setRawValue()` and `isVirtual()` |
| kariricode/contract | ^2.12 | the implemented interfaces and the `Runtime` family |

## 8. Conformance

| Rule | Status |
|---|---|
| ARFA P1, immutable state | Pass: copy-on-write applier (ADR-006); the analyzer cache is the only instance state |
| ARFA P4, protocol agnostic | Pass |
| ARFA P5, observability | By delegation (ADR-012, proposed) |
| §8.4 exception family | Pass (ADR-011) |
| §18 getters, array returns, final classes | Pass |
| §18 `object` parameters | Deviation, accepted (ADR-008): the two implementations |
| §29, §31, §32 tooling and supply chain | Pass (ADR-007) |

## 9. Security

ASVS 5.0 Level 1 is declared. Threats and mitigations are in `docs/security/THREAT-MODEL.md`.

## 10. References

- KaririCode Specification V4.3.3; ARFA 1.3.
- kariricode/contract 2.12.0: ADR-007, SPEC-001 §2.4.
- ADR-001 to ADR-012 in `docs/adr/`.
