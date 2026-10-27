<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Transform implements ProcessableAttribute
{
    /** @param list<string> $processors */
    public function __construct(
        public readonly array $processors = [],
    ) {}

    public function getProcessors(): array
    {
        return $this->processors;
    }
}
