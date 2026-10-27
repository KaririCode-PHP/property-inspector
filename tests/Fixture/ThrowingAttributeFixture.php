<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Tests\Fixture\Attribute\ThrowingAttribute;

/**
 * Carries ThrowingAttribute so that analysis fails while instantiating it.
 */
final class ThrowingAttributeFixture
{
    #[ThrowingAttribute]
    public string $field = 'value';
}
