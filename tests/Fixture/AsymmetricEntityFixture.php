<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

/**
 * Entity using PHP 8.4 asymmetric visibility, as the Spec prescribes for
 * entities: publicly readable, writable only from inside the class.
 */
final class AsymmetricEntityFixture
{
    public private(set) string $status = 'draft';
}
