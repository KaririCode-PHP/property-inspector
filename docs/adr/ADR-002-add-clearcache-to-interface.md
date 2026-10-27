# ADR-002 — Expose clearCache() on the AttributeAnalyzer Contract

| Field | Value |
|---|---|
| Status | Superseded in 3.0.0 by ADR-009 — accepted 2026-02-28; `clearCache()` now lives on `ReflectionPropertyInspector` |
| Component | kariricode/property-inspector 2.0 |
| Standards | KaririCode Spec V4.3.3 §31 (depend on contracts) |
| Related | ADR-009 |

## Context

The concrete `AttributeAnalyzer` exposed `clearCache()`, but `Contract\AttributeAnalyzer` did not. Code holding the contract could not bound the reflection cache without downcasting to the implementation. Long-running workers (ARFA Level 2) need that to cap memory.

The first version of this ADR justified the change with the Interface Segregation Principle. That was wrong: adding a method to an interface works against ISP. The actual reason is dependency inversion — callers must reach the capability through the abstraction.

## Decision

Add `clearCache(): void` to `Contract\AttributeAnalyzer` (released in 2.0.0).

## Alternatives Considered

- **A separate `ClearableCache` contract.** Purer under ISP, but every analyzer in the ecosystem caches reflection metadata, so the extra type would add ceremony without a client that needs the split.
- **Leave it on the concrete class.** Forces downcasting and couples callers to the implementation.

## Consequences

### Positive
- Callers typed against the contract can clear the cache.

### Negative
- Every implementer must provide the method (a no-op when nothing is cached). This was a breaking change, shipped in the 2.0.0 major.

## ARFA 1.3

- **P1:** the cache is the analyzer's only mutable state, and `clearCache()` is the only way to reset it.

## References

- Robert C. Martin, "The Dependency Inversion Principle", C++ Report, 1996.
- PSR-6 `CacheItemPoolInterface::clear()` and PSR-16 `CacheInterface::clear()` as precedent.
