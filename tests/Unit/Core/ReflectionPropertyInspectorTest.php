<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Unit\Core;

use KaririCode\Contract\Exception\Runtime;
use KaririCode\Contract\Exception\Validation;
use KaririCode\PropertyInspector\Core\AnalyzedClass;
use KaririCode\PropertyInspector\Core\AnalyzedProperty;
use KaririCode\PropertyInspector\Core\AttributeAnalyzer;
use KaririCode\PropertyInspector\Core\ReflectionPropertyInspector;
use KaririCode\PropertyInspector\Exception\InspectionFailedException;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\ClassOnly;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Rule;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Validate;
use KaririCode\PropertyInspector\Tests\Fixture\BrokenDeclaration;
use KaririCode\PropertyInspector\Tests\Fixture\Counter;
use KaririCode\PropertyInspector\Tests\Fixture\Draft;
use KaririCode\PropertyInspector\Tests\Fixture\HalfBuilt;
use KaririCode\PropertyInspector\Tests\Fixture\InvalidEmail;
use KaririCode\PropertyInspector\Tests\Fixture\MisplacedDeclaration;
use KaririCode\PropertyInspector\Tests\Fixture\Profile;
use KaririCode\PropertyInspector\Tests\Fixture\RejectingHandler;
use KaririCode\PropertyInspector\Tests\Fixture\RuleCollector;
use KaririCode\PropertyInspector\Tests\Fixture\SignUp;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReflectionPropertyInspector::class)]
#[CoversClass(AttributeAnalyzer::class)]
#[CoversClass(AnalyzedClass::class)]
#[CoversClass(AnalyzedProperty::class)]
#[CoversClass(InspectionFailedException::class)]
final class ReflectionPropertyInspectorTest extends TestCase
{
    // ── semantics of Contract\Inspection\PropertyInspector (contract SPEC-001 §2.4) ──

    #[Test]
    public function everyMatchingAttributeReachesTheHandlerInDeclarationOrder(): void
    {
        $collector = new RuleCollector();

        new ReflectionPropertyInspector()->inspect(self::signUp(), Sanitize::class, $collector);

        self::assertSame(['trim', 'lowercase'], $collector->rules['email'], 'a Validate is not a Sanitize; order is declaration order');
        self::assertSame(['trim'], $collector->rules['name'], 'private properties of the class itself are inspected');
        self::assertArrayNotHasKey('age', $collector->rules, 'a property without the attribute never reaches the handler');
        self::assertSame(['email', 'name', 'tenant'], array_keys($collector->rules), 'own properties first, then inherited ones');
    }

    #[Test]
    public function anInterfaceMatchesEveryAttributeThatImplementsIt(): void
    {
        $collector = new RuleCollector();

        new ReflectionPropertyInspector()->inspect(self::signUp(), Rule::class, $collector);

        self::assertSame(['trim', 'email', 'lowercase'], $collector->rules['email']);
    }

    #[Test]
    public function inheritedProtectedPropertiesAreInspectedButAParentsPrivateOnesAreNot(): void
    {
        $collector = new RuleCollector();

        new ReflectionPropertyInspector()->inspect(self::signUp(), Sanitize::class, $collector);

        self::assertSame(['trim'], $collector->rules['tenant']);
        self::assertArrayNotHasKey('audit', $collector->rules, 'Account::$audit belongs to Account');
    }

    #[Test]
    public function theHandlerReceivesTheValueHeldDuringTheCall(): void
    {
        $draft = new Draft();
        $inspector = new ReflectionPropertyInspector();

        $inspector->inspect($draft, Sanitize::class, $first = new RuleCollector());
        $draft->title = 'second';
        $inspector->inspect($draft, Sanitize::class, $second = new RuleCollector());

        self::assertSame([['title', 'trim', 'first']], $first->calls);
        self::assertSame([['title', 'trim', 'second']], $second->calls, 'values are never cached');
    }

    #[Test]
    public function inspectingNeverWritesToTheObject(): void
    {
        $draft = new Draft();
        $before = clone $draft;

        new ReflectionPropertyInspector()->inspect($draft, Sanitize::class, new RuleCollector());

        self::assertEquals($before, $draft);
    }

    #[Test]
    #[Group('fail-closed')]
    public function anUninitializedAnnotatedPropertyIsARuntimeFault(): void
    {
        $collector = new RuleCollector();

        try {
            new ReflectionPropertyInspector()->inspect(new HalfBuilt(), Sanitize::class, $collector);
            self::fail('an uninitialized annotated property must not pass');
        } catch (InspectionFailedException $fault) {
            self::assertInstanceOf(Runtime::class, $fault);
            self::assertSame(2507, $fault->getCode());
            self::assertSame('[PI_2507] ' . HalfBuilt::class . '::$name carries a matching attribute but is not initialized.', $fault->getMessage());
        }

        self::assertSame([], $collector->calls);
    }

    #[Test]
    #[Group('fail-closed')]
    public function anAttributeThatCannotBeInstantiatedIsARuntimeFaultRaisedBeforeTheHandlerRuns(): void
    {
        $collector = new RuleCollector();

        try {
            new ReflectionPropertyInspector()->inspect(new BrokenDeclaration(), Rule::class, $collector);
            self::fail('an unusable declaration must not pass');
        } catch (InspectionFailedException $fault) {
            self::assertSame(2506, $fault->getCode());
            self::assertInstanceOf(LogicException::class, $fault->getPrevious());
            self::assertStringNotContainsString('declared wrong', $fault->getMessage(), 'the cause is kept, not echoed (E-047)');
        }

        self::assertSame([], $collector->calls, 'the valid property declared before it was not handed over either');
    }

    #[Test]
    public function anExceptionFromTheHandlerKeepsItsFamily(): void
    {
        try {
            new ReflectionPropertyInspector()->inspect(self::signUp(), Validate::class, new RejectingHandler());
            self::fail('the handler rejected the value');
        } catch (\Throwable $thrown) {
            self::assertInstanceOf(InvalidEmail::class, $thrown, 'the handler\'s own exception, not a wrapper');
            self::assertInstanceOf(Validation::class, $thrown, 'the edge still sees a 422');
            self::assertNotInstanceOf(Runtime::class, $thrown, 'not turned into the inspector\'s own family');
        }
    }

    // ── 3.0 specifics ──────────────────────────────────────────────────────

    #[Test]
    #[Group('fail-closed')]
    public function anAttributeThatCannotTargetPropertiesIsARuntimeFault(): void
    {
        try {
            new ReflectionPropertyInspector()->inspect(new MisplacedDeclaration(), ClassOnly::class, new RuleCollector());
            self::fail('a class-only attribute on a property cannot be built');
        } catch (InspectionFailedException $fault) {
            self::assertSame(2503, $fault->getCode());
            self::assertInstanceOf(\Error::class, $fault->getPrevious());
            self::assertSame('[PI_2503] An attribute declared on ' . MisplacedDeclaration::class . '::$field cannot be instantiated.', $fault->getMessage());
        }
    }

    #[Test]
    public function staticPropertiesAreNotInspected(): void
    {
        $collector = new RuleCollector();

        new ReflectionPropertyInspector()->inspect(new Counter(), Sanitize::class, $collector);

        self::assertSame([['label', 'trim', ' counter ']], $collector->calls);
    }

    #[Test]
    public function aVirtualPropertyIsReadThroughItsGetHook(): void
    {
        $collector = new RuleCollector();

        new ReflectionPropertyInspector()->inspect(new Profile('Walmir', 'Silva'), Sanitize::class, $collector);

        self::assertSame([['fullName', 'trim', 'Walmir Silva']], $collector->calls);
    }

    #[Test]
    public function attributeInstancesAreSharedUntilTheCacheIsCleared(): void
    {
        $inspector = new ReflectionPropertyInspector();

        $inspector->inspect(new Draft(), Sanitize::class, $first = new RuleCollector());
        $inspector->inspect(new Draft(), Sanitize::class, $second = new RuleCollector());
        $inspector->clearCache();
        $inspector->inspect(new Draft(), Sanitize::class, $third = new RuleCollector());

        self::assertSame($first->attributes[0], $second->attributes[0], 'cache hit: the same instance (SPEC-001 INV-012)');
        self::assertNotSame($first->attributes[0], $third->attributes[0], 'after clearCache() the attribute is built again');
        self::assertEquals($first->attributes[0], $third->attributes[0]);
    }

    #[Test]
    public function eachAttributeClassIsAnalyzedOnItsOwn(): void
    {
        $inspector = new ReflectionPropertyInspector();

        $inspector->inspect(self::signUp(), Sanitize::class, $sanitize = new RuleCollector());
        $inspector->inspect(self::signUp(), Validate::class, $validate = new RuleCollector());

        self::assertSame(['email', 'name', 'tenant'], array_keys($sanitize->rules));
        self::assertSame(['email' => ['email']], $validate->rules, 'a cached Sanitize analysis does not leak into a Validate one');
    }

    #[Test]
    public function aSharedAnalyzerIsUsedWhenInjected(): void
    {
        $analyzer = new AttributeAnalyzer();
        $inspector = new ReflectionPropertyInspector($analyzer);

        $inspector->inspect(new Draft(), Sanitize::class, $collector = new RuleCollector());

        self::assertSame($collector->attributes[0], $analyzer->analyze(Draft::class, Sanitize::class)->properties[0]->attributes[0]);
    }

    private static function signUp(): SignUp
    {
        return new SignUp(email: ' A@B.org ', name: ' Walmir ', age: 30);
    }
}
