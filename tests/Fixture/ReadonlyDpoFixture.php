<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

/**
 * Readonly DPO as mandated by the Spec type taxonomy; used to pin the in-place
 * write limitation documented in ADR-006.
 */
final readonly class ReadonlyDpoFixture
{
    public function __construct(
        public string $email,
    ) {}
}
