# QD-V433 — Quality Compliance Report

| Field | Value |
|---|---|
| Component | `kariricode/property-inspector` 3.0.0 |
| Specification | KaririCode Specification V4.3.3 · ARFA 1.3 Level 1 · OWASP ASVS 5.0 Level 1 |
| Date | 2026-11-27 |
| Status | **PASS** — gates green against kariricode/contract 2.12.0 from Packagist, toolchain through kcode (ADR-013); CI runs the same commands |

## 1. Automated gates (§29.1)

Measured on 2026-11-27 with PHP 8.4.28 and pcov, against kariricode/contract 2.12.0 installed from Packagist through `composer.lock`. The toolchain is the one `devkit.php` pins (PHPUnit 12.5.37, PHPStan 2.3.7, Psalm 6.19.1, PHP-CS-Fixer 3.95.27, Infection 0.29.14), run with the root configs (ADR-013). CI repeats every command on each push to `main`.

| Gate | Command | Result |
|---|---|---|
| Code style | `composer cs-check` | PER-CS 3.0; 0 of 41 files to fix |
| Static analysis | `composer phpstan` | level max over `src` and `tools`; 0 errors, no baseline |
| Type analysis | `composer psalm` | errorLevel 1; 0 errors, no baseline |
| Complexity | `composer complexity` | 28 methods; 0 breaches (cyclomatic ≤ 10, cognitive ≤ 15, parameters ≤ 4) |
| Tests | `composer test` | 60 tests, 141 assertions |
| Conformance | `composer test-conformance` | 15 tests, 167 assertions |
| Security | `composer security` | validate passes; audit of the runtime and the toolchain finds no advisories; secret scan self-tests 6 rules, 0 findings; fail-closed group 12 tests |
| Coverage | `phpunit --coverage-text` (pcov) | classes 7/7, methods 28/28, lines 109/109 (100%) |
| Mutation | `composer infection` | 90 mutants, 90 killed; MSI 100%, covered MSI 100% (minimum 80%) |
| SBOM | `composer sbom` | CycloneDX 1.5: 1 runtime component, the toolchain as excluded components |

The mutation run first left 12 mutants alive (MSI 88%). They exposed untested cases and a needlessly complex slot key in the applier. Both were fixed by tests for shadowed private properties and copies of objects with static properties, and by a simpler implementation.

## 2. Production-readiness gates

| Gate | Status |
|---|---|
| G1 automated checks | Pass |
| G2 documentation (§17) | Pass, enforced by `SpecConformanceTest::testTypeDocumentationMeetsTheCriteria` |
| G3 no P0 in manual review | Pass |
| G4 `arfa` and `asvs` declared | Pass (`extra.kariricode`) |
| G5 antipatterns (§18) | Pass; one accepted deviation (ADR-008) |
| G6 security gate (§24) | Pass: audit, secret scan, fail-closed suite, E-047 messages. Open: ASVS requirement map, signed tag |

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

1. Done: kariricode/contract 2.12.0 is on Packagist, and `composer.lock` was regenerated from it.
2. Done: `PI-2500–2599` is in the ecosystem registry (`CODE-BLOCKS.md` of kariricode/exception).
3. Open: signed tags. Releases are created from the GitHub UI, unsigned, for now.
4. Open: the ASVS 5.0 requirement map.
