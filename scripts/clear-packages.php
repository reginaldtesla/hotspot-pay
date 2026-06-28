<?php

declare(strict_types=1);

require __DIR__.'/../lib/bootstrap.php';

$db = hp_db();
$db->exec('DELETE FROM voucher_codes');
$db->exec('UPDATE packages SET is_active = 0');
hp_seed_packages($db);

$active = (int) $db->query('SELECT COUNT(*) FROM packages WHERE is_active = 1')->fetchColumn();
$vouchers = (int) $db->query('SELECT COUNT(*) FROM voucher_codes')->fetchColumn();

echo "Active packages: {$active}\n";
echo "Voucher rows: {$vouchers}\n";
