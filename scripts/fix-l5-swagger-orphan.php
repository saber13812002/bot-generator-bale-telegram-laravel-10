#!/usr/bin/env php
<?php

/**
 * بازیابی سایت وقتی vendor/darkaonline حذف شده ولی installed.json هنوز l5-swagger دارد.
 * اجرا: cd ~/bots && php scripts/fix-l5-swagger-orphan.php
 */

$base = dirname(__DIR__);
$package = 'darkaonline/l5-swagger';

function writeln(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function removeFromInstalledJson(string $path, string $package): bool
{
    if (!is_file($path)) {
        return false;
    }

    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        throw new RuntimeException("Invalid JSON: {$path}");
    }

    $key = array_key_exists('packages', $data) ? 'packages' : null;
    if ($key === null) {
        return false;
    }

    $before = count($data[$key]);
    $data[$key] = array_values(array_filter(
        $data[$key],
        static fn (array $item): bool => ($item['name'] ?? '') !== $package
    ));

    if ($before === count($data[$key])) {
        return false;
    }

    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );

    return true;
}

function removeFromInstalledPhp(string $path, string $package): bool
{
    if (!is_file($path)) {
        return false;
    }

    /** @var array{versions?: array<string, mixed>} $data */
    $data = require $path;

    if (!isset($data['versions'][$package])) {
        return false;
    }

    unset($data['versions'][$package]);

    $export = var_export($data, true);
    file_put_contents($path, "<?php return {$export};\n");

    return true;
}

writeln('=== fix-l5-swagger-orphan ===');

$jsonPath = $base . '/vendor/composer/installed.json';
$phpPath = $base . '/vendor/composer/installed.php';

$jsonFixed = removeFromInstalledJson($jsonPath, $package);
$phpFixed = removeFromInstalledPhp($phpPath, $package);

writeln($jsonFixed ? '✓ removed from installed.json' : '- not found in installed.json (already clean?)');
writeln($phpFixed ? '✓ removed from installed.php' : '- not found in installed.php');

$vendorDir = $base . '/vendor/darkaonline';
if (is_dir($vendorDir)) {
    writeln('- vendor/darkaonline still exists (left as-is)');
} else {
    writeln('✓ vendor/darkaonline absent (expected)');
}

$configPath = $base . '/config/l5-swagger.php';
if (is_file($configPath)) {
    unlink($configPath);
    writeln('✓ removed config/l5-swagger.php');
}

foreach ([
    'bootstrap/cache/packages.php',
    'bootstrap/cache/services.php',
    'bootstrap/cache/config.php',
] as $relative) {
    $full = $base . '/' . $relative;
    if (is_file($full)) {
        unlink($full);
        writeln("✓ removed {$relative}");
    }
}

writeln('');
writeln('Next steps:');
writeln('  php artisan package:discover --ansi');
writeln('  php artisan config:clear && php artisan route:clear && php artisan view:clear');
writeln('  php artisan config:cache && php artisan route:cache');
writeln('');
writeln('To find composer on this server:');
writeln('  which composer || type -a composer || ls -la composer.phar');
