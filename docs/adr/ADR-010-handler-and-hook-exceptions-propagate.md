# ADR-010 — Handler and Hook Exceptions Propagate Unchanged

| Field | Value |
|---|---|
| Status | Accepted — 2026-10-27 |
| Component | kariricode/property-inspector 3.0 |
| Standards | KaririCode Spec V4.3.3 §8.4 · kariricode/contract 2.12.0 (ADR-007, SPEC-001 §2.4) |
| Related | ADR-004 (superseded), ADR-011 |

## Context

In 2.x, `PropertyInspector::inspect()` wrapped every throwable raised by the handler into `PropertyInspectionException` (2502, 2504, 2505); ADR-004 only kept the analyzer's own codes from being wrapped twice.

Since kariricode/contract 2.11.0, every exception of the ecosystem belongs to one family (`Validation`, `NotFound`, `Configuration`, `Protocol`, `ContainerFailure`, `Runtime`), and the edge maps the family to a status. A handler that rejects a value throws a `Validation`. Wrapped into this package's exception, it would reach the edge as a fault of the inspector, a 500 instead of a 422.

kariricode/contract 2.12.0 settles the point in the inspection contract: an exception thrown by the handler propagates unchanged, so its family still reaches the edge.

## Decision

1. `ReflectionPropertyInspector::inspect()` calls the handler outside any `try`. Whatever the handler throws reaches the caller as it was thrown.
2. The same rule covers code the inspected object owns. An exception thrown by a property's get hook while the inspector reads it, or by a set hook while the applier assigns a change, propagates unchanged.
3. One exception to rule 2: an `\Error` raised while the applier assigns a value is the applier's own failure, `ChangeRejectedException` 2522 with the cause as previous. This covers a type mismatch, a readonly or read-only property, and a get-only virtual property. The contract requires a value the property's type rejects to surface in the `Runtime` family.
4. Only the package's own failures are wrapped (ADR-011):
   - an attribute that cannot be instantiated (2503, 2506);
   - an uninitialized annotated property (2507);
   - the applier's name resolution and copy (2520–2523).
5. Codes 2502, 2504 and 2505 are retired.

## Alternatives Considered

- **Keep wrapping, as 2.x did.** This contradicts the contract and turns a 422 into a 500 at the edge.
- **Wrap, and copy the cause's family onto the wrapper.** A class cannot implement whatever family the cause happens to have; it would need one wrapper class per family.
- **Also catch `\Exception` around assignments.** A set hook that validates (`InvalidArgumentException` for an empty name) would become a `Runtime` fault, the same loss of family.

## Consequences

### Positive

- A handler's `Validation` reaches the edge as a 422.
- One rule for all code that is not this package's: it is not wrapped.

### Negative

- `inspect()` no longer throws a single type. Callers catch their own handler's exceptions plus `PropertyInspectionException`.
- An `\Error` thrown inside a set hook's body is reported as 2522, the same code as a type mismatch at the assignment. The cause, kept as previous, tells them apart.

## ARFA 1.3

- **P5:** the exception's family survives to the edge, where it is logged and mapped once.

## References

- kariricode/contract 2.12.0: ADR-007, SPEC-001 §2.4 (`anExceptionFromTheHandlerKeepsItsFamily`).
- PHP RFC: Property hooks — https://wiki.php.net/rfc/property-hooks
- Tests:
  - `ReflectionPropertyInspectorTest::anExceptionFromTheHandlerKeepsItsFamily`;
  - `ReflectionChangeApplierTest::anExceptionFromASetHookPropagatesUnchanged`;
  - `ReflectionChangeApplierTest::aVirtualPropertyWithoutASetHookRejectsAChange`.
