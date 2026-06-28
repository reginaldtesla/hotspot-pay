<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$config = hp_config();
$packages = $config['packages'] ?? [];

usort($packages, static fn (array $a, array $b): int => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

echo "TesNet Pay is running.\n\n";
echo "Packages:\n";

foreach ($packages as $pkg) {
    $slug = (string) ($pkg['slug'] ?? '');
    $name = (string) ($pkg['name'] ?? $slug);
    $label = (string) ($pkg['data_label'] ?? '');
    $ghs = number_format(((int) ($pkg['amount_pesewas'] ?? 0)) / 100, 2);
    echo "  - {$name} ({$label}) — GH¢ {$ghs} → /buy.php?pkg={$slug}\n";
}

echo "\nAdmin: /admin/\n";
