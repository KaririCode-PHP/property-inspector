# KaririCode PropertyInspector

<div align="center">

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-22c55e.svg)](LICENSE)
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-4F46E5)](https://phpstan.org/)
[![Tests](https://img.shields.io/badge/Tests-75%20passing-22c55e)](docs/quality/QD-V433-COMPLIANCE.md)
[![ARFA](https://img.shields.io/badge/ARFA-1.3-orange)](https://kariricode.org)
[![KaririCode Framework](https://img.shields.io/badge/KaririCode-Framework-orange)](https://kariricode.org)

**Attribute-driven property inspection for the KaririCode Framework: one inspector for every processor,
and copy-on-write change application that works on readonly DPOs. PHP 8.4+.**

[Installation](#installation) · [Quick Start](#quick-start) · [Features](#features) · [Pipeline](#the-inspection-pipeline) · [Architecture](#architecture) · [Migrating from 2.x](#migrating-from-2x)

</div>

---

## The Problem

Every processor that reads declarations from an object (a sanitizer, a validator, a transformer) ends up writing the same reflection loop, and none of them can write the result back to a readonly DPO:

```php
foreach ((new ReflectionClass($dto))->getProperties() as $property) {
    foreach ($property->getAttributes(Sanitize::class) as $attribute) {
        $value = $property->getValue($dto);
        // process it... then where does it go?
        $property->setValue($dto, $clean);   // Error: Cannot modify readonly property
    }
}
```

## The Solution

The contracts live in `kariricode/contract`. This package implements them, and a processor depends on the contract only:

```php
use KaririCode\Contract\Inspection\PropertyChangeApplier;
use KaririCode\Contract\Inspection\PropertyInspector;

final readonly class Sanitizer
{
    public function __construct(
        private PropertyInspector $inspector,       // ReflectionPropertyInspector, bound once
        private PropertyChangeApplier $applier,     // ReflectionChangeApplier, bound once
        private SanitizeEngine $engine,             // the processor's own rules engine
    ) {}

    public function sanitize(object $dto): object
    {
        $this->inspector->inspect($dto, Sanitize::class, $rules = new SanitizeRules());

        return $this->applier->applyChanges($dto, $this->engine->run($rules));   // a new instance
    }
}
```

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.4 or higher |
| kariricode/contract | ^2.12 |

---

## Installation

```bash
composer require kariricode/property-inspector
```

Bind the implementations to the contracts once:

```php
use KaririCode\Contract\Inspection\PropertyChangeApplier;
use KaririCode\Contract\Inspection\PropertyInspector;
use KaririCode\PropertyInspector\Core\ReflectionChangeApplier;
use KaririCode\PropertyInspector\Core\ReflectionPropertyInspector;

$container->instance(PropertyInspector::class, new ReflectionPropertyInspector());
$container->instance(PropertyChangeApplier::class, new ReflectionChangeApplier());
```

---

## Quick Start

Sanitize a readonly DPO: inspect it, process what the handler collected, write the result to a copy.

```php
<?php

declare(strict_types=1);

use KaririCode\Contract\Inspection\PropertyAttributeHandler;
use KaririCode\PropertyInspector\Core\ReflectionChangeApplier;
use KaririCode\PropertyInspector\Core\ReflectionPropertyInspector;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class Sanitize
{
    public function __construct(public string $rule) {}
}

final readonly class SignUp
{
    public function __construct(
        #[Sanitize('trim')]
        #[Sanitize('lowercase')]
        public string $email,
        #[Sanitize('trim')]
        public string $name,
    ) {}
}

final class SanitizeRules implements PropertyAttributeHandler
{
    /** @var array<string, mixed> */
    public private(set) array $values = [];

    /** @var array<string, list<string>> */
    public private(set) array $rules = [];

    public function handleAttribute(string $propertyName, object $attribute, mixed $value): void
    {
        $this->values[$propertyName] = $value;
        $this->rules[$propertyName][] = $attribute->rule;
    }
}

$signUp = new SignUp(email: ' Walmir@KaririCode.org ', name: ' Walmir ');

(new ReflectionPropertyInspector())->inspect($signUp, Sanitize::class, $rules = new SanitizeRules());

$changes = [];
foreach ($rules->rules as $property => $list) {
    $changes[$property] = array_reduce($list, static fn(string $v, string $rule): string => match ($rule) {
        'trim' => trim($v),
        'lowercase' => strtolower($v),
    }, $rules->values[$property]);
}

$clean = (new ReflectionChangeApplier())->applyChanges($signUp, $changes);

echo $clean->email;   // walmir@kariricode.org
echo $signUp->email;  // ' Walmir@KaririCode.org ' (the original is untouched)
```

---

## Features

### One inspector for every processor

The attribute class is an argument of `inspect()`, so a single `ReflectionPropertyInspector` serves the sanitizer, the validator and the transformer. Matching uses `IS_INSTANCEOF`, so passing an interface selects every attribute that implements it.

### Copy-on-write for readonly DPOs

`ReflectionChangeApplier::applyChanges()` returns a new instance and never writes to the original. Unchanged state is copied as stored, every storage slot exactly once: a parent's private properties are included, and a property redeclared by a subclass is copied once. Changed properties are assigned, so their set hooks run. The constructor does not run, so validate the copy (ADR-006).

### Reflection caching

Reflection metadata and attribute instances are cached per class and attribute class. Values are read on every call, never cached. `clearCache()` empties the cache.

### Exceptions that keep their family

The package's own failures are `Runtime` faults with stable codes (2503–2523), and their messages never echo input (ADR-011). An exception thrown by your handler, for example a `Validation`, reaches the caller unchanged and still maps to a 422 at the edge (ADR-010).

---

## The Inspection Pipeline

```
inspect($dpo, Sanitize::class, $handler)
  └─ AttributeAnalyzer (cached per class × attribute class)
       getProperties() → getAttributes(Sanitize::class, IS_INSTANCEOF) → newInstance()
  └─ read every annotated value          (uninitialized → 2507, before any handler call)
  └─ handler->handleAttribute(name, attribute, value)   once per attribute, declaration order

applyChanges($dpo, $changes)
  └─ resolve names                        (unknown → 2520, static → 2521)
  └─ newInstanceWithoutConstructor()      (enum or internal final class → 2523)
  └─ copy every storage slot once, raw    (no set hooks)
  └─ assign each change                   (set hooks run; \Error → 2522)
```

---

## Architecture

### Source layout

```
src/
├── Core/
│   ├── ReflectionPropertyInspector.php   implements Contract\Inspection\PropertyInspector
│   ├── ReflectionChangeApplier.php       implements Contract\Inspection\PropertyChangeApplier
│   ├── AttributeAnalyzer.php             internal: cached analysis per class × attribute class
│   ├── AnalyzedClass.php                 internal Value Object
│   └── AnalyzedProperty.php              internal Value Object
└── Exception/
    ├── PropertyInspectionException.php   marker interface, extends Contract\Exception\Runtime
    ├── InspectionFailedException.php     2503, 2506, 2507
    └── ChangeRejectedException.php       2520–2523
```

### Key design decisions

| Decision | Rationale | ADR |
|---|---|---|
| Copy-on-write applier | Readonly DPOs cannot be written in place (ARFA P1) | [ADR-006](docs/adr/ADR-006-immutable-change-application.md) |
| Toolchain in the repository | The Specification prevails over the Devkit | [ADR-007](docs/adr/ADR-007-quality-toolchain-and-supply-chain.md) |
| `object` parameters | The domain is any object | [ADR-008](docs/adr/ADR-008-object-typed-inspection-api.md) |
| Implement kariricode/contract 2.12 | Consumers depend on the contract only (§31) | [ADR-009](docs/adr/ADR-009-ecosystem-contract-alignment-3-0.md) |
| Handler and hook exceptions propagate | A `Validation` stays a 422 | [ADR-010](docs/adr/ADR-010-handler-and-hook-exceptions-propagate.md) |
| Runtime family, stable codes, E-047 messages | One catchable family; no input in messages | [ADR-011](docs/adr/ADR-011-exception-model-3-0.md) |
| P5 by delegation (proposed) | Inspection runs inside a processor's operation | [ADR-012](docs/adr/ADR-012-observability-by-delegation.md) |

### Specifications

| Spec | Covers |
|---|---|
| [SPEC-001](docs/spec/SPEC-001-property-inspection-pipeline.md) | Pipeline, contracts, invariants, error codes |

Index of all documents: [docs/README.md](docs/README.md).

---

## Integration with the KaririCode Ecosystem

| Package | Role |
|---|---|
| `kariricode/contract` 2.12 | Defines `Inspection\PropertyInspector`, `PropertyAttributeHandler` and `PropertyChangeApplier` |
| sanitizer, transformer, serializer, validator | Consume the two services through the contract; their handlers implement `PropertyAttributeHandler` |

Spec V4.3.3 §31 routes coupling between components through `kariricode/contract`. On Packagist, as of 2026-10-27, kariricode/sanitizer 1.2.3 and kariricode/validator 1.0.0 still require the 2.x line directly.

---

## Migrating from 2.x

3.0 is a major release; see [UPGRADE-3.0.md](UPGRADE-3.0.md). In short:

- depend on `kariricode/contract:^2.12`;
- pass the attribute class to `inspect()`;
- make handlers implement `handleAttribute(): void` only;
- write back with `applyChanges()`, which returns a copy;
- catch your own handler's exceptions, because they are no longer wrapped.

---

## Project Stats

| Metric | Value |
|---|---|
| PHP source files | 8 (676 lines) |
| Runtime dependencies | 1 (`kariricode/contract`) |
| Tests | 75 (unit 57 · integration 3 · conformance 15) |
| Line coverage | 100% (pcov) |
| Mutation score | MSI 100% (90 of 90 killed) |
| PHPStan / Psalm | level max / errorLevel 1, no baseline |
| Spec / ARFA / ASVS | V4.3.3 / 1.3 Level 1 / 5.0 Level 1 |

Measured locally on 2026-10-27 against kariricode/contract 2.12.0 built from its sources; details in [QD-V433](docs/quality/QD-V433-COMPLIANCE.md).

---

## Contributing

```bash
git clone https://github.com/KaririCode-PHP/property-inspector.git
cd property-inspector
composer install
composer quality     # must pass before opening a PR
composer infection   # MSI >= 80% (needs pcov or Xdebug)
```

Rules and conventions: [CONTRIBUTING.md](CONTRIBUTING.md). Security: [SECURITY.md](SECURITY.md). Changes: [CHANGELOG.md](CHANGELOG.md).

---

## License

[MIT License](LICENSE) © [Walmir Silva](mailto:community@kariricode.org)

<div align="center">

[kariricode.org](https://kariricode.org) · [GitHub](https://github.com/KaririCode-PHP/property-inspector) · [Packagist](https://packagist.org/packages/kariricode/property-inspector)

</div>
