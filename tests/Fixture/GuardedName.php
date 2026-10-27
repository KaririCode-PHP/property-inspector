<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use InvalidArgumentException;

/** A set hook that rejects empty names with a domain exception. Example: `new GuardedName('Walmir')` */
final class GuardedName
{
    public string $name {
        set(string $value) {
            if ('' === $value) {
                throw new InvalidArgumentException('A name must not be empty.');
            }

            $this->name = $value;
        }
    }

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
