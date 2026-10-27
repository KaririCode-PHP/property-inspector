<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Unit\Utility;

use KaririCode\PropertyInspector\AttributeAnalyzer;
use KaririCode\PropertyInspector\Contract\AttributeAnalyzer as AttributeAnalyzerContract;
use KaririCode\PropertyInspector\Exception\PropertyInspectionException;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Validate;
use KaririCode\PropertyInspector\Tests\Fixture\FailingAttributeHandler;
use KaririCode\PropertyInspector\Tests\Fixture\NoAttributeFixture;
use KaririCode\PropertyInspector\Tests\Fixture\SpyAttributeHandler;
use KaririCode\PropertyInspector\Tests\Fixture\UserFixture;
use KaririCode\PropertyInspector\Utility\PropertyInspector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PropertyInspector::class)]
#[UsesClass(AttributeAnalyzer::class)]
final class PropertyInspectorTest extends TestCase
{
    // ── inspect: basic delegation ────────────────────────────────────

    public function testInspectDelegatesEachAttributeToHandler(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $inspector = new PropertyInspector($analyzer);
        $handler = new SpyAttributeHandler();

        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);
        $inspector->inspect($user, $handler);

        // UserFixture has 3 properties with Validate: name, email, age (1 each)
        self::assertSame(3, $handler->getCallCount());
    }

    public function testInspectPassesCorrectPropertyNameAndValue(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $inspector = new PropertyInspector($analyzer);
        $handler = new SpyAttributeHandler();

        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);
        $inspector->inspect($user, $handler);

        $nameCalls = $handler->getCallsForProperty('name');
        self::assertCount(1, $nameCalls);
        self::assertSame('Walmir', $nameCalls[0]['value']);
        self::assertInstanceOf(Validate::class, $nameCalls[0]['attribute']);
    }

    public function testInspectReturnsSameHandlerInstance(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $inspector = new PropertyInspector($analyzer);
        $handler = new SpyAttributeHandler();

        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);
        $returned = $inspector->inspect($user, $handler);

        self::assertSame($handler, $returned);
    }

    // ── inspect: attribute filtering ─────────────────────────────────

    public function testInspectWithSanitizeAnalyzerOnlyProcessesSanitizeAttributes(): void
    {
        $analyzer = new AttributeAnalyzer(Sanitize::class);
        $inspector = new PropertyInspector($analyzer);
        $handler = new SpyAttributeHandler();

        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);
        $inspector->inspect($user, $handler);

        // Only name and email have Sanitize attributes
        self::assertSame(2, $handler->getCallCount());

        $properties = array_map(
            static fn(array $call): string => $call['propertyName'],
            $handler->getCalls(),
        );
        self::assertContains('name', $properties);
        self::assertContains('email', $properties);
        self::assertNotContains('age', $properties);
    }

    // ── inspect: no attributes ───────────────────────────────────────

    public function testInspectWithNoMatchingAttributesDoesNotCallHandler(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $inspector = new PropertyInspector($analyzer);
        $handler = new SpyAttributeHandler();

        $entity = new NoAttributeFixture();
        $inspector->inspect($entity, $handler);

        self::assertSame(0, $handler->getCallCount());
    }

    // ── inspect: exception wrapping ──────────────────────────────────

    #[Group('fail-closed')]
    public function testInspectWrapsRuntimeExceptionIntoPropertyInspectionException(): void
    {
        $analyzer = self::createStub(AttributeAnalyzerContract::class);
        $analyzer->method('analyzeObject')
            ->willThrowException(new \RuntimeException('test error'));

        $inspector = new PropertyInspector($analyzer);
        $handler = new SpyAttributeHandler();

        $this->expectException(PropertyInspectionException::class);
        $this->expectExceptionMessage('test error');
        $this->expectExceptionCode(2504);

        $inspector->inspect(new UserFixture(), $handler);
    }

    #[Group('fail-closed')]
    public function testInspectWrapsReflectionExceptionIntoPropertyInspectionException(): void
    {
        $analyzer = self::createStub(AttributeAnalyzerContract::class);
        $analyzer->method('analyzeObject')
            ->willThrowException(new \ReflectionException('reflection failure'));

        $inspector = new PropertyInspector($analyzer);
        $handler = new SpyAttributeHandler();

        $this->expectException(PropertyInspectionException::class);
        $this->expectExceptionMessage('reflection failure');
        $this->expectExceptionCode(2502);

        $inspector->inspect(new UserFixture(), $handler);
    }

    #[Group('fail-closed')]
    public function testInspectWrapsErrorIntoPropertyInspectionException(): void
    {
        $analyzer = self::createStub(AttributeAnalyzerContract::class);
        $analyzer->method('analyzeObject')
            ->willThrowException(new \Error('fatal error'));

        $inspector = new PropertyInspector($analyzer);
        $handler = new SpyAttributeHandler();

        $this->expectException(PropertyInspectionException::class);
        $this->expectExceptionMessage('fatal error');
        $this->expectExceptionCode(2505);

        $inspector->inspect(new UserFixture(), $handler);
    }

    // ── inspect: handler receives processed values ───────────────────

    public function testInspectPopulatesHandlerProcessedValues(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $inspector = new PropertyInspector($analyzer);
        $handler = new SpyAttributeHandler();

        $user = new UserFixture(name: 'Walmir', email: 'w@test.com', age: 30);
        $result = $inspector->inspect($user, $handler);

        $processed = $result->getProcessedPropertyValues();
        self::assertArrayHasKey('name', $processed);
        self::assertArrayHasKey('email', $processed);
        self::assertArrayHasKey('age', $processed);
    }

    #[Group('fail-closed')]
    public function testInspectPropagatesAnalyzerExceptionWithoutDoubleWrapping(): void
    {
        // The analyzer contract already throws PropertyInspectionException.
        // inspect() must re-throw that same instance, keeping its code (ADR-004).
        $original = PropertyInspectionException::failedToAnalyzeObjectError(new \Error('boom'));

        $analyzer = self::createStub(AttributeAnalyzerContract::class);
        $analyzer->method('analyzeObject')->willThrowException($original);

        $inspector = new PropertyInspector($analyzer);

        try {
            $inspector->inspect(new UserFixture(), new SpyAttributeHandler());
            self::fail('Expected PropertyInspectionException was not thrown');
        } catch (PropertyInspectionException $caught) {
            self::assertSame($original, $caught);
            self::assertSame(2503, $caught->getCode());
        }
    }

    // ── inspect: handler failures ────────────────────────────────────

    #[Group('fail-closed')]
    public function testInspectWrapsHandlerExceptionAsCode2504(): void
    {
        $inspector = new PropertyInspector(new AttributeAnalyzer(Validate::class));
        $cause = new \DomainException('handler rejected value');

        try {
            $inspector->inspect(new UserFixture(), new FailingAttributeHandler($cause));
            self::fail('Expected PropertyInspectionException was not thrown');
        } catch (PropertyInspectionException $e) {
            self::assertSame(2504, $e->getCode());
            self::assertSame($cause, $e->getPrevious());
        }
    }

    #[Group('fail-closed')]
    public function testInspectWrapsHandlerErrorAsCode2505(): void
    {
        $inspector = new PropertyInspector(new AttributeAnalyzer(Validate::class));
        $cause = new \TypeError('handler type failure');

        try {
            $inspector->inspect(new UserFixture(), new FailingAttributeHandler($cause));
            self::fail('Expected PropertyInspectionException was not thrown');
        } catch (PropertyInspectionException $e) {
            self::assertSame(2505, $e->getCode());
            self::assertSame($cause, $e->getPrevious());
        }
    }
}
