<?php

declare(strict_types=1);

namespace KaririCode\PropertyInspector\Tools\Sbom;

/*
 * Writes a CycloneDX 1.5 SBOM (JSON) for this package from composer.lock into
 * build/sbom.cdx.json (Spec V4.3.3 §32). Runtime packages get scope "required";
 * development packages get scope "excluded", since they are not distributed.
 */

/**
 * @param array<mixed> $package
 *
 * @return array<string, mixed>|null
 */
function component(array $package, string $scope): ?array
{
    $name = $package['name'] ?? null;
    $version = $package['version'] ?? null;
    if (! \is_string($name) || ! \is_string($version) || ! str_contains($name, '/')) {
        return null;
    }
    [$group, $short] = explode('/', $name, 2);
    $purl = "pkg:composer/{$name}@{$version}";
    $licenses = [];
    foreach ((array) ($package['license'] ?? []) as $license) {
        if (\is_string($license)) {
            $licenses[] = ['license' => ['id' => $license]];
        }
    }

    return [
        'type' => 'library',
        'bom-ref' => $purl,
        'group' => $group,
        'name' => $short,
        'version' => $version,
        'scope' => $scope,
        'purl' => $purl,
        'licenses' => $licenses,
    ];
}

function uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
    $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function main(string $root): int
{
    $lockJson = file_get_contents($root . '/composer.lock');
    $composerJson = file_get_contents($root . '/composer.json');
    if (false === $lockJson || false === $composerJson) {
        fwrite(STDERR, "composer.json and composer.lock are required\n");

        return 1;
    }
    $lock = json_decode($lockJson, true, 512, JSON_THROW_ON_ERROR);
    $composer = json_decode($composerJson, true, 512, JSON_THROW_ON_ERROR);
    if (! \is_array($lock) || ! \is_array($composer) || ! \is_string($composer['name'] ?? null)) {
        fwrite(STDERR, "unexpected composer.json or composer.lock structure\n");

        return 1;
    }

    $components = [];
    $counts = ['required' => 0, 'excluded' => 0];
    foreach (['packages' => 'required', 'packages-dev' => 'excluded'] as $section => $scope) {
        foreach ((array) ($lock[$section] ?? []) as $package) {
            $component = \is_array($package) ? component($package, $scope) : null;
            if (null !== $component) {
                $components[] = $component;
                ++$counts[$scope];
            }
        }
    }

    $name = $composer['name'];
    $bom = [
        'bomFormat' => 'CycloneDX',
        'specVersion' => '1.5',
        'serialNumber' => 'urn:uuid:' . uuid(),
        'version' => 1,
        'metadata' => [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'tools' => ['components' => [['type' => 'application', 'name' => 'tools/sbom.php']]],
            'component' => [
                'type' => 'library',
                'bom-ref' => "pkg:composer/{$name}",
                'name' => $name,
                'purl' => "pkg:composer/{$name}",
                'licenses' => [['license' => ['id' => 'MIT']]],
            ],
        ],
        'components' => $components,
    ];

    if (! is_dir($root . '/build') && ! mkdir($root . '/build', 0o775, true)) {
        fwrite(STDERR, "cannot create build/\n");

        return 1;
    }
    file_put_contents($root . '/build/sbom.cdx.json', json_encode($bom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    printf("sbom: CycloneDX 1.5, %d runtime and %d development components -> build/sbom.cdx.json\n", $counts['required'], $counts['excluded']);

    return 0;
}

exit(main(\dirname(__DIR__)));
