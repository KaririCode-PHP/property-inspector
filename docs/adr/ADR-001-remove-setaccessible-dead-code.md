# ADR-001 — Remove setAccessible() Dead Code

| Field | Value |
|---|---|
| Status | Accepted — 2026-02-28 (revised 2026-10-02) |
| Component | kariricode/property-inspector 2.0 |
| Standards | KaririCode Spec V4.3.3 (code aesthetics: no dead weight) |
| Related | ADR-006 |

## Context

`PropertyAccessor` saved and restored accessibility around every read and write (`$wasAccessible`, `makeAccessible()`, `restoreAccessibility()`), and `AttributeAnalyzer` called `setAccessible(true)` while caching metadata. Since PHP 8.1, `ReflectionProperty::setAccessible()` does nothing: reflection reads and writes properties of any visibility. The package requires PHP ^8.4.

## Decision

Remove every `setAccessible()` call and the save/restore machinery. The public API of `PropertyAccessor` (`getValue()`, `setValue()`) is unchanged.

## Alternatives Considered

- **Keep the calls for older PHP versions.** Not applicable: the minimum version is 8.4.
- **Keep them as documentation of intent.** The restore step never re-restricted access, so the code described behaviour that did not exist.

## Consequences

### Positive
- `PropertyAccessor` went from 47 to 32 lines and lost its only mutable flag.

### Negative
- None on supported PHP versions.

## ARFA 1.3

- **P1:** removing the flag left `PropertyAccessor` with no internal state besides its constructor arguments.

## References

- PHP RFC: Make Reflection setAccessible() no-op — https://wiki.php.net/rfc/make-reflection-setaccessible-no-op
