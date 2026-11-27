<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

/**
 * Declares a private `$code` and a public `$token`, names its parent also uses
 * for private properties. Example: `new ShadowChild()`
 */
final class ShadowChild extends ShadowBase
{
    private string $code;

    public string $token;

    public function __construct()
    {
        parent::__construct('base-code', 'base-token');
        $this->code = 'child-code';
        $this->token = 'child-token';
    }

    public function childCode(): string
    {
        return $this->code;
    }
}
