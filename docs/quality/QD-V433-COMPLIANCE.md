# QD-V433 — Quality Compliance Report

| Field | Value |
|---|---|
| Component | `kariricode/property-inspector` 2.1.0 (unreleased) |
| Specification | KaririCode Specification V4.3.3 · ARFA 1.3 Level 1 · OWASP ASVS 5.0 Level 1 |
| Date | 2026-10-02 |
| Status | **PENDING** — local gates green; release items in §4 are open |

## 1. Automated gates (§29.1)

Measured locally, not in CI. The runtime was PHP 8.4.26 with pcov 1.0.12, with the toolchain installed from `composer.lock`.

| Gate | Command | Result |
|---|---|---|
| Code style | `composer cs-check` | PER-CS 3.0; 0 of 39 files to fix |
| Static analysis | `composer phpstan` | level max over `src` and `tools`; 0 errors, no baseline |
| Type analysis | `composer psalm` | errorLevel 1; 0 errors, no baseline |
| Complexity | `composer complexity` | 24 methods; 0 breaches (cyclomatic ≤ 10, cognitive ≤ 15, parameters ≤ 4) |
| Tests | `composer test` | 52 tests, 123 assertions |
| Conformance | `composer test-conformance` | 15 tests, 121 assertions |
| Security | `composer security` | see below |
| Coverage | `phpunit --coverage-text` (pcov) | classes 4/4, methods 16/16, lines 88/88 (100%) |
| Mutation | `composer infection` | 36 mutants, 36 killed; MSI 100%, covered MSI 100% (minimum 80%) |
| SBOM | `composer sbom` | CycloneDX 1.5: 2 runtime and 104 development components |

`composer security` covers four checks:
- `validate --strict` passes;
- `audit --locked` finds no advisories;
- the secret scan self-tests 6 rules and reports 0 findings;
- the fail-closed group passes its 9 tests.

## 2. Production-readiness gates

| Gate | Status |
|---|---|
| G1 automated checks | Pass locally; first CI run pending |
| G2 documentation (§17) | Pass, enforced by `SpecConformanceTest::testTypeDocumentationMeetsTheCriteria` |
| G3 no P0 in manual review | Pass for the 2.1.0 scope; design items open in ADR-006 and ADR-009 |
| G4 `arfa` and `asvs` declared | Pass (`extra.kariricode`) |
| G5 antipatterns (§18) | Partial: exact deviation lists in the conformance suite |
| G6 security gate (§24) | Partial: lock, audit, SBOM, secret scan, pinned CI and fail-closed suite pass; the ASVS requirement map is pending; signing happens at release |

## 3. Deviations

`SpecConformanceTest` enforces this list exactly. A deviation that disappears fails the suite until this list and the ADR are updated.

| Rule | Deviation | Plan |
|---|---|---|
| §8.4 exception family | `PropertyInspectionException` implements no `Contract\Exception\*` family | 3.0, after kariricode/contract 2.11.0 is tagged (ADR-009) |
| §31 coupling | runtime dependency on kariricode/exception | 3.0 (ADR-009) |
| §18 getters, arrays, final | 4 getters, 5 array returns, `PropertyAccessor` not final | 3.0 (ADR-009) |
| `object` parameters | 7 signatures | accepted (ADR-008) |
| E-047 messages | messages repeat the cause message | 3.0; mitigation in SECURITY.md |
| ARFA P1 | in-place change application | ADR-006 (proposed) |
| ARFA P5 | no telemetry | decision pending (ADR-009) |

## 4. Open release items

1. Register `PI-2500–2599` in the ecosystem error-code registry.
2. First green CI run on GitHub.
3. Signed tag: `git tag -s v2.1.0`.
4. kariricode/contract 2.11.0 on Packagist. This blocks 3.0, not 2.1.0.
