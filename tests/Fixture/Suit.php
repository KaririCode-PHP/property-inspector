<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

/** An enum: it cannot be instantiated, so it cannot be copied (code 2523). Example: `Suit::Hearts` */
enum Suit
{
    case Hearts;
}
