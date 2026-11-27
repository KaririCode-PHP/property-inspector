<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

/**
 * A parent whose private properties share their names with properties of its
 * child: each private property is its own slot. No defaults, so a slot the
 * copy forgets stays uninitialized. Example: `final class ShadowChild extends ShadowBase`
 */
abstract class ShadowBase
{
    private string $code;

    private string $token;

    public function __construct(string $code, string $token)
    {
        $this->code = $code;
        $this->token = $token;
    }

    public function baseCode(): string
    {
        return $this->code;
    }

    public function baseToken(): string
    {
        return $this->token;
    }
}
