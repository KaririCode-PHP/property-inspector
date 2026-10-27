<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;

/** A virtual property (get hook only, no storage) next to a stored one. Example: `new Profile('Walmir', 'Silva')` */
final class Profile
{
    #[Sanitize('trim')]
    public string $fullName {
        get => $this->first . ' ' . $this->last;
    }

    public function __construct(
        public string $first,
        public string $last,
    ) {}
}
