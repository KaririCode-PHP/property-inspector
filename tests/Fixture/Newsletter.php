<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

/**
 * A mutable class with a set hook that counts its runs, and a property left
 * uninitialized on purpose. Example: `new Newsletter('A@B.org')`
 */
final class Newsletter
{
    public int $writes = 0;

    public string $email {
        set(string $value) {
            $this->email = strtolower($value);
            ++$this->writes;
        }
    }

    /** @psalm-suppress PropertyNotSetInConstructor left uninitialized on purpose */
    public ?string $topic;

    public function __construct(string $email)
    {
        $this->email = $email;
    }
}
