# ADR-005 — Symmetric Exception Handling in analyzeObject()

| Field | Value |
|---|---|
| Status | Accepted — 2026-09-28; amended in 3.0.0 by ADR-011 (2501 folded into 2506) |
| Component | kariricode/property-inspector 2.1 |
| Standards | KaririCode Spec V4.3.3 §8.4 |
| Related | ADR-003, ADR-004 |

## Context

`analyzeObject()` instantiates attributes with `ReflectionAttribute::newInstance()`, which propagates whatever the attribute constructor throws. The method caught only `\ReflectionException` (2501) and `\Error` (2503). An `\InvalidArgumentException` thrown by an attribute constructor therefore escaped unwrapped, breaking the `@throws PropertyInspectionException` contract. The inspection side already handled all three shapes (2502, 2504, 2505).

## Decision

Add a `catch (\Exception)` arm, between the reflection and error arms, and a factory `failedToAnalyzeObjectException()` with the new code **2506** (`GENERAL_ANALYSIS_EXCEPTION`). The change is additive: no existing code or factory changed.

## Alternatives Considered

- **Map it to 2503.** That code means `\Error`; reusing it would blur two failure classes that the codes already separate.
- **A single `catch (\Throwable)`.** It loses the reflection, exception and error distinction that codes 2501 and 2503 encode.

## Consequences

### Positive
- `analyzeObject()` honours its contract for every failure mode.

### Negative
- The numbering is not contiguous by phase (2506 belongs to analysis). Codes are stable identifiers, so existing ones were not renumbered.

## ARFA 1.3

- **P5:** attribute-construction failures get their own code.

## References

- PHP manual, `ReflectionAttribute::newInstance()` — https://www.php.net/manual/en/reflectionattribute.newinstance.php
- Tests in the 2.x line: `AttributeAnalyzerTest::testAnalyzeObjectWrapsAttributeConstructorExceptionAsCode2506` and `PropertyInspectionExceptionTest::testFailedToAnalyzeObjectExceptionWrapsException`.
- Tests in 3.0: `ReflectionPropertyInspectorTest::anAttributeThatCannotBeInstantiatedIsARuntimeFaultRaisedBeforeTheHandlerRuns` and `ExceptionModelTest`.
