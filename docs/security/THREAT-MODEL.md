# Threat Model — kariricode/property-inspector

| Field | Value |
|---|---|
| Version | 2.1.0 |
| Assurance | OWASP ASVS 5.0 Level 1 (declared in `composer.json`) |
| Date | 2026-10-02 |

## Scope and trust boundaries

The component runs inside the application process; it has no network, file or database access. The trust boundaries are:

- **Caller → inspector.** Code supplies the object and the attribute class.
- **Object values.** Values may hold untrusted user input.
- **Attribute classes.** Their constructors run when attributes are instantiated.

## Threats

| ID | Threat | Mitigation | Residual |
|---|---|---|---|
| T1 | Reflection bypasses encapsulation: private state is read and written | Property names come from attributes in code, never from input; documented on `PropertyAccessor` | Misuse by a caller that builds names from input |
| T2 | Code runs when attributes are instantiated | Only autoloadable classes declared in code are instantiated; failures are wrapped (2506) | Inspecting objects whose class is chosen by input |
| T3 | Exception messages repeat the cause message, which may contain user data | Callers must not show messages to end users (generic message plus correlation ID) | Fixed in 3.0 (E-047, ADR-009) |
| T4 | Unbounded cache growth in long-running workers | `clearCache()` | Callers must schedule it |
| T5 | Supply chain tampering | Committed lock, `composer audit`, CycloneDX SBOM, SHA-pinned CI, signed tags | Signing happens at release time |

## Pending

The mapping of ASVS 5.0 Level 1 requirement IDs to these controls is not written yet.
