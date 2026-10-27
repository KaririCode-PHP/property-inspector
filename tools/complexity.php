<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tools\Complexity;

/*
 * Complexity gate of Spec V4.3.3 §13 and §29.1, per function or method:
 * cyclomatic complexity <= 10, cognitive complexity <= 15, parameters <= 4.
 *
 * Cyclomatic: McCabe (1976), 1 + one per decision point.
 * Cognitive: Campbell, "Cognitive Complexity" (SonarSource, 2018). Recursion and
 * labelled jumps are not counted; closures add nesting to their enclosing method.
 *
 * Usage: php tools/complexity.php <dir>...   Exit code 1 when a budget is exceeded.
 */

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

require \dirname(__DIR__) . '/vendor/autoload.php';

const MAX_CYCLOMATIC = 10;
const MAX_COGNITIVE = 15;
const MAX_PARAMETERS = 4;

/** @return list<string> */
function phpFiles(string $directory): array
{
    $files = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $entry) {
        if ($entry instanceof \SplFileInfo && 'php' === $entry->getExtension()) {
            $files[] = $entry->getPathname();
        }
    }
    sort($files);

    return $files;
}

function isDecisionPoint(Node $node): bool
{
    return $node instanceof Stmt\If_ || $node instanceof Stmt\ElseIf_ || $node instanceof Stmt\For_
        || $node instanceof Stmt\Foreach_ || $node instanceof Stmt\While_ || $node instanceof Stmt\Do_
        || $node instanceof Stmt\Catch_ || $node instanceof Expr\Ternary || $node instanceof Expr\BinaryOp\Coalesce
        || $node instanceof Expr\AssignOp\Coalesce || isLogicalOperator($node)
        || ($node instanceof Stmt\Case_ && null !== $node->cond)
        || ($node instanceof Node\MatchArm && null !== $node->conds);
}

function isLogicalOperator(Node $node): bool
{
    return $node instanceof Expr\BinaryOp\BooleanAnd || $node instanceof Expr\BinaryOp\BooleanOr
        || $node instanceof Expr\BinaryOp\LogicalAnd || $node instanceof Expr\BinaryOp\LogicalOr
        || $node instanceof Expr\BinaryOp\LogicalXor;
}

function isNestingStructure(Node $node): bool
{
    return $node instanceof Stmt\If_ || $node instanceof Expr\Ternary || $node instanceof Stmt\Switch_
        || $node instanceof Expr\Match_ || $node instanceof Stmt\For_ || $node instanceof Stmt\Foreach_
        || $node instanceof Stmt\While_ || $node instanceof Stmt\Do_ || $node instanceof Stmt\Catch_;
}

/** @return list<Node> */
function childrenOf(Node $node): array
{
    $children = [];
    foreach ($node->getSubNodeNames() as $name) {
        $value = $node->{$name};
        foreach (\is_array($value) ? $value : [$value] as $child) {
            if ($child instanceof Node) {
                $children[] = $child;
            }
        }
    }

    return $children;
}

function cyclomatic(Node $node): int
{
    $points = isDecisionPoint($node) ? 1 : 0;
    foreach (childrenOf($node) as $child) {
        $points += cyclomatic($child);
    }

    return $points;
}

function cognitive(Node $node, int $nesting, ?string $parentOperator): int
{
    $score = 0;
    $childNesting = $nesting;

    if (isNestingStructure($node)) {
        $score += 1 + $nesting;
        $childNesting = $nesting + 1;
    } elseif ($node instanceof Stmt\ElseIf_ || $node instanceof Stmt\Else_) {
        ++$score;
    } elseif ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
        $childNesting = $nesting + 1;
    }

    $operator = isLogicalOperator($node) ? $node::class : null;
    if (null !== $operator && $operator !== $parentOperator) {
        ++$score;
    }

    foreach (childrenOf($node) as $child) {
        $score += cognitive($child, $childNesting, $operator);
    }

    return $score;
}

/** @param list<Node> $statements */
function cognitiveOfBody(array $statements): int
{
    $score = 0;
    foreach ($statements as $statement) {
        $score += cognitive($statement, 0, null);
    }

    return $score;
}

/** @param list<Node> $statements */
function cyclomaticOfBody(array $statements): int
{
    $points = 1;
    foreach ($statements as $statement) {
        $points += cyclomatic($statement);
    }

    return $points;
}

/** @param array<mixed> $arguments */
function main(array $arguments): int
{
    $parser = new ParserFactory()->createForNewestSupportedVersion();
    $finder = new NodeFinder();
    $measured = 0;
    $violations = [];

    foreach ($arguments as $directory) {
        if (! \is_string($directory) || ! is_dir($directory)) {
            fwrite(STDERR, "Not a directory: " . var_export($directory, true) . "\n");

            return 1;
        }

        foreach (phpFiles($directory) as $file) {
            $code = file_get_contents($file);
            $ast = false === $code ? null : $parser->parse($code);
            if (null === $ast) {
                fwrite(STDERR, "Cannot parse {$file}\n");

                return 1;
            }

            foreach ($finder->findInstanceOf($ast, Stmt\ClassLike::class) as $class) {
                foreach ($class->getMethods() as $method) {
                    ++$measured;
                    $body = array_values($method->stmts ?? []);
                    $name = ($class->name?->toString() ?? 'class@anonymous') . '::' . $method->name->toString();
                    $metrics = [
                        'cyclomatic' => [cyclomaticOfBody($body), MAX_CYCLOMATIC],
                        'cognitive' => [cognitiveOfBody($body), MAX_COGNITIVE],
                        'parameters' => [\count($method->params), MAX_PARAMETERS],
                    ];
                    foreach ($metrics as $metric => [$value, $budget]) {
                        if ($value > $budget) {
                            $violations[] = \sprintf('%s %s = %d (budget %d) in %s', $name, $metric, $value, $budget, $file);
                        }
                    }
                }
            }
        }
    }

    foreach ($violations as $violation) {
        fwrite(STDERR, $violation . "\n");
    }
    printf("complexity: %d methods measured, %d budget breaches\n", $measured, \count($violations));

    return [] === $violations ? 0 : 1;
}

exit(main(\array_slice((array) ($_SERVER['argv'] ?? []), 1)));
