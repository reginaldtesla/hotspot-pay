<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/bootstrap.php';
require_once __DIR__.'/layout.php';

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

function hp_admin_login_page(string $title, string $bodyHtml, int $status = 200): never
{
    http_response_code($status);
    $appName = (string) hp_setting('app_name', 'TesNet Pay');
    ?>
    <!DOCTYPE html>
    <html lang="en" data-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= hp_escape($title) ?> — <?= hp_escape($appName) ?></title>
        <?php hp_admin_theme_head(); ?>
        <link rel="stylesheet" href="<?= hp_escape(hp_asset_url('admin.css')) ?>">
    </head>
    <body class="login-page login-status-page">
        <div class="login-wrap">
            <main class="login-card">
                <div class="login-top-actions">
                    <?php hp_admin_theme_toggle_btn(); ?>
                </div>
                <div class="login-brand">
                    <img src="<?= hp_escape(hp_asset_url('tesnet-logo.png')) ?>" alt="Tesnet Solutions" class="login-brand-logo">
                    <p>Admin</p>
                </div>
                <?= $bodyHtml ?>
            </main>
        </div>
        <script src="<?= hp_escape(hp_asset_url('admin-theme.js')) ?>"></script>
    </body>
    </html>
    <?php
    exit;
}

function hp_admin_require_login(): void
{
    if (hp_admin_logged_in()) {
        return;
    }

    $password = (string) hp_setting('admin_password', '');
    if ($password === '' || $password === 'change-me-to-a-strong-password') {
        hp_admin_login_page(
            'Not configured',
            '<p>Admin password is not set. Copy <code>config.local.php.example</code> to <code>config.local.php</code> and choose a strong password.</p>',
            503
        );
    }

    if (hp_admin_rate_limited()) {
        hp_admin_login_page(
            'Locked',
            '<p>Too many failed login attempts. Please wait <strong>15 minutes</strong> and try again.</p>',
            429
        );
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $attempt = (string) ($_POST['password'] ?? '');
        if (hash_equals($password, $attempt)) {
            hp_admin_clear_attempts();
            $_SESSION['hp_admin'] = true;
            hp_redirect($_SERVER['REQUEST_URI'] ?? 'index.php');
        }
        hp_admin_register_failed_attempt();
        $error = 'Incorrect password. Please try again.';
    }

    $appName = (string) hp_setting('app_name', 'TesNet Pay');
    ?>
    <!DOCTYPE html>
    <html lang="en" data-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sign in — <?= hp_escape($appName) ?> Admin</title>
        <?php hp_admin_theme_head(); ?>
        <link rel="stylesheet" href="<?= hp_escape(hp_asset_url('admin.css')) ?>">
    </head>
    <body class="login-page">
        <div class="login-wrap">
            <main class="login-card">
                <div class="login-top-actions">
                    <?php hp_admin_theme_toggle_btn(); ?>
                </div>
                <div class="login-brand">
                    <img src="<?= hp_escape(hp_asset_url('tesnet-logo.png')) ?>" alt="Tesnet Solutions" class="login-brand-logo">
                    <p>Sign in to manage voucher stock</p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error" role="alert">
                        <span><?= hp_escape($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" id="login-form">
                    <div class="field">
                        <label for="password">Password</label>
                        <div class="field-input-wrap">
                            <input
                                class="field-input"
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter admin password"
                                autocomplete="current-password"
                                required
                                autofocus
                            >
                            <button type="button" class="field-toggle" id="toggle-password" aria-label="Show password">
                                Show
                            </button>
                        </div>
                    </div>
                    <button class="btn-login" type="submit">Sign in</button>
                </form>

                <p class="login-footer">Authorized staff only</p>
            </main>
        </div>
        <script>
            (function () {
                var input = document.getElementById('password');
                var toggle = document.getElementById('toggle-password');
                if (!input || !toggle) return;
                toggle.addEventListener('click', function () {
                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    toggle.textContent = show ? 'Hide' : 'Show';
                    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                    input.focus();
                });
            })();
        </script>
        <script src="<?= hp_escape(hp_asset_url('admin-theme.js')) ?>"></script>
    </body>
    </html>
    <?php
    exit;
}

function hp_admin_logout(): void
{
    unset($_SESSION['hp_admin']);
}
