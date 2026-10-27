<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;

/** A mutable DTO whose value changes between inspections. Example: `$draft->title = 'second';` */
final class Draft
{
    #[Sanitize('trim')]
    public string $title = 'first';

    public string $unannotated = 'never handed to a handler';
}
