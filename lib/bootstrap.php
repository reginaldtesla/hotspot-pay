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

function hp_reference(): string
{
    return 'HP-'.bin2hex(random_bytes(8));
}
