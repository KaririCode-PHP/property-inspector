<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture\Attribute;

use Attribute;

/**
 * Attribute whose constructor throws a non-reflection exception, forcing the
 * analysis-exception path of AttributeAnalyzer (code 2506). Complements
 * BrokenAttribute, which covers the reflection path (code 2501).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ThrowingAttribute
{
    public function __construct()
    {
        throw new \RuntimeException('attribute construction failed');
    }
}
