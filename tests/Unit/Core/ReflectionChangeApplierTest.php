<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Unit\Core;

use InvalidArgumentException;
use KaririCode\Contract\Exception\Runtime;
use KaririCode\PropertyInspector\Core\ReflectionChangeApplier;
use KaririCode\PropertyInspector\Exception\ChangeRejectedException;
use KaririCode\PropertyInspector\Tests\Fixture\Counter;
use KaririCode\PropertyInspector\Tests\Fixture\GuardedName;
use KaririCode\PropertyInspector\Tests\Fixture\Newsletter;
use KaririCode\PropertyInspector\Tests\Fixture\Profile;
use KaririCode\PropertyInspector\Tests\Fixture\RedeclaredTenant;
use KaririCode\PropertyInspector\Tests\Fixture\ShadowChild;
use KaririCode\PropertyInspector\Tests\Fixture\SignUp;
use KaririCode\PropertyInspector\Tests\Fixture\Suit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;
use ReflectionException;
use ReflectionProperty;
use TypeError;

#[CoversClass(ReflectionChangeApplier::class)]
#[CoversClass(ChangeRejectedException::class)]
final class ReflectionChangeApplierTest extends TestCase
{
    // ── semantics of Contract\Inspection\PropertyChangeApplier (contract SPEC-001 §2.4) ──

    #[Test]
    public function theCopyHoldsTheChangesAndTheReadonlyOriginalIsUntouched(): void
    {
        $signUp = self::signUp();

        $copy = new ReflectionChangeApplier()->applyChanges($signUp, ['email' => 'a@b.org', 'name' => 'Walmir']);

        self::assertNotSame($signUp, $copy);
        self::assertSame(['a@b.org', 'Walmir', 30], [$copy->email, $copy->name(), $copy->age]);
        self::assertSame([' A@B.org ', ' Walmir '], [$signUp->email, $signUp->name()]);
    }

    #[Test]
    public function unchangedStateIsCopiedIncludingAParentsPrivateProperties(): void
    {
        $copy = new ReflectionChangeApplier()->applyChanges(self::signUp(), ['age' => 31]);

        self::assertSame(31, $copy->age);
        self::assertSame(' acme ', $copy->tenant());
        self::assertSame('created', $copy->audit(), 'Account::$audit is invisible to SignUp::getProperties()');
    }

    #[Test]
    public function anInheritedProtectedPropertyCanBeChanged(): void
    {
        $signUp = self::signUp();

        $copy = new ReflectionChangeApplier()->applyChanges($signUp, ['tenant' => 'acme']);

        self::assertSame(['acme', ' acme '], [$copy->tenant(), $signUp->tenant()]);
    }

    #[Test]
    #[Group('fail-closed')]
    public function aParentsPrivatePropertyCannotBeNamed(): void
    {
        try {
            new ReflectionChangeApplier()->applyChanges(self::signUp(), ['audit' => 'forged']);
            self::fail('a parent\'s private property is not part of the class\'s view');
        } catch (ChangeRejectedException $fault) {
            self::assertInstanceOf(Runtime::class, $fault);
            self::assertSame(2520, $fault->getCode());
            self::assertInstanceOf(ReflectionException::class, $fault->getPrevious());
        }
    }

    #[Test]
    public function aChangeRunsTheSetHookAndACopiedValueDoesNot(): void
    {
        $original = new Newsletter('A@B.org');
        $applier = new ReflectionChangeApplier();

        $copied = $applier->applyChanges($original, []);
        $changed = $applier->applyChanges($original, ['email' => 'NEW@B.org']);

        self::assertSame([1, 'a@b.org'], [$copied->writes, $copied->email], 'copied as stored');
        self::assertSame([2, 'new@b.org'], [$changed->writes, $changed->email], 'written as an assignment');
        self::assertSame([1, 'a@b.org'], [$original->writes, $original->email]);
    }

    #[Test]
    public function anUninitializedPropertyStaysUninitializedUnlessChanged(): void
    {
        $original = new Newsletter('a@b.org');
        $applier = new ReflectionChangeApplier();
        $topic = new ReflectionProperty(Newsletter::class, 'topic');

        self::assertFalse($topic->isInitialized($applier->applyChanges($original, [])));
        self::assertSame('php', $applier->applyChanges($original, ['topic' => 'php'])->topic);
    }

    #[Test]
    public function anEmptyChangeSetStillYieldsANewEqualInstance(): void
    {
        $signUp = self::signUp();

        $copy = new ReflectionChangeApplier()->applyChanges($signUp, []);

        self::assertNotSame($signUp, $copy);
        self::assertEquals($signUp, $copy);
    }

    #[Test]
    #[Group('fail-closed')]
    public function anUnknownPropertyIsARuntimeFault(): void
    {
        try {
            new ReflectionChangeApplier()->applyChanges(self::signUp(), ['nickname' => 'w']);
            self::fail('there is no such property');
        } catch (ChangeRejectedException $fault) {
            self::assertSame(2520, $fault->getCode());
            self::assertSame('[PI_2520] ' . SignUp::class . ' has no writable property named "nickname".', $fault->getMessage());
        }
    }

    #[Test]
    #[Group('fail-closed')]
    public function aValueOfTheWrongTypeIsARuntimeFault(): void
    {
        try {
            new ReflectionChangeApplier()->applyChanges(self::signUp(), ['age' => 'thirty']);
            self::fail('an int property cannot hold a string');
        } catch (ChangeRejectedException $fault) {
            self::assertInstanceOf(Runtime::class, $fault);
            self::assertSame(2522, $fault->getCode());
            self::assertInstanceOf(TypeError::class, $fault->getPrevious());
            self::assertStringNotContainsString('thirty', $fault->getMessage(), 'the rejected value is never shown (E-047)');
        }
    }

    // ── 3.0 specifics ──────────────────────────────────────────────────────

    #[Test]
    public function aPropertyRedeclaredByASubclassIsWrittenOnce(): void
    {
        $original = new RedeclaredTenant(' acme ');
        $applier = new ReflectionChangeApplier();

        $copied = $applier->applyChanges($original, ['label' => 'copy']);
        $changed = $applier->applyChanges($original, ['tenant' => 'acme']);

        self::assertSame([' acme ', 'copy'], [$copied->tenant(), $copied->label], 'one readonly slot, initialized once');
        self::assertSame(['acme', 'main'], [$changed->tenant(), $changed->label]);
        self::assertSame(' acme ', $original->tenant());
    }

    #[Test]
    #[Group('fail-closed')]
    public function aStaticPropertyCannotBeChanged(): void
    {
        try {
            new ReflectionChangeApplier()->applyChanges(new Counter(), ['shared' => 'other']);
            self::fail('static state does not belong to the copy');
        } catch (ChangeRejectedException $fault) {
            self::assertSame(2521, $fault->getCode());
        }

        self::assertSame('class state', Counter::$shared);
    }

    #[Test]
    public function aVirtualPropertyIsComputedOnTheCopyNotCopied(): void
    {
        $copy = new ReflectionChangeApplier()->applyChanges(new Profile('Walmir', 'Silva'), ['last' => 'Souza']);

        self::assertSame('Walmir Souza', $copy->fullName);
    }

    #[Test]
    #[Group('fail-closed')]
    public function aVirtualPropertyWithoutASetHookRejectsAChange(): void
    {
        try {
            new ReflectionChangeApplier()->applyChanges(new Profile('Walmir', 'Silva'), ['fullName' => 'Other']);
            self::fail('a get-only virtual property cannot be written');
        } catch (ChangeRejectedException $fault) {
            self::assertSame(2522, $fault->getCode());
            self::assertInstanceOf(\Error::class, $fault->getPrevious());
        }
    }

    #[Test]
    public function anExceptionFromASetHookPropagatesUnchanged(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A name must not be empty.');

        new ReflectionChangeApplier()->applyChanges(new GuardedName('Walmir'), ['name' => '']);
    }

    #[Test]
    #[Group('fail-closed')]
    public function anEnumCannotBeCopied(): void
    {
        try {
            new ReflectionChangeApplier()->applyChanges(Suit::Hearts, []);
            self::fail('an enum case cannot be instantiated');
        } catch (ChangeRejectedException $fault) {
            self::assertSame(2523, $fault->getCode());
            self::assertInstanceOf(\Error::class, $fault->getPrevious());
        }
    }

    #[Test]
    #[Group('fail-closed')]
    public function anInternalFinalClassCannotBeCopied(): void
    {
        try {
            new ReflectionChangeApplier()->applyChanges(new Randomizer(), []);
            self::fail('an internal final class needs its constructor');
        } catch (ChangeRejectedException $fault) {
            self::assertSame(2523, $fault->getCode());
            self::assertInstanceOf(ReflectionException::class, $fault->getPrevious());
        }
    }

    #[Test]
    #[Group('fail-closed')]
    public function anIntegerKeyNamesNoProperty(): void
    {
        try {
            new ReflectionChangeApplier()->applyChanges(self::signUp(), [0 => 'x']);
            self::fail('a list is not a change set');
        } catch (ChangeRejectedException $fault) {
            self::assertSame(2520, $fault->getCode());
            self::assertStringEndsWith('has no writable property with the requested name, which is not a valid identifier.', $fault->getMessage());
        }
    }

    #[Test]
    #[Group('fail-closed')]
    public function namesAreResolvedBeforeAnyValueIsWritten(): void
    {
        $original = new GuardedName('Walmir');

        try {
            new ReflectionChangeApplier()->applyChanges($original, ['name' => '', 'missing' => 1]);
            self::fail('an unknown name must stop the change set');
        } catch (ChangeRejectedException $fault) {
            self::assertSame(2520, $fault->getCode(), 'the unknown name wins over the hook that would reject the empty value');
        }
    }

    #[Test]
    public function aParentsPrivatePropertyNamedLikeAChildPropertyIsItsOwnSlot(): void
    {
        $original = new ShadowChild();
        $applier = new ReflectionChangeApplier();

        $public = $applier->applyChanges($original, ['token' => 'new-token']);
        $private = $applier->applyChanges($original, ['code' => 'new-code']);

        self::assertSame(['new-token', 'base-token', 'child-code', 'base-code'], [$public->token, $public->baseToken(), $public->childCode(), $public->baseCode()]);
        self::assertSame(['child-token', 'base-token', 'new-code', 'base-code'], [$private->token, $private->baseToken(), $private->childCode(), $private->baseCode()]);
    }

    #[Test]
    public function anObjectWithAStaticPropertyIsCopiedWithoutTouchingIt(): void
    {
        $copy = new ReflectionChangeApplier()->applyChanges(new Counter(), ['label' => 'copy']);

        self::assertSame('copy', $copy->label);
        self::assertSame('class state', Counter::$shared);
    }

    private static function signUp(): SignUp
    {
        return new SignUp(email: ' A@B.org ', name: ' Walmir ', age: 30);
    }
}
