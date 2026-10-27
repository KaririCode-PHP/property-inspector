<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use DomainException;
use KaririCode\Contract\Exception\Validation;

/** A processor's own Validation-family exception: the edge maps it to a 422. */
final class InvalidEmail extends DomainException implements Validation {}
