<?php

declare(strict_types=1);

require_once __DIR__.'/pool.php';

function hp_root(): string
{
    return dirname(__DIR__);
}

function hp_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $config = require hp_root().'/config.php';
    $local = hp_root().'/config.local.php';

    if (is_file($local)) {
        $config = array_replace_recursive($config, require $local);
    }

    return $config;
}

function hp_setting(string $key, mixed $default = null): mixed
{
    return hp_config()[$key] ?? $default;
}

function hp_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = hp_root().'/storage';
    if (! is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $path = $dir.'/pool.sqlite';
    $pdo = new PDO('sqlite:'.$path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $schema = file_get_contents($dir.'/schema.sql');
    $pdo->exec($schema);

    hp_migrate($pdo);
    hp_seed_packages($pdo);

    return $pdo;
}

function hp_migrate(PDO $db): void
{
    $columns = $db->query('PRAGMA table_info(payments)')->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($columns, 'name');

    if (! in_array('access_token', $names, true)) {
        $db->exec("ALTER TABLE payments ADD COLUMN access_token TEXT NOT NULL DEFAULT ''");
    }
}

function hp_json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function hp_redirect(string $url): never
{
    header('Location: '.$url);
    exit;
}

function hp_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hp_format_ghana_phone_display(?string $phone): string
{
    if ($phone === null || $phone === '') {
        return '';
    }

    if (str_contains($phone, '***')) {
        if (preg_match('/^(\d{3})\*{3}(\d{3,4})$/', $phone, $m)) {
            return '0'.$m[1].' ••• '.$m[2];
        }

        return $phone;
    }

    $digits = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($digits, '233') && strlen($digits) >= 12) {
        $local = '0'.substr($digits, 3);

        return substr($local, 0, 3).' '.substr($local, 3, 3).' '.substr($local, 6);
    }

    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return substr($digits, 0, 3).' '.substr($digits, 3, 3).' '.substr($digits, 6);
    }

    return $phone;
}

/** URL for static files in public/assets/ (vhost docroot = public/). */
function hp_asset_url(string $path = 'success.css'): string
{
    return '/assets/'.ltrim($path, '/');
}

function hp_reference(): string
{
    return 'HP-'.bin2hex(random_bytes(8));
}
