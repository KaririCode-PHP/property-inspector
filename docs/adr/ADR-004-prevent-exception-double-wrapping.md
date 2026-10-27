# ADR-004 — Prevent Exception Double-Wrapping in PropertyInspector

| Field | Value |
|---|---|
| Status | Accepted — 2026-09-28 |
| Component | kariricode/property-inspector 2.1 |
| Standards | KaririCode Spec V4.3.3 §8.4 |
| Related | ADR-003, ADR-005 |

## Context

`PropertyInspector::inspect()` calls `analyzeObject()`, which already throws `PropertyInspectionException` with an analysis code (2501, 2503 or 2506). That exception extends `\Exception`, so the generic `catch (\Exception)` in `inspect()` wrapped it again as 2504. The caller saw `GENERAL_INSPECTION_ERROR`, and the real code was buried one level down in `getPrevious()`.

## Decision

Add `catch (PropertyInspectionException $e) { throw $e; }` as the first catch arm of `inspect()`. Order matters, because `PropertyInspectionException` and `\ReflectionException` both precede the generic `\Exception` arm.

## Alternatives Considered

- **An `instanceof` check inside the generic arm.** It has the same effect, but the special case becomes less visible.
- **Wrapping only in the inspector.** The analyzer would throw raw exceptions, which breaks its contract (ADR-003).

## Consequences

### Positive
- The analysis code reaches the caller intact.
- Handler failures are still wrapped as 2502, 2504 or 2505.

### Negative
- Callers that relied on always receiving 2504 for analysis failures now receive the precise code.

## ARFA 1.3

- **P5:** the error code in logs identifies the failing phase.

## References

- PHP manual, Exceptions: the first matching catch block wins — https://www.php.net/manual/en/language.exceptions.php
- Test: `PropertyInspectorTest::testInspectPropagatesAnalyzerExceptionWithoutDoubleWrapping`.
