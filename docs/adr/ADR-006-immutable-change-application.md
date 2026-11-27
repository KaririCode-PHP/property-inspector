# ADR-006 — Copy-on-Write Change Application for Readonly DPOs

| Field | Value |
|---|---|
| Status | Accepted — 2026-10-27 (proposed 2026-10-02; implemented in 3.0.0) |
| Component | kariricode/property-inspector 3.0 |
| Standards | ARFA 1.3 P1 · KaririCode Spec V4.3.3 type taxonomy (a DPO is a readonly class) · kariricode/contract 2.12.0 `Inspection\PropertyChangeApplier` |
| Related | ADR-001, ADR-009, ADR-010 |

## Context

The 2.x `PropertyChangeApplier::applyChanges(object): void` wrote processed values back in place, through `Utility\PropertyAccessor`. The Spec models DPOs as readonly classes. On PHP 8.4, writing an initialized readonly property through reflection raises `Error: Cannot modify readonly property`. A sanitizer therefore could not update a conformant DPO, and in-place mutation contradicts P1, under which every transformation yields a new state.

kariricode/contract 2.12.0 defines `Inspection\PropertyChangeApplier::applyChanges(object $object, array $changes): object`. It returns a new instance and never writes to its argument.

## Decision

`Core\ReflectionChangeApplier` implements the contract as follows:

1. **Resolve names first.** Each key of the change set is resolved before anything is allocated. A name with no writable property raises 2520 (a parent's private property is not visible through the class), and a static property raises 2521.
2. **Allocate.** The copy comes from `ReflectionClass::newInstanceWithoutConstructor()`. An enum or an internal final class cannot be instantiated this way and raises 2523.
3. **Copy every storage slot exactly once.** A child's `getProperties()` omits its parents' private properties, so the hierarchy is walked. Private properties are copied per declaring class. A public or protected name is a single slot however many classes declare it, so the most derived declaration is copied once. Virtual properties have no storage and are skipped. Uninitialized properties stay uninitialized. Values are read and written raw (`getRawValue()` / `setRawValue()`), so set hooks do not run.
4. **Assign each change.** `setValue()` is used, so set hooks run. An `\Error` raised there (wrong type, readonly or read-only property) becomes 2522. An `\Exception` thrown by a hook propagates unchanged (ADR-010).

Measured on PHP 8.4.27:
- reflection may initialize an uninitialized readonly property from outside its class, and a second write raises `Error`;
- a child's `getProperties()` omits its parents' private properties;
- a readonly property redeclared by a subclass appears in both classes' `getProperties()` but is one slot.

A copy that writes level by level therefore initializes that slot twice and fails. The reference fixture in kariricode/contract 2.12.0 copies that way, while this implementation writes each slot once (`ReflectionChangeApplierTest::aPropertyRedeclaredByASubclassIsWrittenOnce`).

## Alternatives Considered

- **`clone` with reinitialization in `__clone()`** (PHP 8.3 readonly amendments). Every DPO would need its own `__clone()`.
- **Rebuild through the constructor with named arguments.** This works only when the constructor parameters mirror the property names, and it runs the constructor's side effects.
- **Keep in-place writes and forbid readonly DPOs.** This contradicts the Spec taxonomy.

## Consequences

### Positive

- Sanitize and transform passes work on readonly DPOs, and the original stays available for auditing (P1).

### Negative

- Constructor invariants are skipped. The pipeline must validate the copy after the rebuild (sanitize, then validate).
- Virtual (hook-only) properties have no storage: they are computed on the copy and cannot be copied.
- Objects whose state is not in declared properties, such as enums and internal classes, cannot be copied (2523).

## ARFA 1.3

- **P1:** the transformation produces a new state instead of mutating its input.

## References

- PHP RFC: Readonly properties 2.0 — https://wiki.php.net/rfc/readonly_properties_v2
- PHP RFC: Property hooks (`ReflectionProperty::setRawValue()`) — https://wiki.php.net/rfc/property-hooks
- PHP manual, `ReflectionClass::newInstanceWithoutConstructor()` — https://www.php.net/manual/en/reflectionclass.newinstancewithoutconstructor.php
- kariricode/contract 2.12.0, ADR-007 and SPEC-001 §2.4.
