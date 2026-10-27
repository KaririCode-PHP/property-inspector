<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Validate;

final class PrivatePropertiesFixture
{
    #[Validate(processors: ['required'])]
    private string $secret = 'hidden-value'; // secret-scan:allow (fixture value, not a credential)

    #[Validate(processors: ['required'])]
    protected string $internal = 'protected-value';

    #[Validate(processors: ['required'])]
    public string $visible = 'public-value';

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getInternal(): string
    {
        return $this->internal;
    }
}
