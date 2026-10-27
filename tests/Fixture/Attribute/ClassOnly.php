<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture\Attribute;

use Attribute;

/** An attribute that may only target classes; declared on a property it cannot be built (code 2503). */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ClassOnly {}
