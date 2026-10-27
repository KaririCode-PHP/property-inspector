<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tests\Conformance;

/**
 * Read-only view of the repository used by the conformance suite: locates the
 * project root, lists the PHP files under src/ and maps them to the type names
 * PSR-4 expects.
 */
final class SourceTree
{
    public const string NAMESPACE_ROOT = 'KaririCode\\PropertyInspector\\';

    public static function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    public static function read(string $path): string
    {
        $content = file_get_contents($path);
        if (false === $content) {
            throw new \RuntimeException("Cannot read {$path}");
        }

        return $content;
    }

    /** @return array<string, string> path relative to src/ => absolute path */
    public static function sourceFiles(): array
    {
        $root = self::root() . '/src';
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry instanceof \SplFileInfo && 'php' === $entry->getExtension()) {
                $files[substr($entry->getPathname(), \strlen($root) + 1)] = $entry->getPathname();
            }
        }
        ksort($files);

        return $files;
    }

    /** @return list<class-string> */
    public static function declaredTypes(): array
    {
        $types = [];
        foreach (array_keys(self::sourceFiles()) as $relative) {
            /** @var class-string $type */
            $type = self::NAMESPACE_ROOT . str_replace(['/', '.php'], ['\\', ''], $relative);
            $types[] = $type;
        }

        return $types;
    }

    /** @return list<string> */
    public static function documentationFiles(): array
    {
        $root = self::root();
        $files = array_merge(
            glob($root . '/docs/*.md') ?: [],
            glob($root . '/docs/*/*.md') ?: [],
            [$root . '/README.md', $root . '/CHANGELOG.md', $root . '/SECURITY.md', $root . '/CONTRIBUTING.md'],
        );

        return array_values(array_filter($files, 'is_file'));
    }

    public static function shortName(string $type): string
    {
        return substr($type, (int) strrpos($type, '\\') + 1);
    }
}
