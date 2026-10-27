<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture\Attribute;

use Attribute;

/** A repeatable sanitizing rule. Example: `#[Sanitize('trim')]` */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class Sanitize implements Rule
{
    public function __construct(public string $rule) {}
}
