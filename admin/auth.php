<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/bootstrap.php';

session_start();

function hp_admin_logged_in(): bool
{
    return ! empty($_SESSION['hp_admin']);
}

function hp_admin_rate_limited(): bool
{
    $until = (int) ($_SESSION['hp_admin_locked_until'] ?? 0);

    return $until > time();
}

function hp_admin_register_failed_attempt(): void
{
    $attempts = (int) ($_SESSION['hp_admin_attempts'] ?? 0) + 1;
    $_SESSION['hp_admin_attempts'] = $attempts;

    if ($attempts >= 5) {
        $_SESSION['hp_admin_locked_until'] = time() + 900;
    }
}

function hp_admin_clear_attempts(): void
{
    unset($_SESSION['hp_admin_attempts'], $_SESSION['hp_admin_locked_until']);
}

function hp_admin_require_login(): void
{
    if (hp_admin_logged_in()) {
        return;
    }

    $password = (string) hp_setting('admin_password', '');
    if ($password === '' || $password === 'change-me-to-a-strong-password') {
        http_response_code(503);
        echo 'Admin password not configured. Copy config.local.php.example to config.local.php';
        exit;
    }

    if (hp_admin_rate_limited()) {
        http_response_code(429);
        echo 'Too many failed attempts. Try again in 15 minutes.';
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $attempt = (string) ($_POST['password'] ?? '');
        if (hash_equals($password, $attempt)) {
            hp_admin_clear_attempts();
            $_SESSION['hp_admin'] = true;
            hp_redirect($_SERVER['REQUEST_URI'] ?? 'index.php');
        }
        hp_admin_register_failed_attempt();
        $error = 'Wrong password.';
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>TesNet Pay Admin</title>
        <link rel="stylesheet" href="../public/assets/style.css">
    </head>
    <body>
        <main class="card">
            <h1>Admin login</h1>
            <?php if (! empty($error)): ?>
                <p class="error"><?= hp_escape($error) ?></p>
            <?php endif; ?>
            <form method="post">
                <p><input type="password" name="password" placeholder="Password" required style="width:100%;padding:0.75rem;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#fff;"></p>
                <button class="btn" type="submit">Login</button>
            </form>
        </main>
    </body>
    </html>
    <?php
    exit;
}

function hp_admin_logout(): void
{
    unset($_SESSION['hp_admin']);
}
