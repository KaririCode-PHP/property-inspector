# ADR-003 — Correct @throws on the AttributeAnalyzer Contract

| Field | Value |
|---|---|
| Status | Accepted — 2026-02-28 (revised 2026-10-02) |
| Component | kariricode/property-inspector 2.0 |
| Standards | KaririCode Spec V4.3.3 §8.4 (error model) |
| Related | ADR-004, ADR-005 |

## Context

`Contract\AttributeAnalyzer::analyzeObject()` documented `@throws \ReflectionException`, but the implementation wraps reflection failures in `PropertyInspectionException`. Callers following the docblock caught a type that never arrived.

## Decision

Document `@throws PropertyInspectionException`. The original throwable stays available through `getPrevious()`.

## Alternatives Considered

- **Let `\ReflectionException` escape.** This would leak an implementation detail; callers would depend on reflection types.
- **Document both types.** This would be inaccurate, because only one type is thrown.

## Consequences

### Positive
- Callers catch one type and branch on its stable code (SPEC-001 §5).

### Negative
- Code that caught `\ReflectionException` from `analyzeObject()` must catch `PropertyInspectionException`.

## ARFA 1.3

- **P5:** one exception type with a stable numeric code gives logs and metrics a single dimension to aggregate on.

## References

- phpDocumentor, `@throws` tag — https://docs.phpdoc.org/guide/references/phpdoc/tags/throws.html
