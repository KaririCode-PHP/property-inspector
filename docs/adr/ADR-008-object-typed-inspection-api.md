# ADR-008 — Object-Typed Parameters on the Inspection API

| Field | Value |
|---|---|
| Status | Accepted — 2026-10-02 (signatures updated for 3.0.0 on 2026-10-27) |
| Component | kariricode/property-inspector 3.0 |
| Standards | KaririCode Spec V4.3.3 §18, §31 |
| Related | ADR-009 |

## Context

The ecosystem conformance suites reject `object` parameters, because `object` was how components coupled to each other by duck typing. This component's domain, though, is arbitrary objects. `analyzeObject()`, `inspect()`, `applyChanges()` and the `PropertyAccessor` constructor accept any object, and `handleAttribute()` receives attribute instances of any class.

## Decision

Keep `object` on the two implementations, `ReflectionPropertyInspector::inspect($object)` and `ReflectionChangeApplier::applyChanges($object)`. The contract interfaces they implement, and `PropertyAttributeHandler::handleAttribute($attribute)`, live in kariricode/contract 2.12.0 with the same types. `SpecConformanceTest::OBJECT_PARAMETER_DEVIATIONS` pins the exact list, so any new `object` parameter fails the build.

## Alternatives Considered

- **A marker interface for inspectable objects.** Every DPO and entity would have to implement a framework type.
- **`mixed`.** Weaker than `object`.
- **`@template T of object` generics.** These are compatible with this decision and add static precision without changing runtime types; a candidate for 3.0.

## Consequences

### Positive
- The type states the real contract: any object.

### Negative
- Until generics are added, static analysis cannot relate an input type to an output type.

## ARFA 1.3

- **P4:** inspection works on any object, whatever transport produced it.

## References

- PHP RFC: object typehint — https://wiki.php.net/rfc/object-typehint
