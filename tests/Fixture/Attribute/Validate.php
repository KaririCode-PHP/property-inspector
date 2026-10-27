<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture\Attribute;

use Attribute;

/** A repeatable validation rule. Example: `#[Validate('email')]` */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class Validate implements Rule
{
    public function __construct(public string $rule) {}
}
