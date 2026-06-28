<?php

declare(strict_types=1);

function hp_admin_theme_head(): void
{
    ?>
    <script>
        document.documentElement.setAttribute(
            'data-theme',
            localStorage.getItem('hp-admin-theme') === 'light' ? 'light' : 'dark'
        );
    </script>
    <?php
}

function hp_admin_theme_toggle_btn(): void
{
    ?>
    <button type="button" class="admin-icon-btn admin-theme-btn" data-theme-toggle aria-label="Toggle light/dark mode">
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
        </svg>
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
    </button>
    <?php
}

/**
 * Shared admin dashboard shell.
 *
 * @param 'overview'|'import'|'sales' $activeNav
 */
function hp_admin_layout_start(string $pageTitle, string $activeNav = 'overview'): void
{
    $appName = (string) hp_setting('app_name', 'TesNet Pay');
    ?>
    <!DOCTYPE html>
    <html lang="en" data-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= hp_escape($pageTitle) ?> — <?= hp_escape($appName) ?></title>
        <?php hp_admin_theme_head(); ?>
        <link rel="stylesheet" href="<?= hp_escape(hp_asset_url('admin.css')) ?>">
    </head>
    <body class="admin-app">
        <div class="admin-overlay" aria-hidden="true"></div>

        <aside class="admin-sidebar" id="admin-sidebar">
            <a class="admin-brand" href="index.php">
                <img src="<?= hp_escape(hp_asset_url('tesnet-logo.png')) ?>" alt="Tesnet Solutions" class="admin-brand-logo">
            </a>

            <a class="admin-btn-primary" href="import.php">+ Import vouchers</a>

            <nav class="admin-nav" aria-label="Admin">
                <a class="admin-nav-link<?= $activeNav === 'overview' ? ' is-active' : '' ?>" href="index.php">
                    <span class="admin-nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    </span>
                    Overview
                </a>
                <a class="admin-nav-link<?= $activeNav === 'import' ? ' is-active' : '' ?>" href="import.php">
                    <span class="admin-nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    </span>
                    Import CSV
                </a>
                <a class="admin-nav-link<?= $activeNav === 'sales' ? ' is-active' : '' ?>" href="sold.php">
                    <span class="admin-nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 14l2 2 4-4"/></svg>
                    </span>
                    Sales history
                </a>
            </nav>

            <div class="admin-sidebar-foot">
                <a class="admin-nav-link" href="index.php?logout=1">
                    <span class="admin-nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </span>
                    Logout
                </a>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="admin-topbar-left">
                    <button type="button" class="admin-icon-btn admin-menu-btn" data-menu-toggle aria-label="Open menu" aria-controls="admin-sidebar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <div class="admin-topbar-title">
                        <h1><?= hp_escape($pageTitle) ?></h1>
                        <span class="admin-badge">Live</span>
                    </div>
                </div>
                <div class="admin-topbar-actions">
                    <?php hp_admin_theme_toggle_btn(); ?>
                </div>
            </header>
            <div class="admin-content">
    <?php
}

function hp_admin_layout_end(): void
{
    ?>
            </div>
        </div>
        <script src="<?= hp_escape(hp_asset_url('admin-theme.js')) ?>"></script>
    </body>
    </html>
    <?php
}

function hp_admin_tx_status_badge(string $status): string
{
    return match ($status) {
        'paid' => 'success',
        'paid_no_stock' => 'warn',
        'pending' => 'pending',
        default => 'pending',
    };
}

function hp_admin_tx_status_label(string $status): string
{
    return match ($status) {
        'paid' => 'Success',
        'paid_no_stock' => 'No stock',
        'pending' => 'Pending',
        default => ucfirst($status),
    };
}

function hp_admin_format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }

    return date('H:i:s · M j, Y', $ts);
}

/**
 * Buyer phone for admin tables — never shows generic checkout@tesnet.xyz.
 *
 * @param array{buyer_phone?: ?string, buyer_email?: ?string} $row
 */
function hp_admin_buyer_phone(array $row): string
{
    $phone = hp_format_ghana_phone_display(trim((string) ($row['buyer_phone'] ?? '')));
    if ($phone !== '') {
        return $phone;
    }

    $email = trim((string) ($row['buyer_email'] ?? ''));
    if ($email === '' || $email === 'checkout@tesnet.xyz' || str_ends_with($email, '@tesnet.xyz')) {
        return '';
    }

    if (preg_match('/^(\d{10,15})@/', $email, $m)) {
        return hp_format_ghana_phone_display($m[1]);
    }

    return $email;
}
