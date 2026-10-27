<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Sanitize
{
    /** @param list<string> $sanitizers */
    public function __construct(
        public readonly array $sanitizers = [],
    ) {}
}
