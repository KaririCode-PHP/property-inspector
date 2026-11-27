<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;

/** An annotated typed property that was never initialized (code 2507). Example: `new HalfBuilt()` */
final class HalfBuilt
{
    #[Sanitize('trim')]
    public string $name;
}
