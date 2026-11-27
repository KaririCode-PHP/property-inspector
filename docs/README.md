# Documentation — kariricode/property-inspector

| Document | Purpose | Status |
|---|---|---|
| [SPEC-001](spec/SPEC-001-property-inspection-pipeline.md) | Pipeline, contracts, invariants, error codes | Final for 3.0.0 |
| [UPGRADE-3.0](../UPGRADE-3.0.md) | Migration from 2.x | Current |
| [ADR-001](adr/ADR-001-remove-setaccessible-dead-code.md) | Remove `setAccessible()` dead code | Accepted |
| [ADR-002](adr/ADR-002-add-clearcache-to-interface.md) | `clearCache()` on the contract | Superseded by ADR-009 |
| [ADR-003](adr/ADR-003-correct-throws-annotation.md) | Correct `@throws` on the contract | Accepted |
| [ADR-004](adr/ADR-004-prevent-exception-double-wrapping.md) | No double-wrapping in `inspect()` | Superseded by ADR-010 |
| [ADR-005](adr/ADR-005-symmetric-analysis-exception-handling.md) | Code 2506 for analysis exceptions | Accepted, amended by ADR-011 |
| [ADR-006](adr/ADR-006-immutable-change-application.md) | Copy-on-write change application | Accepted |
| [ADR-007](adr/ADR-007-quality-toolchain-and-supply-chain.md) | Quality toolchain and supply chain | Accepted |
| [ADR-008](adr/ADR-008-object-typed-inspection-api.md) | `object` parameters on the API | Accepted |
| [ADR-009](adr/ADR-009-ecosystem-contract-alignment-3-0.md) | Implement kariricode/contract 2.12 in 3.0.0 | Accepted |
| [ADR-010](adr/ADR-010-handler-and-hook-exceptions-propagate.md) | Handler and hook exceptions propagate unchanged | Accepted |
| [ADR-011](adr/ADR-011-exception-model-3-0.md) | Exception model of 3.0 | Accepted |
| [ADR-012](adr/ADR-012-observability-by-delegation.md) | Observability (P5) by delegation | Proposed |
| [QD-V433](quality/QD-V433-COMPLIANCE.md) | Gate results | Pending release |
| [Threat model](security/THREAT-MODEL.md) | Threats and mitigations | Current |
