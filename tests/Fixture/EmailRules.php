<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\Contract\Inspection\PropertyAttributeHandler;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Validate;
use Override;

/** A minimal validating handler: collects errors instead of throwing. Example: `$inspector->inspect($dpo, Validate::class, $rules = new EmailRules());` */
final class EmailRules implements PropertyAttributeHandler
{
    /** @var array<string, string> */
    public private(set) array $errors = [];

    #[Override]
    public function handleAttribute(string $propertyName, object $attribute, mixed $value): void
    {
        if ($attribute instanceof Validate && 'email' === $attribute->rule && false === filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            $this->errors[$propertyName] = 'not an e-mail address';
        }
    }
}
