# Security Policy

Report vulnerabilities privately to community@kariricode.org, not in a public issue. Include the affected version, a description and, if possible, a reproduction.

**Supported versions:** the latest 3.x release. The 2.x line receives security fixes until consumers have migrated (UPGRADE-3.0.md).

Security-relevant behaviour:

- **Reflection.** The inspector reads non-public properties by design and never writes. The applier writes only a new instance. Build change keys from your own property names, never from request field names.
- **Copies.** The copy's constructor does not run, so validate the copy before trusting it.
- **Exceptions.**
  - The package's own failures are `Runtime` faults with stable codes.
  - Messages never contain the cause's message or a rejected value (E-047).
  - Exceptions from your handler or your property hooks propagate unchanged.

Threat model: [docs/security/THREAT-MODEL.md](docs/security/THREAT-MODEL.md). Quality and supply-chain status: [docs/quality/QD-V433-COMPLIANCE.md](docs/quality/QD-V433-COMPLIANCE.md).
