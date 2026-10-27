<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

/**
 * Property guarded by a PHP 8.4 `set` hook that rejects empty strings; proves
 * reflection writes still run hook validation.
 */
final class HookedPropertyFixture
{
    public string $name = 'initial' {
        set(string $value) {
            if ('' === $value) {
                throw new \InvalidArgumentException('name must not be empty');
            }

            $this->name = $value;
        }
    }
}
