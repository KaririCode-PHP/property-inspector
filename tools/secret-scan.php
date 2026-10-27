<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tools\SecretScan;

/*
 * Secret scan of Spec V4.3.3 §24 (security gate). Every pattern is first
 * proven against a synthetic sample built at runtime (self-test), so a broken
 * pattern fails the gate instead of silently passing it; then the repository is
 * scanned, skipping vendor/, build/ and .git/. A line carrying the marker
 * "secret-scan:allow" is skipped (fixtures only). Exit code 1 on any finding.
 */

/** @return array<string, array{pattern: non-empty-string, sample: string}> */
function rules(): array
{
    return [
        'aws-access-key-id' => ['pattern' => '/\bAKIA[0-9A-Z]{16}\b/', 'sample' => 'AKIA' . str_repeat('Q', 16)],
        'private-key-block' => ['pattern' => '/-----BEGIN (?:[A-Z]+ )?PRIVATE KEY-----/', 'sample' => '-----BEGIN ' . 'RSA PRIVATE KEY-----'],
        'github-token' => ['pattern' => '/\bgh[pousr]_[A-Za-z0-9]{36}\b/', 'sample' => 'ghp_' . str_repeat('a', 36)],
        'slack-token' => ['pattern' => '/\bxox[abprs]-[A-Za-z0-9-]{10,}/', 'sample' => 'xoxb-' . str_repeat('1', 12)],
        'jwt' => ['pattern' => '/\beyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/', 'sample' => 'eyJ' . str_repeat('a', 12) . '.eyJ' . str_repeat('b', 12) . '.' . str_repeat('c', 12)],
        'credential-literal' => ['pattern' => '/\b(?:password|passwd|secret|api[_-]?key)\s*[:=]\s*[\'"][^\'"\s]{8,}[\'"]/i', 'sample' => 'password = "' . str_repeat('x', 10) . '"'],
    ];
}

function selfTest(): bool
{
    $ok = true;
    foreach (rules() as $name => $rule) {
        if (1 !== preg_match($rule['pattern'], $rule['sample'])) {
            fwrite(STDERR, "self-test failed: {$name}\n");
            $ok = false;
        }
    }

    return $ok;
}

/** @return list<string> */
function scannedFiles(string $root): array
{
    $files = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $entry) {
        if (! $entry instanceof \SplFileInfo || ! $entry->isFile()) {
            continue;
        }
        $relative = substr($entry->getPathname(), \strlen($root) + 1);
        if (1 === preg_match('#^(?:vendor|build|\.git|\.kcode)/#', $relative)) {
            continue;
        }
        $files[] = $entry->getPathname();
    }
    sort($files);

    return $files;
}

function main(string $root): int
{
    if (! selfTest()) {
        return 1;
    }

    $findings = 0;
    $files = scannedFiles($root);
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (false === $content) {
            continue;
        }
        foreach (explode("\n", $content) as $index => $line) {
            if (str_contains($line, 'secret-scan:allow')) {
                continue;
            }
            foreach (rules() as $name => $rule) {
                if (1 === preg_match($rule['pattern'], $line)) {
                    fwrite(STDERR, \sprintf("%s:%d: %s\n", substr($file, \strlen($root) + 1), $index + 1, $name));
                    ++$findings;
                }
            }
        }
    }
    printf("secret-scan: self-test passed for %d rules, %d files scanned, %d findings\n", \count(rules()), \count($files), $findings);

    return 0 === $findings ? 0 : 1;
}

exit(main(\dirname(__DIR__)));
