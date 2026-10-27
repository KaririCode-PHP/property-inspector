<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Integration;

use KaririCode\PropertyInspector\AttributeAnalyzer;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Validate;
use KaririCode\PropertyInspector\Tests\Fixture\ReflectionChangeApplier;
use KaririCode\PropertyInspector\Tests\Fixture\SimpleValidationHandler;
use KaririCode\PropertyInspector\Tests\Fixture\TrimLowercaseHandler;
use KaririCode\PropertyInspector\Tests\Fixture\UserFixture;
use KaririCode\PropertyInspector\Utility\PropertyAccessor;
use KaririCode\PropertyInspector\Utility\PropertyInspector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests exercising the full inspection pipeline:
 * AttributeAnalyzer → PropertyInspector → Handler → PropertyAccessor.
 *
 * These tests verify cross-component behavior that unit tests cannot cover,
 * including multi-pass inspection, handler state accumulation,
 * and round-trip property mutation via PropertyAccessor.
 */
#[CoversClass(AttributeAnalyzer::class)]
#[CoversClass(PropertyInspector::class)]
#[CoversClass(PropertyAccessor::class)]
final class InspectionPipelineTest extends TestCase
{
    // ── Full pipeline: inspect → collect → apply ─────────────────────

    public function testFullPipelineInspectCollectAndApplyChanges(): void
    {
        $user = new UserFixture(name: '  Walmir  ', email: '  W@TEST.COM  ', age: 30);

        // Pass 1: Sanitize (trim + lowercase)
        $sanitizeAnalyzer = new AttributeAnalyzer(Sanitize::class);
        $sanitizeInspector = new PropertyInspector($sanitizeAnalyzer);
        $sanitizeHandler = new TrimLowercaseHandler();

        $sanitizeInspector->inspect($user, $sanitizeHandler);

        // Apply sanitized values back to the object
        $applier = new ReflectionChangeApplier($sanitizeHandler->getProcessedPropertyValues());
        $applier->applyChanges($user);

        self::assertSame('Walmir', $user->name);
        self::assertSame('w@test.com', $user->email);

        // Pass 2: Validate (on sanitized data)
        $validateAnalyzer = new AttributeAnalyzer(Validate::class);
        $validateInspector = new PropertyInspector($validateAnalyzer);
        $validateHandler = new SimpleValidationHandler();

        $validateInspector->inspect($user, $validateHandler);

        self::assertSame([], $validateHandler->getProcessingResultErrors());
    }

    // ── Multi-pass: same analyzer, different objects ──────────────────

    public function testMultiPassInspectionSharesCachedMetadata(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $inspector = new PropertyInspector($analyzer);

        $users = [
            new UserFixture(name: 'Alice', email: 'alice@test.com', age: 25),
            new UserFixture(name: 'Bob', email: 'bob@test.com', age: 35),
            new UserFixture(name: 'Charlie', email: 'charlie@test.com', age: 45),
        ];

        $allProcessed = [];
        foreach ($users as $user) {
            $handler = new SimpleValidationHandler();
            $inspector->inspect($user, $handler);
            $allProcessed[] = $handler->getProcessedPropertyValues();
        }

        self::assertCount(3, $allProcessed);
        self::assertSame('Alice', $allProcessed[0]['name']);
        self::assertSame('Bob', $allProcessed[1]['name']);
        self::assertSame('Charlie', $allProcessed[2]['name']);
    }

    // ── PropertyAccessor round-trip integration ──────────────────────

    public function testPropertyAccessorRoundTripWithInspection(): void
    {
        $user = new UserFixture(name: 'Original', email: 'orig@test.com', age: 20);

        // Inspect to get attribute data
        $analyzer = new AttributeAnalyzer(Validate::class);
        $inspector = new PropertyInspector($analyzer);
        $handler = new SimpleValidationHandler();
        $inspector->inspect($user, $handler);

        // Mutate via PropertyAccessor
        $accessor = new PropertyAccessor($user, 'name');
        $accessor->setValue('Modified');

        // Re-inspect — should see the updated value
        $handler2 = new SimpleValidationHandler();
        $inspector->inspect($user, $handler2);

        self::assertSame('Modified', $handler2->getProcessedPropertyValues()['name']);
    }

    // ── ClearCache forces re-analysis ────────────────────────────────

    public function testClearCacheForcesReAnalysisOnNextInspection(): void
    {
        $analyzer = new AttributeAnalyzer(Validate::class);
        $inspector = new PropertyInspector($analyzer);

        $user = new UserFixture(name: 'Before', email: 'b@test.com', age: 30);
        $handler1 = new SimpleValidationHandler();
        $inspector->inspect($user, $handler1);

        $analyzer->clearCache();

        $user->name = 'After';
        $handler2 = new SimpleValidationHandler();
        $inspector->inspect($user, $handler2);

        self::assertSame('After', $handler2->getProcessedPropertyValues()['name']);
    }

    // ── Error accumulation across properties ─────────────────────────

    public function testValidationErrorsAccumulateAcrossProperties(): void
    {
        $user = new UserFixture(name: '', email: 'not-an-email', age: 10);

        $analyzer = new AttributeAnalyzer(Validate::class);
        $inspector = new PropertyInspector($analyzer);
        $handler = new SimpleValidationHandler();

        $inspector->inspect($user, $handler);

        $errors = $handler->getProcessingResultErrors();
        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('email', $errors);
        self::assertArrayHasKey('age', $errors);
    }
}
