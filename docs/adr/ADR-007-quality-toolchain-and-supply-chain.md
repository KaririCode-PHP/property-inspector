# ADR-007 — Quality Toolchain and Supply Chain of Spec V4.3.3

| Field | Value |
|---|---|
| Status | Accepted — 2026-10-02 |
| Component | kariricode/property-inspector 2.1 |
| Standards | KaririCode Spec V4.3.3 §13, §24, §29, §31, §32 |
| Related | ADR-009 |

## Context

Release 2.0.0 delegated tooling to the KaririCode Devkit, which has these properties:

- `kcode` runs in CI and `require-dev` is empty;
- the generated configs run PHPStan at level 9 with `src/Contract` excluded and Psalm at level 3;
- they define no Conformance suite;
- the CI actions are pinned by tag, and `composer.lock` is ignored.

Where Devkit and Specification disagree on component tooling, the Specification prevails. §31 keeps `phpunit.xml.dist`, `phpstan.neon` and `psalm.xml` at the root. The sibling components already revised for V4.3.3 (Authorization, CircuitBreaker, CLI) converged on one toolchain.

## Decision

- **Root configuration.** `phpunit.xml.dist` with the Unit, Integration and Conformance suites, `phpstan.neon`, `psalm.xml`, `.php-cs-fixer.dist.php` and `infection.json5`.
- **Gates.** These are the §29.1 checks run by `composer quality`:

  | Check | Configuration |
  |---|---|
  | PHPStan | level max over `src` and `tools`, no baseline |
  | Psalm | errorLevel 1 |
  | Code style | PER-CS 3.0 |
  | Complexity | `tools/complexity.php`: cyclomatic ≤ 10, cognitive ≤ 15, parameters ≤ 4 |
  | Tests | unit, integration and conformance |
  | Security | `composer validate --strict`, `composer audit --locked`, `tools/secret-scan.php` (self-tested), `phpunit --group=fail-closed` |
  | Mutation | Infection with MSI and covered MSI ≥ 80% |

- **Supply chain (§32).** `composer.lock` is committed, with `export-ignore` so it is not distributed. `tools/sbom.php` writes a CycloneDX 1.5 SBOM. Every workflow action is pinned by commit SHA: checkout v4.4.0, setup-php 2.37.2 and action-gh-release v2.6.2. `code-quality.yml` is folded into `ci.yml`.

## Alternatives Considered

- **Keep the Devkit.** It conflicts with the Specification, and its generated configuration has no Conformance suite.
- **A PHPStan cognitive-complexity extension.** It covers one budget; the AST tool covers all three in one place.

## Consequences

### Positive
- Gates are reproducible from the lock file, and moved tags cannot alter CI.

### Negative
- There are six development dependencies, and the lock needs periodic refreshes; `composer audit` flags advisories.
- Psalm unused-code detection is off, because a library's public API is consumed elsewhere.
- Infection needs pcov or Xdebug.

## ARFA 1.3

- Tooling only. It enforces the automated gates (G1, G4) and the security gate (G6).

## References

- GitHub advisory GHSA-mrrh-fwg8-r2c3 (CVE-2025-30066, tj-actions/changed-files tags rewritten).
- CycloneDX 1.5 JSON reference — https://cyclonedx.org/docs/1.5/json/
- T. J. McCabe, "A Complexity Measure", IEEE TSE SE-2(4), 1976.
- G. A. Campbell, "Cognitive Complexity", SonarSource, 2018.
- Composer, Libraries: lock file — https://getcomposer.org/doc/02-libraries.md#lock-file
- PER Coding Style — https://www.php-fig.org/per/coding-style/
