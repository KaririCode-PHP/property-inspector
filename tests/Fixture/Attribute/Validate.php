<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Validate
{
    /** @param list<string> $processors */
    public function __construct(
        public readonly array $processors = [],
    ) {}
}
