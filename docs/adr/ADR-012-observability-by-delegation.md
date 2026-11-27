# ADR-012 — Observability (ARFA P5) by Delegation

| Field | Value |
|---|---|
| Status | Proposed — 2026-10-27 |
| Component | kariricode/property-inspector 3.0 |
| Standards | ARFA 1.3 P5 · KaririCode Spec V4.3.3 §29.3 |
| Related | ADR-009, ADR-010, ADR-011 |

## Context

ARFA Level 1 requires continuous observability (P5), and this package declares Level 1.

Inspection and copying are synchronous, in-memory reflection steps. They have no I/O and no state beyond a metadata cache, and they always run inside an operation of a processor (sanitize, validate, transform, serialize). That processor is the unit a trace or metric describes.

Sibling components meet P5 in two ways:
- they emit PSR-14 events for state changes (circuit-breaker transitions, rate-limiter decisions);
- or they decorate a port (`ObservedAuthenticator`).

## Decision (proposed)

The package emits no telemetry of its own. P5 is met by delegation.

1. **Failures reach the edge.** Every failure carries a stable code and the `Runtime` family (ADR-011), and handler exceptions keep their family (ADR-010). Failures are therefore logged and counted once, at the edge.
2. **The processor measures.** The processor that calls `inspect()` and `applyChanges()` records its own span or metric, and the inspection's time is part of it.
3. **Hosts can decorate.** A host that wants per-inspection metrics wraps the contract interfaces, which are public in kariricode/contract, with a decorator of its own. Nothing in this package has to change.

## Alternatives Considered

- **A PSR-14 event per inspection or copy.** This adds one dispatch per DPO on a hot path and a runtime dependency (`psr/event-dispatcher`). The events would duplicate the processor's own telemetry.
- **A metrics port in this package.** No consumer exists, and a port without a consumer is not added (kariricode/contract ADR-001).
- **An `ObservedPropertyInspector` decorator here.** It is possible later and stays compatible with this decision; it waits for a host that needs it.

## Consequences

### Positive

- No overhead on the hot path and no new dependency.

### Negative

- There are no ready-made metrics for inspection time or cache hits.
- QD-V433 records P5 as "met by delegation", pending acceptance of this ADR.

## ARFA 1.3

- **P5:** met through stable failure signals and the calling processor's telemetry.

## References

- PSR-14: Event Dispatcher — https://www.php-fig.org/psr/psr-14/
- OpenTelemetry, Traces: spans and their parent operations — https://opentelemetry.io/docs/concepts/signals/traces/
- ADR-010, ADR-011.
