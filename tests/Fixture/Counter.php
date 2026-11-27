<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;

/** A static annotated property next to an instance one. Example: `new Counter()` */
final class Counter
{
    #[Sanitize('trim')]
    public static string $shared = 'class state';

    #[Sanitize('trim')]
    public string $label = ' counter ';
}
