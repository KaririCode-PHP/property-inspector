# Security Policy

Report vulnerabilities privately to community@kariricode.org, not in a public issue. Include the affected version, a description and, if possible, a reproduction.

**Supported versions:** the latest 2.x release.

Security-relevant behaviour:

- **Reflection.** It reads and writes non-public properties by design. Property names must come from code, never from request data.
- **Exceptions.** Every failure surfaces as `PropertyInspectionException`, so no raw throwable escapes (fail closed). Messages still include the cause message until 3.0, so do not show them to end users.

Threat model: [docs/security/THREAT-MODEL.md](docs/security/THREAT-MODEL.md). Quality and supply-chain status: [docs/quality/QD-V433-COMPLIANCE.md](docs/quality/QD-V433-COMPLIANCE.md).
