<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Validate;

final class UserFixture
{
    public function __construct(
        #[Validate(processors: ['required', 'string', 'min:3'])]
        #[Sanitize(sanitizers: ['trim'])]
        public string $name = '',
        #[Validate(processors: ['required', 'email'])]
        #[Sanitize(sanitizers: ['trim', 'lowercase'])]
        public string $email = '',
        #[Validate(processors: ['required', 'integer', 'min:18'])]
        public int $age = 0,
        public string $noAttribute = 'ignored',
    ) {}
}
