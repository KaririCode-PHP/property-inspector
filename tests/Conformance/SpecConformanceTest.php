<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Conformance;

use KaririCode\PropertyInspector\Exception\PropertyInspectionException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Executable form of the KaririCode Spec V4.3.3 / ARFA 1.3 rules that apply to
 * this component (§4, §8.4, §17, §18, §24, §29, §31, §32).
 *
 * Deviations accepted for the 2.x line are listed in the constants below and
 * justified in ADR-008 and ADR-009. Each list is exact: a new violation fails,
 * and so does a fixed one, until the list is updated together with the ADR.
 */
#[CoversNothing]
final class SpecConformanceTest extends TestCase
{
    /** §18 getters kept for 2.x; they become properties in 3.0 (ADR-009). */
    private const array GETTER_DEVIATIONS = [
        'Contract\\PropertyAttributeHandler::getProcessedPropertyValues',
        'Contract\\PropertyAttributeHandler::getProcessingResultErrors',
        'Contract\\PropertyAttributeHandler::getProcessingResultMessages',
        'Utility\\PropertyAccessor::getValue',
    ];

    /** §18 arrays returned as domain values; Value Objects in 3.0 (ADR-009). */
    private const array ARRAY_RETURN_DEVIATIONS = [
        'AttributeAnalyzer::analyzeObject',
        'Contract\\AttributeAnalyzer::analyzeObject',
        'Contract\\PropertyAttributeHandler::getProcessedPropertyValues',
        'Contract\\PropertyAttributeHandler::getProcessingResultErrors',
        'Contract\\PropertyAttributeHandler::getProcessingResultMessages',
    ];

    /** `object` parameters, accepted permanently: the domain is any object (ADR-008). */
    private const array OBJECT_PARAMETER_DEVIATIONS = [
        'AttributeAnalyzer::analyzeObject($object)',
        'Contract\\AttributeAnalyzer::analyzeObject($object)',
        'Contract\\PropertyAttributeHandler::handleAttribute($attribute)',
        'Contract\\PropertyChangeApplier::applyChanges($object)',
        'Contract\\PropertyInspector::inspect($object)',
        'Utility\\PropertyAccessor::__construct($object)',
        'Utility\\PropertyInspector::inspect($object)',
    ];

    /** Concrete classes that are not final in 2.x (ADR-009). */
    private const array NON_FINAL_DEVIATIONS = ['Utility\\PropertyAccessor'];

    private const array FORBIDDEN_METHODS = ['toArray', 'fromArray', 'jsonSerialize', '__get', '__set', '__isset', '__unset', '__call', '__callStatic'];

    // ── §29.2 structure ─────────────────────────────────────────────

    public function testEverySourceFileIsStrictAndDeclaresOneTypeMatchingItsPath(): void
    {
        $violations = [];
        foreach (SourceTree::sourceFiles() as $relative => $path) {
            $code = SourceTree::read($path);
            $expected = SourceTree::NAMESPACE_ROOT . str_replace(['/', '.php'], ['\\', ''], $relative);
            $declared = self::typesDeclaredIn($code);
            if (! str_contains($code, 'declare(strict_types=1);') || [$expected] !== $declared) {
                $violations[] = $relative . ' declares [' . implode(', ', $declared) . ']';
            }
        }

        self::assertSame([], $violations);
    }

    public function testNamesCarryNoInterfaceSuffixNorVagueNoun(): void
    {
        $violations = array_values(array_filter(
            SourceTree::declaredTypes(),
            static fn(string $type): bool => 1 === preg_match('/(Interface|Manager|Helper|Util|Utils|Utility)$/', SourceTree::shortName($type)),
        ));

        self::assertSame([], $violations);
    }

    public function testNoSerializationOrMagicMethodsAndShallowInheritance(): void
    {
        $violations = [];
        foreach (SourceTree::declaredTypes() as $type) {
            $reflection = new \ReflectionClass($type);
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() === $type && \in_array($method->getName(), self::FORBIDDEN_METHODS, true)) {
                    $violations[] = $type . '::' . $method->getName();
                }
            }
            $depth = 0;
            for ($parent = $reflection->getParentClass(); false !== $parent; $parent = $parent->getParentClass()) {
                ++$depth;
            }
            if ($depth > 2) {
                $violations[] = $type . ' inheritance depth ' . $depth;
            }
        }

        self::assertSame([], $violations);
    }

    public function testConstantsAreTypedAndOverridesAreMarked(): void
    {
        $violations = [];
        foreach (SourceTree::declaredTypes() as $type) {
            $reflection = new \ReflectionClass($type);
            foreach ($reflection->getReflectionConstants() as $constant) {
                if ($constant->getDeclaringClass()->getName() === $type && ! $constant->hasType()) {
                    $violations[] = $type . '::' . $constant->getName() . ' is untyped';
                }
            }
            if ($reflection->isInterface()) {
                continue;
            }
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $type) {
                    continue;
                }
                foreach ($reflection->getInterfaces() as $interface) {
                    if ($interface->hasMethod($method->getName()) && [] === $method->getAttributes(\Override::class)) {
                        $violations[] = $type . '::' . $method->getName() . ' lacks #[\\Override]';
                    }
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function testConcreteClassesAreFinalExceptListedDeviations(): void
    {
        $nonFinal = [];
        foreach (SourceTree::declaredTypes() as $type) {
            $reflection = new \ReflectionClass($type);
            if (! $reflection->isInterface() && ! $reflection->isAbstract() && ! $reflection->isFinal()) {
                $nonFinal[] = substr($type, \strlen(SourceTree::NAMESPACE_ROOT));
            }
        }

        self::assertSame(self::NON_FINAL_DEVIATIONS, $nonFinal);
    }

    // ── §18 antipatterns (exact deviation lists) ────────────────────

    public function testGettersArrayReturnsAndObjectParametersMatchTheDeviationLists(): void
    {
        $getters = $arrays = $objects = [];
        foreach (SourceTree::declaredTypes() as $type) {
            $short = substr($type, \strlen(SourceTree::NAMESPACE_ROOT));
            foreach (new \ReflectionClass($type)->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $type) {
                    continue;
                }
                $name = $short . '::' . $method->getName();
                if (1 === preg_match('/^get[A-Z]/', $method->getName()) && 0 === $method->getNumberOfParameters()) {
                    $getters[] = $name;
                }
                if ('array' === (string) $method->getReturnType()) {
                    $arrays[] = $name;
                }
                foreach ($method->getParameters() as $parameter) {
                    if ('object' === (string) $parameter->getType()) {
                        $objects[] = $name . '($' . $parameter->getName() . ')';
                    }
                }
            }
        }
        sort($getters);
        sort($arrays);
        sort($objects);

        self::assertSame(self::GETTER_DEVIATIONS, $getters, 'parameterless getters (§18)');
        self::assertSame(self::ARRAY_RETURN_DEVIATIONS, $arrays, 'arrays as domain return types (§18)');
        self::assertSame(self::OBJECT_PARAMETER_DEVIATIONS, $objects, 'object parameters (ADR-008)');
    }

    public function testEveryCatchFailsClosed(): void
    {
        $violations = [];
        foreach (SourceTree::sourceFiles() as $relative => $path) {
            $tokens = \PhpToken::tokenize(SourceTree::read($path));
            foreach ($tokens as $index => $token) {
                if ($token->is(\T_CATCH) && ! self::blockAfterContainsThrow($tokens, $index)) {
                    $violations[] = $relative . ':' . $token->line;
                }
            }
        }

        self::assertSame([], $violations, 'a catch that does not re-throw fails open (§18)');
    }

    // ── §17 documentation ───────────────────────────────────────────

    public function testTypeDocumentationMeetsTheCriteria(): void
    {
        $violations = [];
        foreach (SourceTree::declaredTypes() as $type) {
            $reflection = new \ReflectionClass($type);
            $doc = (string) $reflection->getDocComment();
            $summary = trim((string) preg_replace('#^/\*\*\s*\*?\s*#', '', $doc));
            $required = ['@since' => '@since', 'thread-safety' => 'Thread-safety', 'reference' => '@see'];
            if (! $reflection->isInterface()) {
                $required += ['example (C4)' => 'Example:', 'side effects (C3)' => 'ide effects'];
            }
            if ('' === $doc || 1 === preg_match('/^(Represents|Class|Interface)\b/i', $summary)) {
                $violations[] = $type . ': missing or tautological domain role (C1)';
            }
            foreach ($required as $label => $needle) {
                if (! str_contains($doc, $needle)) {
                    $violations[] = $type . ': missing ' . $label;
                }
            }
        }

        self::assertSame([], $violations);
    }

    // ── §8.4 errors ─────────────────────────────────────────────────

    public function testErrorCodesAreUniqueInsideTheDeclaredRangeAndDocumented(): void
    {
        $range = (string) (self::composer()['extra']['kariricode']['error-code-range'] ?? '');
        self::assertSame(1, preg_match('/^PI-(\d{4})\x{2013}(\d{4})$/u', $range, $bounds), 'error-code-range');

        $codes = [];
        foreach (new \ReflectionClass(PropertyInspectionException::class)->getReflectionConstants() as $constant) {
            if (str_starts_with($constant->getName(), 'CODE_')) {
                $codes[] = (int) $constant->getValue();
            }
        }
        sort($codes);

        self::assertSame($codes, array_values(array_unique($codes)));
        self::assertGreaterThanOrEqual((int) $bounds[1], $codes[0]);
        self::assertLessThanOrEqual((int) $bounds[2], $codes[\count($codes) - 1]);

        preg_match_all('/^\|\s*(25\d\d)\s*\|/m', SourceTree::read(SourceTree::root() . '/docs/spec/SPEC-001-property-inspection-pipeline.md'), $documented);
        self::assertSame($codes, array_map('intval', $documented[1]), 'SPEC-001 error table');
    }

    public function testExceptionFamilyMigrationWaitsForContract211(): void
    {
        // Tripwire for ADR-009: when kariricode/contract 2.11 is installed, the
        // exception must implement Contract\Exception\Runtime (§8.4) and stop
        // echoing the cause message (E-047); this test must then be replaced.
        self::assertFalse(interface_exists('KaririCode\\Contract\\Exception\\Runtime'));
        self::assertStringContainsString('marker', PropertyInspectionException::failedToInspectObjectError(new \Error('marker'))->getMessage());
    }

    // ── §4 / §31 / §32 metadata, tooling, supply chain ──────────────

    public function testComposerDeclaresG4MetadataAndTheSpecToolchain(): void
    {
        $composer = self::composer();

        self::assertSame(
            ['arfa' => ['version' => '1.3', 'level' => 1], 'asvs' => ['version' => '5.0', 'level' => 1], 'spec' => '4.3.3', 'error-code-range' => "PI-2500\u{2013}2599"],
            $composer['extra']['kariricode'] ?? null,
            'Spec V4.3.3 §4, gate G4',
        );
        // kariricode/exception is a sibling dependency kept until 3.0 (ADR-009).
        self::assertSame(['php', 'kariricode/contract', 'kariricode/exception'], array_keys((array) ($composer['require'] ?? [])));
        $dev = (array) ($composer['require-dev'] ?? []);
        foreach (['phpunit/phpunit', 'phpstan/phpstan', 'vimeo/psalm', 'friendsofphp/php-cs-fixer', 'infection/infection', 'nikic/php-parser'] as $tool) {
            self::assertArrayHasKey($tool, $dev);
        }
        self::assertArrayNotHasKey('kariricode/devkit', $dev, 'the Specification prevails over the Devkit');

        foreach (['phpunit.xml.dist', 'phpstan.neon', 'psalm.xml', '.php-cs-fixer.dist.php', 'infection.json5', 'composer.lock', 'tools/complexity.php', 'tools/secret-scan.php', 'tools/sbom.php'] as $file) {
            self::assertFileExists(SourceTree::root() . '/' . $file);
        }
    }

    public function testLockIsVersionedButNotDistributedAndWorkflowsArePinned(): void
    {
        $root = SourceTree::root();
        self::assertStringNotContainsString('composer.lock', SourceTree::read($root . '/.gitignore'));
        self::assertMatchesRegularExpression('#^/composer\.lock\s+export-ignore$#m', SourceTree::read($root . '/.gitattributes'));

        $workflows = glob($root . '/.github/workflows/*.yml') ?: [];
        self::assertNotEmpty($workflows);
        foreach ($workflows as $workflow) {
            preg_match_all('/uses:\s*([^\s#]+)/', SourceTree::read($workflow), $uses);
            foreach ($uses[1] as $action) {
                self::assertMatchesRegularExpression('/@[0-9a-f]{40}$/', $action, basename($workflow) . ': ' . $action);
            }
        }
    }

    public function testTheTreeHasNoScaffoldingArtifacts(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(SourceTree::root(), \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        $artifacts = [];
        foreach ($iterator as $entry) {
            if ($entry instanceof \SplFileInfo && 1 !== preg_match('#/(vendor|\.git|build)/#', $entry->getPathname()) && str_contains($entry->getFilename(), '{')) {
                $artifacts[] = $entry->getPathname();
            }
        }

        self::assertSame([], $artifacts, 'brace-expansion leftovers');
    }

    // ── documentation layout and references ─────────────────────────

    public function testDocumentationLayoutAndAdrFormat(): void
    {
        $root = SourceTree::root();
        foreach (['README.md', 'CHANGELOG.md', 'SECURITY.md', 'CONTRIBUTING.md', 'docs/README.md', 'docs/spec/SPEC-001-property-inspection-pipeline.md', 'docs/quality/QD-V433-COMPLIANCE.md', 'docs/security/THREAT-MODEL.md'] as $file) {
            self::assertFileExists($root . '/' . $file);
        }

        $adrs = glob($root . '/docs/adr/ADR-*.md') ?: [];
        self::assertNotEmpty($adrs);
        foreach ($adrs as $adr) {
            $content = SourceTree::read($adr);
            foreach (['| Status |', '## Context', '## Decision', '## Alternatives Considered', '## Consequences', '## ARFA 1.3', '## References'] as $section) {
                self::assertStringContainsString($section, $content, basename($adr));
            }
        }
    }

    public function testEveryAdrAndSpecReferenceResolves(): void
    {
        $root = SourceTree::root();
        $known = [];
        foreach (array_merge(glob($root . '/docs/adr/*.md') ?: [], glob($root . '/docs/spec/*.md') ?: []) as $document) {
            if (1 === preg_match('/^((?:ADR|SPEC)-\d{3})/', basename($document), $id)) {
                $known[$id[1]] = true;
            }
        }

        $missing = [];
        foreach (array_merge(array_values(SourceTree::sourceFiles()), SourceTree::documentationFiles()) as $file) {
            preg_match_all('/\b((?:ADR|SPEC)-\d{3})\b/', SourceTree::read($file), $references);
            foreach (array_unique($references[1]) as $reference) {
                if (! isset($known[$reference])) {
                    $missing[] = $reference . ' in ' . substr($file, \strlen($root) + 1);
                }
            }
        }

        self::assertSame([], $missing);
    }

    // ── helpers ─────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private static function composer(): array
    {
        $composer = json_decode(SourceTree::read(SourceTree::root() . '/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);

        /** @var array<string, mixed> $composer */
        return $composer;
    }

    /** @return list<string> */
    private static function typesDeclaredIn(string $code): array
    {
        $tokens = \PhpToken::tokenize($code);
        $namespace = '';
        $types = [];
        foreach ($tokens as $index => $token) {
            if ($token->is(\T_NAMESPACE)) {
                $namespace = self::nextName($tokens, $index);
            }
            if ($token->is([\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM]) && ! self::previousIs($tokens, $index, [\T_DOUBLE_COLON, \T_NEW])) {
                $types[] = $namespace . '\\' . self::nextName($tokens, $index);
            }
        }

        return $types;
    }

    /** @param list<\PhpToken> $tokens */
    private static function nextName(array $tokens, int $index): string
    {
        for ($i = $index + 1, $count = \count($tokens); $i < $count; ++$i) {
            if ($tokens[$i]->is([\T_STRING, \T_NAME_QUALIFIED])) {
                return $tokens[$i]->text;
            }
        }

        return '';
    }

    /**
     * @param list<\PhpToken> $tokens
     * @param list<int>       $kinds
     */
    private static function previousIs(array $tokens, int $index, array $kinds): bool
    {
        for ($i = $index - 1; $i >= 0; --$i) {
            if (! $tokens[$i]->isIgnorable()) {
                return $tokens[$i]->is($kinds);
            }
        }

        return false;
    }

    /** @param list<\PhpToken> $tokens */
    private static function blockAfterContainsThrow(array $tokens, int $index): bool
    {
        $depth = 0;
        for ($i = $index + 1, $count = \count($tokens); $i < $count; ++$i) {
            if ('{' === $tokens[$i]->text) {
                ++$depth;
            } elseif ('}' === $tokens[$i]->text && 0 === --$depth) {
                return false;
            } elseif ($depth > 0 && $tokens[$i]->is(\T_THROW)) {
                return true;
            }
        }

        return false;
    }
}
