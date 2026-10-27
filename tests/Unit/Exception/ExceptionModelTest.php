<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Unit\Exception;

use KaririCode\Contract\Exception\ExceptionInterface;
use KaririCode\Contract\Exception\Runtime;
use KaririCode\PropertyInspector\Exception\ChangeRejectedException;
use KaririCode\PropertyInspector\Exception\InspectionFailedException;
use KaririCode\PropertyInspector\Exception\PropertyInspectionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InspectionFailedException::class)]
#[CoversClass(ChangeRejectedException::class)]
final class ExceptionModelTest extends TestCase
{
    private const string CAUSE_DETAIL = 'cause-detail-that-must-not-leak';

    /**
     * @return iterable<string, array{\Closure(): PropertyInspectionException, int, string}>
     */
    public static function factories(): iterable
    {
        yield 'attribute not instantiable' => [
            static fn(): PropertyInspectionException => InspectionFailedException::attributeNotInstantiable('App\\SignUp', 'email', new \Error(self::CAUSE_DETAIL)),
            2503,
            '[PI_2503] An attribute declared on App\\SignUp::$email cannot be instantiated.',
        ];
        yield 'attribute constructor failed' => [
            static fn(): PropertyInspectionException => InspectionFailedException::attributeConstructorFailed('App\\SignUp', 'email', new \RuntimeException(self::CAUSE_DETAIL)),
            2506,
            '[PI_2506] The constructor of an attribute declared on App\\SignUp::$email threw an exception.',
        ];
        yield 'uninitialized property' => [
            static fn(): PropertyInspectionException => InspectionFailedException::uninitializedProperty('App\\SignUp', 'email'),
            2507,
            '[PI_2507] App\\SignUp::$email carries a matching attribute but is not initialized.',
        ];
        yield 'unknown property' => [
            static fn(): PropertyInspectionException => ChangeRejectedException::unknownProperty('App\\SignUp', 'nickname', new \ReflectionException(self::CAUSE_DETAIL)),
            2520,
            '[PI_2520] App\\SignUp has no writable property named "nickname".',
        ];
        yield 'static property' => [
            static fn(): PropertyInspectionException => ChangeRejectedException::staticProperty('App\\Counter', 'shared'),
            2521,
            '[PI_2521] App\\Counter::$shared is static and is not part of an instance.',
        ];
        yield 'value rejected' => [
            static fn(): PropertyInspectionException => ChangeRejectedException::valueRejected('App\\SignUp', 'age', new \TypeError(self::CAUSE_DETAIL)),
            2522,
            '[PI_2522] App\\SignUp::$age rejects the new value.',
        ];
        yield 'not copyable' => [
            static fn(): PropertyInspectionException => ChangeRejectedException::notCopyable('App\\Suit', new \Error(self::CAUSE_DETAIL)),
            2523,
            '[PI_2523] Instances of App\\Suit cannot be copied without running a constructor.',
        ];
    }

    /**
     * @return iterable<string, array{\Closure(): PropertyInspectionException}>
     */
    public static function faults(): iterable
    {
        foreach (self::factories() as $name => $case) {
            yield $name => [$case[0]];
        }
    }

    /**
     * @param \Closure(): PropertyInspectionException $factory
     */
    #[Test]
    #[DataProvider('factories')]
    public function everyFactoryCarriesItsCodeAndAPrefixedMessage(\Closure $factory, int $code, string $message): void
    {
        $fault = $factory();

        self::assertSame($code, $fault->getCode());
        self::assertSame($message, $fault->getMessage());
    }

    /**
     * @param \Closure(): PropertyInspectionException $factory
     */
    #[Test]
    #[DataProvider('faults')]
    public function theCauseIsKeptButNeverEchoed(\Closure $factory): void
    {
        $fault = $factory();
        $previous = $fault->getPrevious();

        self::assertStringNotContainsString(self::CAUSE_DETAIL, $fault->getMessage(), 'Spec V4.3.3 E-047');
        if (null !== $previous) {
            self::assertSame(self::CAUSE_DETAIL, $previous->getMessage());
        }
    }

    /**
     * @param \Closure(): PropertyInspectionException $factory
     */
    #[Test]
    #[DataProvider('faults')]
    public function everyFailureIsInTheRuntimeFamily(\Closure $factory): void
    {
        $fault = $factory();

        self::assertInstanceOf(PropertyInspectionException::class, $fault);
        self::assertInstanceOf(Runtime::class, $fault);
        self::assertInstanceOf(ExceptionInterface::class, $fault);
        self::assertInstanceOf(\RuntimeException::class, $fault);
    }

    #[Test]
    public function aPropertyNameThatIsNotAnIdentifierIsNotEchoed(): void
    {
        $fault = ChangeRejectedException::unknownProperty('App\\SignUp', "x\n<script>");

        self::assertSame('[PI_2520] App\\SignUp has no writable property with the requested name, which is not a valid identifier.', $fault->getMessage());
        self::assertNull($fault->getPrevious());
    }

    #[Test]
    public function identifiersUpToSixtyFourCharactersAreShown(): void
    {
        $longest = str_repeat('a', 64);

        self::assertStringContainsString('"' . $longest . '"', ChangeRejectedException::unknownProperty('App\\SignUp', $longest)->getMessage());
        self::assertStringNotContainsString($longest . 'a', ChangeRejectedException::unknownProperty('App\\SignUp', $longest . 'a')->getMessage());
        self::assertStringContainsString('"_9"', ChangeRejectedException::unknownProperty('App\\SignUp', '_9')->getMessage());
    }
}
