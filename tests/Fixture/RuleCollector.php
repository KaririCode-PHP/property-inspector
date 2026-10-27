<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\Contract\Inspection\PropertyAttributeHandler;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Rule;
use Override;

/**
 * Handler that records every call and keeps what a processor reads through its
 * own API: the value and the rules of each property.
 * Example: `$inspector->inspect($dto, Sanitize::class, $collector = new RuleCollector());`
 */
final class RuleCollector implements PropertyAttributeHandler
{
    /** @var list<array{string, string, mixed}> property, rule and value, in call order */
    public private(set) array $calls = [];

    /** @var array<string, mixed> */
    public private(set) array $values = [];

    /** @var array<string, list<string>> */
    public private(set) array $rules = [];

    /** @var list<object> */
    public private(set) array $attributes = [];

    #[Override]
    public function handleAttribute(string $propertyName, object $attribute, mixed $value): void
    {
        $rule = $attribute instanceof Rule ? $attribute->rule : $attribute::class;
        $this->calls[] = [$propertyName, $rule, $value];
        $this->values[$propertyName] = $value;
        $this->rules[$propertyName] = [...$this->rules[$propertyName] ?? [], $rule];
        $this->attributes[] = $attribute;
    }
}
