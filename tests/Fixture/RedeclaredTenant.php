<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;

/**
 * Redeclares the parent's readonly property to add a rule: both classes list
 * it, but it is one storage slot that a copy may write only once.
 * Example: `new RedeclaredTenant(' acme ')`
 */
final class RedeclaredTenant extends TenantBase
{
    #[Sanitize('trim')]
    protected readonly string $tenant;

    public function __construct(string $tenant, public string $label = 'main')
    {
        $this->tenant = $tenant;
    }
}
