<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

final class NoAttributeFixture
{
    public function __construct(
        public string $name = 'plain',
        public int $count = 0,
    ) {}
}
