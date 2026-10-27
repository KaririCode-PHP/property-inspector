# ADR-009 — Ecosystem Contract Alignment for 3.0.0

| Field | Value |
|---|---|
| Status | Proposed — 2026-10-02 |
| Component | kariricode/property-inspector 3.0 |
| Standards | KaririCode Spec V4.3.3 §8.4, §18, §31, E-047 |
| Related | ADR-002, ADR-006, ADR-008 |

## Context

The 2.x line deviates from V4.3.3 in ways that cannot change without breaking consumers. The conformance suite lists each deviation exactly.

- **§8.4.** Exceptions must implement one family of kariricode/contract 2.11.0: `Validation`, `NotFound`, `Configuration`, `Protocol`, `ContainerFailure` or `Runtime`. `PropertyInspectionException` extends `AbstractException` of kariricode/exception instead.
- **§31.** Coupling between components goes only through the contract. This package depends on kariricode/exception, a sibling. Packagist, checked 2026-10-02, shows kariricode/sanitizer 1.2.3 requiring it directly (`^2.0`), as does kariricode/validator 1.0.0 (`^1.0`).
- **§18.** There are four parameterless getters and five array returns used as domain values, and `PropertyAccessor` is not final.
- **E-047.** Exception messages repeat the message of the cause.
- **Blocker.** kariricode/contract 2.11.0 is not on Packagist; the latest is 2.8.6.

## Decision

Once kariricode/contract 2.11.0 is tagged, release 3.0.0 with these changes:

1. `PropertyInspectionException` becomes an abstract base extending `\RuntimeException` and implementing `Contract\Exception\Runtime`. The kariricode/exception dependency goes away. Messages become `[PI_25xx] …` without data from the cause, which stays as previous.
2. Getters become interface properties (`{ get; }`), and the analysis result becomes Value Objects.
3. `PropertyAccessor` becomes final, and `Utility\` becomes `Core\`.
4. The copy-on-write applier of ADR-006 lands.
5. If KC Contract accepts the promotion, the four contracts move to kariricode/contract and this package implements them.
6. The P5 gap is resolved, either with PSR-14 events or with a documented deviation.

## Alternatives Considered

- **Do it in 2.x behind deprecations.** A parent class and a message format cannot be deprecated in place.
- **Each consumer keeps its own reflection.** The same analysis code would be duplicated across the DPO libraries.

## Consequences

### Positive
- Conformance with §8.4, §18 and §31, and consumers type against the contract.

### Negative
- A major release, and consumers must adapt.
- The error range PI-2500–2599 must be registered in the ecosystem registry before then.

## ARFA 1.3

- **P1:** copy-on-write change application.
- **P5:** observability decision pending.

## References

- Semantic Versioning 2.0.0 — https://semver.org/spec/v2.0.0.html
- kariricode/contract 2.11.0, migration table for consumers (its specification, §8.5).
