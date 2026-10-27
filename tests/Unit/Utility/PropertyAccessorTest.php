<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Unit\Utility;

use KaririCode\PropertyInspector\Tests\Fixture\AsymmetricEntityFixture;
use KaririCode\PropertyInspector\Tests\Fixture\HookedPropertyFixture;
use KaririCode\PropertyInspector\Tests\Fixture\PrivatePropertiesFixture;
use KaririCode\PropertyInspector\Tests\Fixture\ReadonlyDpoFixture;
use KaririCode\PropertyInspector\Tests\Fixture\UserFixture;
use KaririCode\PropertyInspector\Utility\PropertyAccessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PropertyAccessor::class)]
final class PropertyAccessorTest extends TestCase
{
    // ── getValue ─────────────────────────────────────────────────────

    public function testGetValueReadsPublicProperty(): void
    {
        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);
        $accessor = new PropertyAccessor($user, 'name');

        self::assertSame('Walmir', $accessor->getValue());
    }

    public function testGetValueReadsPrivateProperty(): void
    {
        $entity = new PrivatePropertiesFixture();
        $accessor = new PropertyAccessor($entity, 'secret');

        self::assertSame('hidden-value', $accessor->getValue());
    }

    public function testGetValueReadsProtectedProperty(): void
    {
        $entity = new PrivatePropertiesFixture();
        $accessor = new PropertyAccessor($entity, 'internal');

        self::assertSame('protected-value', $accessor->getValue());
    }

    // ── setValue ─────────────────────────────────────────────────────

    public function testSetValueWritesPublicProperty(): void
    {
        $user = new UserFixture(name: 'Before');
        $accessor = new PropertyAccessor($user, 'name');

        $accessor->setValue('After');

        self::assertSame('After', $user->name);
    }

    public function testSetValueWritesPrivateProperty(): void
    {
        $entity = new PrivatePropertiesFixture();
        $accessor = new PropertyAccessor($entity, 'secret');

        $accessor->setValue('new-secret');

        self::assertSame('new-secret', $entity->getSecret());
    }

    public function testSetValueWritesProtectedProperty(): void
    {
        $entity = new PrivatePropertiesFixture();
        $accessor = new PropertyAccessor($entity, 'internal');

        $accessor->setValue('new-internal');

        self::assertSame('new-internal', $entity->getInternal());
    }

    // ── error cases ─────────────────────────────────────────────────

    public function testConstructorThrowsForNonexistentProperty(): void
    {
        $this->expectException(\ReflectionException::class);

        new PropertyAccessor(new UserFixture(), 'nonexistent');
    }

    // ── round-trip ──────────────────────────────────────────────────

    public function testSetThenGetReturnsSameValue(): void
    {
        $user = new UserFixture();
        $accessor = new PropertyAccessor($user, 'age');

        $accessor->setValue(42);
        $result = $accessor->getValue();

        self::assertSame(42, $result);
    }

    // ── PHP 8.4 property model ──────────────────────────────────────

    public function testSetValueWritesAsymmetricVisibilityProperty(): void
    {
        // Entities use `public private(set)`; reflection writes bypass set-visibility.
        $entity = new AsymmetricEntityFixture();
        $accessor = new PropertyAccessor($entity, 'status');

        $accessor->setValue('confirmed');

        self::assertSame('confirmed', $entity->status);
    }

    public function testSetValueRunsPropertySetHook(): void
    {
        $entity = new HookedPropertyFixture();
        $accessor = new PropertyAccessor($entity, 'name');

        $accessor->setValue('updated');

        self::assertSame('updated', $entity->name);
    }

    public function testSetValuePropagatesSetHookValidationFailure(): void
    {
        // Hook validation stays enforced on reflection writes.
        $accessor = new PropertyAccessor(new HookedPropertyFixture(), 'name');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name must not be empty');

        $accessor->setValue('');
    }

    public function testSetValueOnInitializedReadonlyPropertyThrowsError(): void
    {
        // Characterizes the ARFA P1 limitation recorded in ADR-006: readonly
        // DPOs cannot be updated in place.
        $accessor = new PropertyAccessor(new ReadonlyDpoFixture('A@B.C'), 'email');

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        $accessor->setValue('a@b.c');
    }
}
