<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Fixture;

/** A parent that declares a readonly property its child redeclares. Example: `final class RedeclaredTenant extends TenantBase` */
abstract class TenantBase
{
    /** @psalm-suppress PropertyNotSetInConstructor initialized by the child */
    protected readonly string $tenant;

    public function tenant(): string
    {
        return $this->tenant;
    }
}
