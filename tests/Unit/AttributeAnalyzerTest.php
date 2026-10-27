<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Unit;

use KaririCode\PropertyInspector\AttributeAnalyzer;
use KaririCode\PropertyInspector\Exception\PropertyInspectionException;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\BrokenAttribute;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\ProcessableAttribute;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\ThrowingAttribute;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Transform;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Validate;
use KaririCode\PropertyInspector\Tests\Fixture\BrokenAttributeFixture;
use KaririCode\PropertyInspector\Tests\Fixture\MixedAttributeFixture;
use KaririCode\PropertyInspector\Tests\Fixture\NoAttributeFixture;
use KaririCode\PropertyInspector\Tests\Fixture\PrivatePropertiesFixture;
use KaririCode\PropertyInspector\Tests\Fixture\ThrowingAttributeFixture;
use KaririCode\PropertyInspector\Tests\Fixture\UninitializedPropertyFixture;
use KaririCode\PropertyInspector\Tests\Fixture\UserFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeAnalyzer::class)]
final class AttributeAnalyzerTest extends TestCase
{
    // ── analyzeObject: basic behavior ─────────────────────────────────

    public function testAnalyzeObjectReturnsOnlyPropertiesWithTargetAttribute(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);

        $result = $analyzer->analyzeObject($user);

        self::assertArrayHasKey('name', $result);
        self::assertArrayHasKey('email', $result);
        self::assertArrayHasKey('age', $result);
        self::assertArrayNotHasKey('noAttribute', $result);
    }

    public function testAnalyzeObjectExtractsCurrentPropertyValues(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);

        $result = $analyzer->analyzeObject($user);

        self::assertSame('Walmir', $result['name']['value']);
        self::assertSame('w@test.com', $result['email']['value']);
        self::assertSame(30, $result['age']['value']);
    }

    public function testAnalyzeObjectReturnsAttributeInstances(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);

        $result = $analyzer->analyzeObject($user);

        self::assertCount(1, $result['name']['attributes']);
        self::assertInstanceOf(Validate::class, $result['name']['attributes'][0]);
        self::assertSame(['required', 'string', 'min:3'], $result['name']['attributes'][0]->processors);
    }

    public function testAnalyzeObjectReturnsEmptyArrayForEntityWithoutTargetAttributes(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $entity = new NoAttributeFixture();

        $result = $analyzer->analyzeObject($entity);

        self::assertSame([], $result);
    }

    // ── analyzeObject: different attribute types ──────────────────────

    public function testAnalyzeObjectFiltersBySanitizeAttribute(): void
    {
        $analyzer = new AttributeAnalyzer(Sanitize::class);
        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);

        $result = $analyzer->analyzeObject($user);

        self::assertArrayHasKey('name', $result);
        self::assertArrayHasKey('email', $result);
        // age has no Sanitize attribute
        self::assertArrayNotHasKey('age', $result);
    }

    public function testAnalyzeObjectSupportsIsInstanceOfFiltering(): void
    {
        // Transform implements ProcessableAttribute, so searching for ProcessableAttribute
        // with IS_INSTANCEOF should find Transform-annotated properties
        $analyzer = new AttributeAnalyzer(ProcessableAttribute::class);
        $entity = new MixedAttributeFixture();

        $result = $analyzer->analyzeObject($entity);

        // name has Transform (implements ProcessableAttribute) and Validate (does not)
        self::assertArrayHasKey('name', $result);
        self::assertCount(1, $result['name']['attributes']);
        self::assertInstanceOf(Transform::class, $result['name']['attributes'][0]);

        // title has Transform
        self::assertArrayHasKey('title', $result);
        self::assertInstanceOf(Transform::class, $result['title']['attributes'][0]);
    }

    // ── analyzeObject: visibility ────────────────────────────────────

    public function testAnalyzeObjectAccessesPrivateAndProtectedProperties(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $entity = new PrivatePropertiesFixture();

        $result = $analyzer->analyzeObject($entity);

        self::assertArrayHasKey('secret', $result);
        self::assertSame('hidden-value', $result['secret']['value']);

        self::assertArrayHasKey('internal', $result);
        self::assertSame('protected-value', $result['internal']['value']);

        self::assertArrayHasKey('visible', $result);
        self::assertSame('public-value', $result['visible']['value']);
    }

    // ── caching ──────────────────────────────────────────────────────

    public function testAnalyzeObjectCachesReflectionMetadataAcrossCalls(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $user1 = new UserFixture(name: 'First', email: 'a@test.com', age: 20);
        $user2 = new UserFixture(name: 'Second', email: 'b@test.com', age: 40);

        $result1 = $analyzer->analyzeObject($user1);
        $result2 = $analyzer->analyzeObject($user2);

        // Same structure (same class), different values
        self::assertSame('First', $result1['name']['value']);
        self::assertSame('Second', $result2['name']['value']);

        // Attribute metadata should be identical (cached)
        self::assertSame(
            $result1['name']['attributes'][0]->processors,
            $result2['name']['attributes'][0]->processors,
        );
    }

    public function testAnalyzeObjectReflectsCurrentValueAfterMutation(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $user = new UserFixture(name: 'Before', email: 'x@test.com', age: 25);

        $analyzer->analyzeObject($user);

        $user->name = 'After';
        $result = $analyzer->analyzeObject($user);

        self::assertSame('After', $result['name']['value']);
    }

    // ── clearCache ───────────────────────────────────────────────────

    public function testClearCacheResetsInternalState(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);

        // Populate cache
        $analyzer->analyzeObject($user);

        // Clear and re-analyze — should still work
        $analyzer->clearCache();
        $result = $analyzer->analyzeObject($user);

        self::assertArrayHasKey('name', $result);
        self::assertSame('Walmir', $result['name']['value']);
    }

    // ── edge cases ───────────────────────────────────────────────────

    public function testAnalyzeObjectHandlesMultipleAttributesOnSameProperty(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);

        $result = $analyzer->analyzeObject($user);

        // UserFixture::name has 1 Validate and 1 Sanitize.
        // Analyzer filters for Validate only → exactly 1 attribute
        self::assertCount(1, $result['name']['attributes']);
    }

    public function testAnalyzeObjectHandlesDifferentClassesSeparately(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);

        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);
        $priv = new PrivatePropertiesFixture();

        $resultUser = $analyzer->analyzeObject($user);
        $resultPriv = $analyzer->analyzeObject($priv);

        self::assertArrayHasKey('email', $resultUser);
        self::assertArrayNotHasKey('email', $resultPriv);

        self::assertArrayHasKey('secret', $resultPriv);
        self::assertArrayNotHasKey('secret', $resultUser);
    }

    // ── error handling ───────────────────────────────────────────────

    #[Group('fail-closed')]
    public function testAnalyzeObjectWrapsReflectionExceptionFromBrokenAttribute(): void
    {
        // BrokenAttributeFixture has a property annotated with #[BrokenAttribute],
        // whose constructor throws ReflectionException.
        // When analyzeObject calls cacheObjectMetadata -> newInstance(),
        // the exception propagates up and is caught by the catch(\ReflectionException)
        // block in analyzeObject.
        //
        // NOTE: We use try/catch instead of expectException() to ensure PCOV
        // instruments the catch block in AttributeAnalyzer (not just the throw).
        $analyzer = new AttributeAnalyzer(BrokenAttribute::class);
        $entity = new BrokenAttributeFixture();

        try {
            $analyzer->analyzeObject($entity);
            self::fail('Expected PropertyInspectionException was not thrown');
        } catch (PropertyInspectionException $e) {
            self::assertStringContainsString('Failed to analyze object using reflection', $e->getMessage());
            self::assertSame(2501, $e->getCode());
        }
    }

    #[Group('fail-closed')]
    public function testAnalyzeObjectWrapsAttributeConstructorExceptionAsCode2506(): void
    {
        // A non-reflection exception thrown by an attribute constructor must be
        // wrapped (2506) instead of escaping raw (ADR-005).
        $analyzer = new AttributeAnalyzer(ThrowingAttribute::class);

        try {
            $analyzer->analyzeObject(new ThrowingAttributeFixture());
            self::fail('Expected PropertyInspectionException was not thrown');
        } catch (PropertyInspectionException $e) {
            self::assertSame(2506, $e->getCode());
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }
    }

    #[Group('fail-closed')]
    public function testAnalyzeObjectWrapsUninitializedTypedPropertyAsCode2503(): void
    {
        // Documented precondition (SPEC-001 INV-011): annotated properties must be
        // initialized; reading one that is not raises \Error, surfaced as 2503.
        $analyzer = new AttributeAnalyzer(Validate::class);

        try {
            $analyzer->analyzeObject(new UninitializedPropertyFixture());
            self::fail('Expected PropertyInspectionException was not thrown');
        } catch (PropertyInspectionException $e) {
            self::assertSame(2503, $e->getCode());
            self::assertInstanceOf(\Error::class, $e->getPrevious());
        }
    }

    // ── cache semantics ──────────────────────────────────────────────

    public function testAnalyzeObjectReusesAttributeInstancesUntilCacheIsCleared(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);

        $first = $analyzer->analyzeObject($user)['name']['attributes'][0];
        $second = $analyzer->analyzeObject($user)['name']['attributes'][0];

        // Cache hit: the very same attribute instance is handed out (SPEC-001 INV-012).
        self::assertSame($first, $second);

        $analyzer->clearCache();
        $afterClear = $analyzer->analyzeObject($user)['name']['attributes'][0];

        // Cache miss after clearCache(): attributes are instantiated again.
        self::assertNotSame($first, $afterClear);
        self::assertEquals($first, $afterClear);
    }
}
