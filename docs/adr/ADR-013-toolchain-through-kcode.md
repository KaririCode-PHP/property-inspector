# ADR-013 — Quality toolchain through KaririCode Devkit, with the root configs

| Field | Value |
|---|---|
| Status | Accepted — 2026-11-27 |
| Component | kariricode/property-inspector 3.0 |
| Standards | KaririCode Spec V4.3.3 §29, §31, §32 |
| Related | amends ADR-007 (toolchain location); the gates of ADR-007 stay |

## Context

ADR-007 moved the toolchain into `require-dev` because Devkit 1.1 ran its own generated configs (PHPStan level 9, Psalm level 3, no Conformance suite), which the Specification overrides. Devkit 1.2.1 can run a component's committed configs instead (`'root_configs' => true`), and the ecosystem now installs the toolchain through it: kariricode/contract and kariricode/exception moved first, each recording the change in an ADR of its own.

## Decision

- `require-dev` is removed. `devkit.php` pins the tools exactly: PHPUnit 12.5.37, PHPStan 2.3.7, Psalm 6.19.1, PHP-CS-Fixer 3.95.27 and Infection 0.29.14. kcode installs them into `.kcode/vendor/`.
- The root configs of ADR-007 stay and are what kcode runs: `phpunit.xml.dist`, `phpstan.neon`, `psalm.xml`, `.php-cs-fixer.dist.php`, `infection.json5`. Paths that pointed into `vendor/` point into `.kcode/vendor/`.
- The gates of ADR-007 are unchanged, and so are the commands: `composer quality`, `composer infection`, `composer sbom`. The scripts call kcode. `composer security` also audits the toolchain (`composer audit --locked --working-dir=.kcode`).
- `composer.lock` stays committed and now holds the runtime only: kariricode/contract 2.12.0. `tools/sbom.php` reads the development components from `.kcode/composer.lock`.
- CI and the release workflow download kcode 1.2.1 by version and check its SHA-256 before `kcode init`. Actions stay pinned by commit SHA.

## Alternatives Considered

- **Keep the toolchain in `require-dev` (ADR-007).** The rest of the ecosystem installs it through kcode; two toolchain models in one ecosystem.
- **kcode with its generated configs.** Weaker than §29.1, and no Conformance suite; the reason ADR-007 left Devkit still holds for the generated configs.
- **Unpinned tools.** A gate result could not be reproduced from the commit that produced it (§32).

## Consequences

- One toolchain model across the ecosystem, with this package's gates unchanged.
- Contributors install kcode once (CONTRIBUTING.md).
- Transitive versions of the toolchain resolve at install time until Devkit commits a `devkit.lock`.

## ARFA 1.3

No runtime effect: the change touches development tooling only.

## References

- KaririCode Spec V4.3.3 §29.1, §31, §32.
- KaririCode Devkit 1.2.1 (`root_configs`); the matching toolchain decisions of kariricode/contract and kariricode/exception.
