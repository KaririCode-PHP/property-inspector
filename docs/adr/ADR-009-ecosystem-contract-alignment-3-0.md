# ADR-009 — Ecosystem Contract Alignment for 3.0.0

| Field | Value |
|---|---|
| Status | Accepted — 2026-10-27 (proposed 2026-10-02; implemented in 3.0.0) |
| Component | kariricode/property-inspector 3.0 |
| Standards | KaririCode Spec V4.3.3 §8.4, §18, §31, E-047 · kariricode/contract 2.12.0 (ADR-007) |
| Related | ADR-002, ADR-006, ADR-008, ADR-010, ADR-011, ADR-012 |

## Context

The 2.x line deviated from Spec V4.3.3 in ways that could not change without breaking consumers:

- **§31.** It depended on kariricode/exception, a sibling. Its own four contracts were required directly by kariricode/sanitizer 1.2.3 (`^2.0`) and kariricode/validator 1.0.0 (`^1.0`) on Packagist.
- **§8.4.** Its exception belonged to no family of kariricode/contract.
- **§18.** It had four parameterless getters and five array return values, and `PropertyAccessor` was not final.
- **E-047.** Its messages repeated the cause's message.

kariricode/contract 2.12.0 promoted three of the four contracts into `KaririCode\Contract\Inspection`, reshaped for the Spec (its ADR-007):
- `inspect()` takes the attribute class;
- the handler keeps only `handleAttribute(): void`;
- the applier returns a copy;
- handler exceptions propagate unchanged.

## Decision

3.0.0 implements the contract and drops what it replaces.

1. **Implementations.** `Core\ReflectionPropertyInspector` implements `Contract\Inspection\PropertyInspector`, and `Core\ReflectionChangeApplier` implements `Contract\Inspection\PropertyChangeApplier` (ADR-006). The processors' handlers implement `Contract\Inspection\PropertyAttributeHandler`.
2. **Removed.** The package's own `Contract\*` interfaces, the root `AttributeAnalyzer`, `Utility\PropertyInspector` and `Utility\PropertyAccessor`. Writes go through the applier, on a copy; reads go through the inspector.
3. **Analyzer.** `Core\AttributeAnalyzer` stays in this package as an internal class. No other package consumes it now that the attribute class is a parameter of `inspect()`. It caches per class and attribute class and returns Value Objects (`AnalyzedClass`, `AnalyzedProperty`) instead of arrays.
4. **Exceptions.** They follow ADR-011: the `Runtime` family, final classes, named factories and E-047 messages.
5. **Dependencies.** kariricode/exception is dropped. kariricode/contract `^2.12` is the only runtime dependency.
6. **§18 and layout.** There are no getters and no array return values. Every concrete class is final. `Utility\` becomes `Core\`.
7. **Handler exceptions.** They propagate unchanged (ADR-010).
8. **P5.** It is met by delegation (ADR-012, proposed).

The only deviation left is the `object` parameter of the two implementations (ADR-008).

## Alternatives Considered

- **Do it in 2.x behind deprecations.** A parent class and a message format cannot be deprecated in place, and the contract's new signatures are incompatible with the 2.x interfaces.
- **Each consumer keeps its own reflection.** The same analysis code would be duplicated across the data-processing libraries.
- **Promote `AttributeAnalyzer` too.** It has no consumer outside this package, so it does not enter the contract (kariricode/contract ADR-001).

## Consequences

### Positive

- Conformance with §8.4, §18 and §31. Consumers type against kariricode/contract only.

### Negative

- **A major release.** Consumers migrate as UPGRADE-3.0.md describes. The 2.x line keeps its interfaces.
- **Release blocked.** The release waits for kariricode/contract 2.12.0 on Packagist.
- **Error range.** PI-2500–2599 must still be registered in the ecosystem registry.

## ARFA 1.3

- **P1:** copy-on-write change application (ADR-006).
- **P5:** by delegation (ADR-012, proposed).

## References

- Semantic Versioning 2.0.0 — https://semver.org/spec/v2.0.0.html
- kariricode/contract 2.12.0: ADR-007, SPEC-001 §2.4 and the migration table in its §8.6.
- UPGRADE-3.0.md.
