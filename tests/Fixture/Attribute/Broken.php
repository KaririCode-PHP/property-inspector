<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture\Attribute;

use Attribute;
use LogicException;

/**
 * A rule whose constructor rejects its declaration (code 2506). It implements
 * Rule, so one inspection for Rule meets it next to valid rules.
 * Example: `#[Broken]`
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Broken implements Rule
{
    public string $rule = 'broken';

    public function __construct()
    {
        throw new LogicException('This rule is declared wrong.');
    }
}
