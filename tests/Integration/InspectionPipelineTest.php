<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Integration;

use KaririCode\Contract\Inspection\PropertyChangeApplier;
use KaririCode\Contract\Inspection\PropertyInspector;
use KaririCode\PropertyInspector\Core\ReflectionChangeApplier;
use KaririCode\PropertyInspector\Core\ReflectionPropertyInspector;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Sanitize;
use KaririCode\PropertyInspector\Tests\Fixture\Attribute\Validate;
use KaririCode\PropertyInspector\Tests\Fixture\EmailRules;
use KaririCode\PropertyInspector\Tests\Fixture\RuleCollector;
use KaririCode\PropertyInspector\Tests\Fixture\SignUp;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The flow a processor runs through the contracts only: inspect the DPO,
 * process what the handler collected, write the result back to a copy, then
 * validate the copy. Both implementations are typed as the contracts.
 */
#[CoversNothing]
final class InspectionPipelineTest extends TestCase
{
    private PropertyInspector $inspector;

    private PropertyChangeApplier $applier;

    #[\Override]
    protected function setUp(): void
    {
        $this->inspector = new ReflectionPropertyInspector();
        $this->applier = new ReflectionChangeApplier();
    }

    #[Test]
    public function aReadonlyDpoIsSanitizedByInspectingThenApplyingACopy(): void
    {
        $signUp = new SignUp(email: ' A@B.org ', name: ' Walmir ', age: 30);

        $clean = $this->sanitize($signUp);

        self::assertSame(['a@b.org', 'Walmir', 'acme', 30], [$clean->email, $clean->name(), $clean->tenant(), $clean->age]);
        self::assertSame(' A@B.org ', $signUp->email, 'the original is untouched');
    }

    #[Test]
    public function theCopyIsValidatedNotTheOriginal(): void
    {
        $signUp = new SignUp(email: ' A@B.org ', name: 'Walmir', age: 30);

        $this->inspector->inspect($signUp, Validate::class, $before = new EmailRules());
        $this->inspector->inspect($this->sanitize($signUp), Validate::class, $after = new EmailRules());

        self::assertSame(['email' => 'not an e-mail address'], $before->errors, 'padding makes the raw value invalid');
        self::assertSame([], $after->errors, 'sanitize, then validate');
    }

    #[Test]
    public function oneInspectorServesEveryAttributeClassAndEveryObject(): void
    {
        $first = new SignUp(email: 'x@y.org', name: 'A', age: 1);
        $second = new SignUp(email: 'z@w.org', name: 'B', age: 2);

        $this->inspector->inspect($first, Sanitize::class, $a = new RuleCollector());
        $this->inspector->inspect($second, Sanitize::class, $b = new RuleCollector());
        $this->inspector->inspect($second, Validate::class, $c = new RuleCollector());

        self::assertSame(['x@y.org', 'z@w.org'], [$a->values['email'], $b->values['email']]);
        self::assertSame(['email' => ['email']], $c->rules);
    }

    private function sanitize(SignUp $signUp): SignUp
    {
        $this->inspector->inspect($signUp, Sanitize::class, $rules = new RuleCollector());

        $changes = [];
        foreach ($rules->rules as $property => $list) {
            $changes[$property] = self::apply($rules->values[$property], $list);
        }

        return $this->applier->applyChanges($signUp, $changes);
    }

    /**
     * @param list<string> $rules
     */
    private static function apply(mixed $value, array $rules): mixed
    {
        return array_reduce(
            $rules,
            static fn(mixed $carry, string $rule): mixed => match (true) {
                'trim' === $rule && \is_string($carry) => trim($carry),
                'lowercase' === $rule && \is_string($carry) => strtolower($carry),
                default => $carry,
            },
            $value,
        );
    }
}
