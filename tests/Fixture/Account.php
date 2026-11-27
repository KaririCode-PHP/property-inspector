<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;

/**
 * A readonly parent DPO: an inherited protected property (inspected through its
 * children) and a private one (owned by this class, never inspected through a child).
 * Example: `final readonly class SignUp extends Account`
 */
abstract readonly class Account
{
    public function __construct(
        #[Sanitize('trim')]
        protected string $tenant,
        #[Sanitize('never-seen')]
        private string $audit = 'created',
    ) {}

    public function tenant(): string
    {
        return $this->tenant;
    }

    public function audit(): string
    {
        return $this->audit;
    }
}
