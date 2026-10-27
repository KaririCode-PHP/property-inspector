<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Validate;

/**
 * Annotated typed property left uninitialized: reading it raises \Error,
 * which the analyzer must surface as code 2503.
 */
final class UninitializedPropertyFixture
{
    #[Validate(processors: ['required'])]
    public string $name;
}
