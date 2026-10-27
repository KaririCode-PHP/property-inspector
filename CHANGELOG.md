# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [2.1.0] — 2026-10-27

### Fixed

- `PropertyInspector::inspect()` no longer wraps a `PropertyInspectionException` from the analyzer a second time. The original code (2501, 2503, 2506) reaches the caller (ADR-004).
- `AttributeAnalyzer::analyzeObject()` wraps exceptions thrown by attribute constructors as code 2506 instead of letting them escape (ADR-005).

### Added

- `PropertyInspectionException::failedToAnalyzeObjectException()` (code 2506).
- `composer.json` metadata `extra.kariricode.{arfa, asvs, spec, error-code-range}` (Spec V4.3.3 §4). The range `PI-2500–2599` is pending registration.
- Conformance suite `tests/Conformance/` for the V4.3.3 rules, with exact deviation lists (ADR-008, ADR-009).
- Tests:
  - uninitialized properties;
  - attribute instance caching;
  - handler failures;
  - PHP 8.4 asymmetric visibility and `set` hooks;
  - the readonly limitation.
- Tooling (ADR-007):
  - `tools/complexity.php`, `tools/secret-scan.php` and `tools/sbom.php`;
  - Infection;
  - root `phpunit.xml.dist`, `phpstan.neon`, `psalm.xml`, `.php-cs-fixer.dist.php`;
  - committed `composer.lock`;
  - CI actions pinned by commit SHA.
- Documentation:
  - `docs/README.md`, ADR-004 to ADR-009;
  - `docs/quality/QD-V433-COMPLIANCE.md`, `docs/security/THREAT-MODEL.md`;
  - SECURITY.md, CONTRIBUTING.md, this changelog.

### Changed

- **Exception constants** are typed (`const int`).
- **Type docblocks** state role, preconditions, thread-safety, side effects, example, references and `@since`.
- **Docs layout:** `docs/adr/` and `docs/spec/`.
- **ADRs** were rewritten in the V4.3.3 format:
  - section references that did not exist in the Specification were removed;
  - ADR-002 now gives dependency inversion, not interface segregation, as its rationale.
- **Tests:**
  - test doubles defined inline in the integration test moved to `tests/Fixture/`;
  - stubs replace mocks without expectations (no PHPUnit notices).
- **CI** runs the Composer gates instead of the Devkit; `code-quality.yml` was folded into `ci.yml`.

## [2.0.0] — 2026-03-03

### Changed

- PHP `^8.4`, `kariricode/contract ^2.8`.
- `clearCache()` added to `Contract\AttributeAnalyzer`, a breaking change for implementers (ADR-002).
- `@throws` on `analyzeObject()` corrected (ADR-003).

### Removed

- Dead `setAccessible()` calls (ADR-001).

[2.1.0]: https://github.com/KaririCode-Framework/kariricode-property-inspector/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/KaririCode-Framework/kariricode-property-inspector/compare/v1.2.3...v2.0.0
