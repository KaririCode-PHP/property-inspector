<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\Contract\Inspection\PropertyAttributeHandler;
use Override;

/** Handler that rejects the first value it sees with a Validation-family exception. */
final class RejectingHandler implements PropertyAttributeHandler
{
    #[Override]
    public function handleAttribute(string $propertyName, object $attribute, mixed $value): void
    {
        throw new InvalidEmail(\sprintf('%s is not a valid e-mail address.', $propertyName));
    }
}
