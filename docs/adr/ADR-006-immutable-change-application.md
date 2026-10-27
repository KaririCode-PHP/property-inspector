# ADR-006 — Copy-on-Write Change Application for Readonly DPOs

| Field | Value |
|---|---|
| Status | Proposed — 2026-10-02 (target 3.0.0) |
| Component | kariricode/property-inspector |
| Standards | ARFA 1.3 P1 · KaririCode Spec V4.3.3 type taxonomy (DPO is readonly) |
| Related | ADR-001, ADR-009 |

## Context

`PropertyChangeApplier::applyChanges(object): void` writes processed values back in place. The Spec models DPOs as readonly classes. On PHP 8.4.23, writing an initialized readonly property through reflection raises `Error: Cannot modify readonly property`; `PropertyAccessorTest::testSetValueOnInitializedReadonlyPropertyThrowsError` pins that behaviour. A sanitizer can therefore not update a conformant DPO, and in-place mutation also contradicts P1 (every transformation yields a new state).

The same run verified two facts this proposal relies on:

- reflection may initialize an **uninitialized** readonly property from outside its class;
- `set` hooks run on reflection writes.

## Decision

In 3.0.0, add a copy-on-write path that returns a new object:

1. `ReflectionClass::newInstanceWithoutConstructor()`;
2. initialize every non-virtual property with `ReflectionProperty::setValue()`, using the processed value when present and the original value otherwise;
3. return the new instance and leave the original untouched.

## Alternatives Considered

- **`clone` with reinitialization in `__clone()`** (PHP 8.3 readonly amendments). Every DPO would need its own `__clone()`.
- **Rebuild through the constructor with named arguments.** This works only when constructor parameters mirror property names.
- **Keep in-place writes and forbid readonly DPOs.** This contradicts the Spec taxonomy.

## Consequences

### Positive
- Sanitize and transform passes work on readonly DPOs, and the original stays available for auditing (P1).

### Negative
- Constructor invariants are skipped. The pipeline must validate after the rebuild, as Sanitize → Validate already does.
- Virtual (hook-only) properties have no storage and are skipped.

## ARFA 1.3

- **P1:** the transformation produces a new state instead of mutating the input.

## References

- PHP RFC: Readonly properties 2.0 — https://wiki.php.net/rfc/readonly_properties_v2
- PHP RFC: Readonly amendments — https://wiki.php.net/rfc/readonly_amendments
- PHP manual, `ReflectionClass::newInstanceWithoutConstructor()` — https://www.php.net/manual/en/reflectionclass.newinstancewithoutconstructor.php
