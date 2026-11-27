# QD-V433 — Quality Compliance Report

| Field | Value |
|---|---|
| Component | `kariricode/property-inspector` 3.0.0 (unreleased) |
| Specification | KaririCode Specification V4.3.3 · ARFA 1.3 Level 1 · OWASP ASVS 5.0 Level 1 |
| Date | 2026-10-27 |
| Status | **PENDING** — local gates green against kariricode/contract 2.12.0 built from its sources; release items in §4 are open |

## 1. Automated gates (§29.1)

Measured locally on 2026-10-27, not in CI. The runtime was PHP 8.4.27 with pcov 1.0.12.

kariricode/contract 2.12.0 is not on Packagist yet. The gates ran against a local build of it: the 2.11.0 commit on its `main` (`62dea5b`) plus the three `Inspection` interfaces of its 2.12.0 release commit. That build was installed through an untracked Composer path repository; every other package came from the versions locked in `composer.lock`.

| Gate | Command | Result |
|---|---|---|
| Code style | `composer cs-check` | PER-CS 3.0; 0 of 41 files to fix |
| Static analysis | `composer phpstan` | level max over `src` and `tools`; 0 errors, no baseline |
| Type analysis | `composer psalm` | errorLevel 1; 0 errors, no baseline |
| Complexity | `composer complexity` | 28 methods; 0 breaches (cyclomatic ≤ 10, cognitive ≤ 15, parameters ≤ 4) |
| Tests | `composer test` | 60 tests, 141 assertions |
| Conformance | `composer test-conformance` | 15 tests, 153 assertions |
| Security | `composer security` | validate passes; audit finds no advisories; secret scan self-tests 6 rules, 0 findings; fail-closed group 12 tests |
| Coverage | `phpunit --coverage-text` (pcov) | classes 7/7, methods 28/28, lines 109/109 (100%) |
| Mutation | `composer infection` | 90 mutants, 90 killed; MSI 100%, covered MSI 100% (minimum 80%) |
| SBOM | `composer sbom` | generated after `composer.lock` is regenerated (§4) |

The mutation run first left 12 mutants alive (MSI 88%). They exposed untested cases and a needlessly complex slot key in the applier. Both were fixed by tests for shadowed private properties and copies of objects with static properties, and by a simpler implementation.

## 2. Production-readiness gates

| Gate | Status |
|---|---|
| G1 automated checks | Pass locally; CI pending (§4) |
| G2 documentation (§17) | Pass, enforced by `SpecConformanceTest::testTypeDocumentationMeetsTheCriteria` |
| G3 no P0 in manual review | Pass |
| G4 `arfa` and `asvs` declared | Pass (`extra.kariricode`) |
| G5 antipatterns (§18) | Pass; one accepted deviation (ADR-008) |
| G6 security gate (§24) | Pass locally: audit, secret scan, fail-closed suite, E-047 messages. Open: ASVS requirement map, signed tag |

## 3. Deviations

`SpecConformanceTest` enforces this list exactly.

| Rule | Deviation | Plan |
|---|---|---|
| `object` parameters | `ReflectionPropertyInspector::inspect($object)`, `ReflectionChangeApplier::applyChanges($object)` | accepted (ADR-008) |
| ARFA P5 | met by delegation, no telemetry of its own | ADR-012, proposed |

The 2.x deviations are closed by ADR-009, ADR-010 and ADR-011:
- the exception family;
- the sibling dependency;
- getters and array return values;
- non-final classes;
- E-047 messages;
- in-place writes.

## 4. Open release items

1. kariricode/contract 2.12.0 on Packagist.
2. Regenerate `composer.lock`: `composer update kariricode/contract kariricode/exception`. Then rerun `composer quality`, `composer infection` and `composer sbom` from the lock.
3. Green `ci.yml` runs on `develop` and `main`; `release.yml` reruns every gate before publishing.
4. Register `PI-2500–2599` in the ecosystem error-code registry.
5. Signed tag: `git tag -s v3.0.0`.
