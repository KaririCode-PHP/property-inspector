# Threat Model — kariricode/property-inspector

| Field | Value |
|---|---|
| Version | 3.0.0 |
| Assurance | OWASP ASVS 5.0 Level 1 (declared in `composer.json`) |
| Date | 2026-10-27 |

## Scope and trust boundaries

The component runs inside the application process and has no network, file or database access. Its trust boundaries:

- **Caller → inspector and applier.** Code supplies the object, the attribute class and the change set.
- **Object values.** They may hold untrusted user input.
- **Attribute classes.** Their constructors run when attributes are instantiated.
- **Change sets.** Their keys may come from a processor that maps request fields.

## Threats

| ID | Threat | Mitigation | Residual |
|---|---|---|---|
| T1 | Reflection bypasses encapsulation: private state is read, and a copy is written | Inspection never writes; the applier writes only a new instance, never the original; static properties are rejected (2521) | A caller that builds change keys from input can set any instance property of the copy |
| T2 | Code runs when attributes are instantiated | Only classes declared in code are instantiated; failures are wrapped (2503, 2506) | Inspecting objects whose class is chosen by input |
| T3 | Exception messages leak input | Messages never contain the cause's message or a rejected value; a change-set key appears only when it is an ASCII identifier of at most 64 characters (E-047, ADR-011) | None known |
| T4 | Unbounded cache growth in long-running workers | The cache is keyed by class and attribute class, bounded by the code loaded in the process; `clearCache()` | Processes that generate classes at runtime must clear it |
| T5 | Supply chain tampering | Committed lock, `composer audit`, CycloneDX SBOM, SHA-pinned CI, signed tags | Signing happens at release time |
| T6 | A copy skips constructor invariants | Documented on the contract and the applier: validate the copy, not the original (sanitize, then validate) | A processor that returns the copy unvalidated |

## Pending

The mapping of ASVS 5.0 Level 1 requirement IDs to these controls is not written yet.
