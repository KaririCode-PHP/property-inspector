<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Broken;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;

/** A property whose attribute constructor throws, next to a valid one. Example: `new BrokenDeclaration()` */
final class BrokenDeclaration
{
    #[Sanitize('trim')]
    public string $valid = 'ok';

    #[Broken]
    public string $invalid = 'never read';
}
