<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/bootstrap.php';
require_once dirname(__DIR__).'/lib/paystack.php';

$reference = trim((string) ($_GET['ref'] ?? ''));
$accessToken = trim((string) ($_GET['tok'] ?? ''));
$appUrl = rtrim((string) hp_setting('app_url', ''), '/');
$appName = (string) hp_setting('app_name', 'TesNet Pay');

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

$ready = $accessOk && $payment && $payment['status'] === 'paid' && ! empty($payment['code']);
$noStock = $accessOk && $payment && $payment['status'] === 'paid_no_stock';
$invalidLink = $reference === '' || $accessToken === '' || ! $accessOk;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= hp_escape($appName) ?> — Payment</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="card">
        <h1><?= hp_escape($appName) ?></h1>

        <?php if ($invalidLink): ?>
            <p class="muted">This payment link is invalid or expired. Return to the Wi‑Fi login page and start again.</p>
        <?php elseif ($noStock): ?>
            <p class="error">Payment received but we are out of codes for this package. Please contact support with reference <strong><?= hp_escape($reference) ?></strong>.</p>
        <?php elseif ($ready): ?>
            <p class="success">Payment received</p>
            <?php if ($package): ?>
                <p><strong><?= hp_escape($package['name']) ?></strong> — <?= hp_escape($package['data_label']) ?></p>
            <?php endif; ?>
            <div class="code-box">
                <div class="label">Your login code</div>
                <div class="code" id="voucher-code"><?= hp_escape((string) $payment['code']) ?></div>
            </div>
            <p class="notice">After payment, your voucher code appears on screen. <strong>Copy it or screenshot it before closing the page.</strong> Use that code on the Wi‑Fi login page (same for username). We don’t send codes by SMS yet.</p>
            <button type="button" class="btn" onclick="copyCode()">Copy code</button>
        <?php else: ?>
            <p id="status-text">Confirming your payment…</p>
            <div class="spinner" aria-hidden="true"></div>
            <p class="notice">Keep this page open. Your code will appear here in a few seconds. Copy it or screenshot it before closing — we don’t send codes by SMS yet.</p>
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
                            statusText.textContent = 'This payment link is invalid. Start again from the Wi‑Fi login page.';
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
                        statusText.textContent = 'Still waiting. Refresh this page in a moment.';
                    }
                }
                poll();
            </script>
        <?php endif; ?>
    </main>
    <script>
        function copyCode() {
            const el = document.getElementById('voucher-code');
            if (!el) return;
            navigator.clipboard.writeText(el.textContent.trim()).then(() => alert('Code copied!'));
        }
    </script>
</body>
</html>
