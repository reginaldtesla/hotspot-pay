#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Convert MikroTik /ip hotspot user export (.rsc) to hotspot-pay import CSV.
 *
 * Usage:
 *   php scripts/rsc-to-csv.php /path/to/export.rsc
 *   php scripts/rsc-to-csv.php /path/to/export.rsc data/new-vouchers.csv
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/rsc-to-csv.php <export.rsc> [output.csv]\n");
    exit(1);
}

$src = $argv[1];
$out = $argv[2] ?? preg_replace('/\.rsc$/i', '.csv', $src);

if (! is_file($src)) {
    fwrite(STDERR, "Error: file not found: {$src}\n");
    exit(1);
}

$text = file_get_contents($src);
$text = preg_replace('/\\\s*\n\s*/', ' ', $text) ?? $text;

$rows = [];
$seen = [];

foreach (explode("\n", $text) as $line) {
    $line = trim($line);
    if (! str_starts_with($line, 'add ')) {
        continue;
    }
    if (! str_contains($line, 'limit-bytes-total') && ! str_contains($line, 'limit-uptime')) {
        continue;
    }
    if (! str_contains($line, 'profile=')) {
        continue;
    }

    if (! preg_match('/name=\s*([A-Z0-9]+)/', $line, $nameMatch)) {
        continue;
    }
    if (! preg_match('/profile=([^\s]+)/', $line, $profileMatch)) {
        continue;
    }

    $code = strtoupper($nameMatch[1]);
    $profile = $profileMatch[1];
    if (isset($seen[$code])) {
        continue;
    }
    $seen[$code] = true;
    $rows[] = [$code, $profile];
}

if ($rows === []) {
    fwrite(STDERR, "Error: no hotspot users found in file.\n");
    exit(1);
}

usort($rows, static fn ($a, $b) => $a[1] <=> $b[1] ?: $a[0] <=> $b[0]);

$lines = ['code,profile'];
foreach ($rows as [$code, $profile]) {
    $lines[] = "{$code},{$profile}";
}

file_put_contents($out, implode("\n", $lines)."\n");

$counts = [];
foreach ($rows as [, $profile]) {
    $counts[$profile] = ($counts[$profile] ?? 0) + 1;
}

echo 'Wrote '.count($rows)." codes to {$out}\n";
ksort($counts);
foreach ($counts as $profile => $count) {
    echo "  {$profile}: {$count}\n";
}
