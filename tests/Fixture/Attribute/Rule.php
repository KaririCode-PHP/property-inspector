<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture\Attribute;

/**
 * What every rule attribute implements, so an inspector can be asked for all
 * of them at once. Example: `$inspector->inspect($dto, Rule::class, $handler);`
 */
interface Rule
{
    public string $rule { get; }
}
