<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Tests\Fixture\Attribute\ClassOnly;

/** A class-only attribute declared on a property. Example: `new MisplacedDeclaration()` */
final class MisplacedDeclaration
{
    #[ClassOnly]
    public string $field = 'value';
}
