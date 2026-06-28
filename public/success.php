<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/bootstrap.php';
require_once dirname(__DIR__).'/lib/paystack.php';

$reference = trim((string) ($_GET['ref'] ?? ''));
$accessToken = trim((string) ($_GET['tok'] ?? ''));
$appName = (string) hp_setting('app_name', 'TesNet');
$hotspotLogin = rtrim((string) hp_setting('hotspot_login_url', 'http://192.168.88.1'), '/');

$payment = null;
$package = null;
$poll = isset($_GET['poll']);
$accessOk = false;

if ($reference !== '' && $accessToken !== '') {
    $db = hp_db();
    $payment = hp_get_payment_by_reference($db, $reference, $accessToken);
    $accessOk = $payment !== null;
    if ($payment) {
        $package = hp_get_package($db, (string) $payment['package_slug']);
    }
}

if ($poll) {
    if (! $accessOk) {
        hp_json_response(['ready' => false, 'status' => 'forbidden'], 403);
    }

    $ready = $payment && $payment['status'] === 'paid' && ! empty($payment['code']);
    hp_json_response([
        'ready' => $ready,
        'status' => $payment['status'] ?? 'unknown',
        'code' => $payment['code'] ?? null,
    ]);
}

$previewMode = isset($_GET['preview']) && (bool) hp_setting('dev_success_preview', false);

if ($previewMode) {
    $state = (string) ($_GET['state'] ?? 'ready');
    $reference = 'HP-PREVIEW-DEMO';
    $accessOk = true;
    $ready = false;
    $noStock = false;
    $invalidLink = false;
    $pending = false;
    $payment = null;
    $package = null;

    switch ($state) {
        case 'invalid':
            $invalidLink = true;
            $accessOk = false;
            break;
        case 'pending':
            $pending = true;
            $payment = ['amount_pesewas' => 900, 'package_slug' => 'student-choice', 'status' => 'pending'];
            $package = hp_get_package(hp_db(), 'student-choice');
            break;
        case 'nostock':
            $noStock = true;
            break;
        case 'ready':
        default:
            $ready = true;
            $payment = ['amount_pesewas' => 900, 'code' => 'TN4K8H2M9P1'];
            $package = hp_get_package(hp_db(), 'student-choice') ?: [
                'name' => 'Student Choice',
                'data_label' => '3GB',
            ];
            break;
    }
} else {
    $ready = $accessOk && $payment && $payment['status'] === 'paid' && ! empty($payment['code']);
    $noStock = $accessOk && $payment && $payment['status'] === 'paid_no_stock';
    $invalidLink = $reference === '' || $accessToken === '' || ! $accessOk;
    $pending = $accessOk && ! $ready && ! $noStock;
}

$voucherCode = $ready ? (string) $payment['code'] : '';
$loginUrl = $ready
    ? $hotspotLogin.(str_contains($hotspotLogin, '?') ? '&' : '?').'code='.rawurlencode($voucherCode)
    : $hotspotLogin;

$packageLine = '';
if ($package && $payment) {
    $packageLine = hp_format_ghs((int) $payment['amount_pesewas'])
        .' — '.(string) $package['name']
        .' — '.(string) $package['data_label'];
}

$pageTitle = $ready ? 'Payment successful' : ($pending ? 'Confirming payment' : 'Payment');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function () {
            var t = localStorage.getItem('hp-landing-theme');
            document.documentElement.classList.add(t === 'light' ? 'light' : 'dark');
        })();
    </script>
    <title><?= hp_escape($appName) ?> — <?= hp_escape($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= hp_escape(hp_asset_url('success.css')) ?>">
</head>
<body class="success-page">
    <div class="success-shell">
        <?php if ($previewMode): ?>
            <nav class="success-preview-bar" aria-label="Preview states">
                <span>Local preview</span>
                <a href="?preview=1&amp;state=ready"<?= ($ready ? ' class="is-active"' : '') ?>>Success</a>
                <a href="?preview=1&amp;state=pending"<?= ($pending ? ' class="is-active"' : '') ?>>Waiting</a>
                <a href="?preview=1&amp;state=nostock"<?= ($noStock ? ' class="is-active"' : '') ?>>No stock</a>
                <a href="?preview=1&amp;state=invalid"<?= ($invalidLink ? ' class="is-active"' : '') ?>>Invalid link</a>
            </nav>
        <?php endif; ?>
        <header class="success-header">
            <img src="<?= hp_escape(hp_asset_url('tesnet-logo.png')) ?>" alt="" class="success-logo" width="40" height="40">
            <span class="success-brand"><?= hp_escape($appName) ?></span>
        </header>

        <main class="success-card">
            <?php if ($invalidLink): ?>
                <div class="success-hero">
                    <div class="success-icon success-icon--pending" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    </div>
                    <h1 class="success-title">Link not valid</h1>
                    <p class="success-subtitle">This payment link is invalid or expired.</p>
                </div>
                <p class="success-subtitle" style="text-align:center;margin:0;">Return to the TesNet Wi‑Fi login page and start again.</p>

            <?php elseif ($noStock): ?>
                <div class="success-hero">
                    <div class="success-icon success-icon--pending" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4M12 17h.01"/></svg>
                    </div>
                    <h1 class="success-title">Payment received</h1>
                    <p class="success-subtitle">We are temporarily out of codes for this package.</p>
                </div>
                <div class="success-alert success-alert--error">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    <p>Please contact support with your payment reference: <strong><?= hp_escape($reference) ?></strong></p>
                </div>

            <?php elseif ($ready): ?>
                <div class="success-hero">
                    <div class="success-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
                    <h1 class="success-title">Payment successful!</h1>
                    <p class="success-subtitle">Your internet package is ready to use.</p>
                </div>

                <div class="success-alert success-alert--screenshot" role="alert">
                    <div class="screenshot-alert-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3Z"/><circle cx="12" cy="13" r="3"/></svg>
                    </div>
                    <div class="screenshot-alert-content">
                        <p class="screenshot-alert-kicker">Important — read this</p>
                        <p class="screenshot-alert-title">Screenshot this page now</p>
                        <p class="screenshot-alert-text">Your voucher code will not be shown again. Copy it or take a screenshot before you close this page.</p>
                    </div>
                </div>

                <?php if ($packageLine !== ''): ?>
                    <p class="success-package"><?= hp_escape($packageLine) ?></p>
                <?php endif; ?>

                <div class="success-credentials">
                    <div class="cred-row">
                        <div>
                            <div class="cred-label">Voucher code</div>
                            <div class="cred-value" id="voucher-code"><?= hp_escape($voucherCode) ?></div>
                        </div>
                        <button type="button" class="cred-copy" data-copy-target="voucher-code">Copy</button>
                    </div>
                </div>
                <p class="cred-note">Use this code on the Wi‑Fi login page — enter it as your voucher (same value for username and password).</p>

                <a class="success-btn" href="<?= hp_escape($loginUrl) ?>">
                    Go to login page now
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>

                <div class="success-meta">
                    <p>Reference: <code><?= hp_escape($reference) ?></code></p>
                    <?php if ($payment): ?>
                        <p>Amount paid: <strong><?= hp_escape(hp_format_ghs((int) $payment['amount_pesewas'])) ?></strong></p>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="success-hero">
                    <div class="success-spinner" aria-hidden="true"></div>
                    <h1 class="success-title" id="status-text">Confirming your payment…</h1>
                    <p class="success-subtitle">Keep this page open. Your voucher code will appear in a few seconds.</p>
                </div>

                <div class="success-alert success-alert--warn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4M12 17h.01"/></svg>
                    <p>Do not close this tab until your code appears. Screenshot it before leaving — we do not send codes by SMS.</p>
                </div>

                <?php if (! $previewMode): ?>
                <script>
                    const ref = <?= json_encode($reference) ?>;
                    const tok = <?= json_encode($accessToken) ?>;
                    let attempts = 0;
                    const statusText = document.getElementById('status-text');

                    async function poll() {
                        attempts++;
                        try {
                            const res = await fetch('success.php?poll=1&ref=' + encodeURIComponent(ref) + '&tok=' + encodeURIComponent(tok));
                            if (res.status === 403) {
                                statusText.textContent = 'This payment link is invalid.';
                                return;
                            }
                            const data = await res.json();
                            if (data.ready && data.code) {
                                window.location.reload();
                                return;
                            }
                            if (data.status === 'paid_no_stock') {
                                window.location.reload();
                                return;
                            }
                        } catch (e) {}
                        if (attempts < 40) {
                            setTimeout(poll, 2000);
                        } else {
                            statusText.textContent = 'Still waiting — refresh this page.';
                        }
                    }
                    poll();
                </script>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <script>
        document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-copy-target');
                var el = document.getElementById(id);
                if (!el) return;
                var text = el.textContent.trim();
                function onCopied() {
                    btn.textContent = 'Copied';
                    btn.classList.add('is-copied');
                    setTimeout(function () {
                        btn.textContent = 'Copy';
                        btn.classList.remove('is-copied');
                    }, 2000);
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(onCopied).catch(function () {
                        window.prompt('Copy this code:', text);
                    });
                } else {
                    window.prompt('Copy this code:', text);
                }
            });
        });
    </script>
</body>
</html>
