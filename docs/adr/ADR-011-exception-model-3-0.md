# ADR-011 — Exception Model of 3.0

| Field | Value |
|---|---|
| Status | Accepted — 2026-10-27 |
| Component | kariricode/property-inspector 3.0 |
| Standards | KaririCode Spec V4.3.3 §8.4, §18, §31, E-047 · kariricode/contract 2.11.0 |
| Related | ADR-005 (amended), ADR-009, ADR-010 |

## Context

The 2.x exception had three problems.

1. **Sibling dependency.** `PropertyInspectionException` extended `AbstractException` from kariricode/exception, a dependency between sibling components that Spec §31 rules out.
2. **No family.** It belonged to none of the families of kariricode/contract 2.11.0.
3. **Leaking messages.** Every message repeated the cause's message, which can carry request data; Spec E-047 forbids that (THREAT-MODEL T3).

## Decision

1. **Marker interface.** `Exception\PropertyInspectionException` becomes a marker interface that extends `Contract\Exception\Runtime`. Callers catch the package's faults through it, the Runtime family, or the ecosystem root, `Contract\Exception\ExceptionInterface`.
2. **Two final classes.** Each extends `\RuntimeException` and implements the marker, so inheritance depth stays at 2:
   - `InspectionFailedException` for the inspector;
   - `ChangeRejectedException` for the applier.
3. **Named factories only.** Constructors are private.
4. **Messages.** Every message reads `[PI_<code>] <detail>`.
   - **Shown:** class and property names that come from code.
   - **Never shown:** the cause's message and rejected values. The cause stays reachable through `getPrevious()`.
   - **Names from a change set:** shown only when they are an ASCII identifier of at most 64 characters.
5. **Codes**, all in PI-2500–2599:

   | Code | Class | Meaning |
   |---|---|---|
   | 2503 | InspectionFailedException | an attribute cannot be instantiated (`\Error`) |
   | 2506 | InspectionFailedException | an attribute constructor threw an `\Exception` |
   | 2507 | InspectionFailedException | an annotated property is not initialized |
   | 2520 | ChangeRejectedException | no writable property has that name |
   | 2521 | ChangeRejectedException | the named property is static |
   | 2522 | ChangeRejectedException | the property rejects the value (`\Error`) |
   | 2523 | ChangeRejectedException | the object's class cannot be copied |

   - **Retired:** 2501, 2502, 2504 and 2505. 2501 folds into 2506, because a `ReflectionException` thrown by an attribute constructor is an `\Exception`; the other three follow from ADR-010.
   - **Changed in 3.0:** an uninitialized property was 2503 in 2.x and is now 2507.
6. **Dependency.** kariricode/exception leaves `require`.

## Alternatives Considered

- **Keep `AbstractException`.** The sibling dependency would remain.
- **Abstract base class under `\RuntimeException`.** The final classes would sit at depth 3 (base, `RuntimeException`, `Exception`), over the depth-2 rule.
- **One class with every code.** Callers could not catch the inspector's faults apart from the applier's without reading codes.

## Consequences

### Positive

- One family (`Runtime`), stable codes, and messages that never echo input.
- The contract is the only runtime dependency.

### Negative

- **Breaking change.** `PropertyInspectionException` changes from a class to an interface, and messages change.
- **Code changes.** Code that compared 2501, 2502, 2503, 2504 or 2505 must be updated (UPGRADE-3.0.md).

## ARFA 1.3

- **P5:** logs aggregate on stable codes and families.

## References

- KaririCode Specification V4.3.3 §8.4, E-047.
- kariricode/contract 2.11.0, `Exception\*`.
- OWASP Logging Cheat Sheet — https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html
- Tests:
  - `ExceptionModelTest`;
  - `SpecConformanceTest::testEveryExceptionIsFinalAndInExactlyOneContractFamily`.
