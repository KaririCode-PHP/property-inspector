# Contributing

## Gates

```bash
composer install            # from composer.lock
composer quality            # cs-check, phpstan (max), psalm (1), complexity, tests, conformance, security
composer infection          # mutation testing, MSI >= 80% (needs pcov or Xdebug)
composer sbom               # CycloneDX 1.5 SBOM into build/
```

The tooling lives in this repository (ADR-007); it does not depend on the KaririCode Devkit.

## Code rules (KaririCode Spec V4.3.3)

- **Files.** `declare(strict_types=1)`, one type per file, PSR-4, PER Coding Style 3.0.
- **Type docblocks.** Each states:
  - the domain role and non-obvious preconditions;
  - thread-safety and side effects;
  - a usage example (concrete classes);
  - references and `@since`.
- **Prohibited:**
  - parameterless getters;
  - arrays as domain return values;
  - new `object` parameters;
  - magic methods;
  - catches that do not re-throw.

  The one accepted deviation (`object` parameters, ADR-008) is listed exactly in `tests/Conformance/SpecConformanceTest.php`.
- **Exceptions.** A failure of this package is a final class implementing `Exception\PropertyInspectionException` (family `Runtime`), built by a named factory, with a `[PI_<code>]` message that never echoes the cause or a value (ADR-011). Exceptions from handlers and property hooks are never wrapped (ADR-010).
- **Tests.**
  - Every bug fix ships with a test that fails without it.
  - Tests that prove the package's own failures surface as `PropertyInspectionException` carry `#[Group('fail-closed')]`.
  - The semantics of the contract are pinned by the tests named in SPEC-001 §3.

## Decisions and releases

- **ADRs** go in `docs/adr/` using the sections Context, Decision, Alternatives Considered, Consequences, ARFA 1.3 and References.
- **Commits** follow Conventional Commits.
- **Releases** follow Semantic Versioning, and tags are signed (`git tag -s`).
