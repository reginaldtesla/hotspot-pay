<?php

declare(strict_types=1);

require_once __DIR__.'/auth.php';

hp_admin_require_login();

$db = hp_db();
$summary = hp_stock_summary($db);
$appName = (string) hp_setting('app_name', 'TesNet Pay');
$packageSlug = trim((string) ($_GET['pkg'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 100);
$sold = hp_sold_codes($db, $limit, $packageSlug !== '' ? $packageSlug : null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= hp_escape($appName) ?> — Sold codes</title>
    <link rel="stylesheet" href="../public/assets/style.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.85rem; }
        th, td { padding: 0.5rem 0.35rem; border-bottom: 1px solid #334155; text-align: left; vertical-align: top; }
        th { white-space: nowrap; }
        .code { font-family: ui-monospace, monospace; letter-spacing: 0.03em; }
        .ref { font-size: 0.8rem; color: #94a3b8; word-break: break-all; }
        .links { margin-top: 1.25rem; display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
        .links a { color: #93c5fd; }
        .filters { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; align-items: end; }
        .filters label { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.85rem; color: #94a3b8; }
        .filters select, .filters input { padding: 0.45rem 0.55rem; border-radius: 6px; border: 1px solid #475569; background: #0f172a; color: #fff; }
        .filters button { padding: 0.5rem 0.85rem; border-radius: 6px; border: none; background: #2563eb; color: #fff; cursor: pointer; }
        .muted { color: #94a3b8; font-size: 0.9rem; margin-top: 0.5rem; }
    </style>
</head>
<body>
    <main class="card" style="max-width: 960px;">
        <h1><?= hp_escape($appName) ?> — Sold codes</h1>
        <p class="muted">Codes assigned after Paystack payment. Newest first.</p>

        <form class="filters" method="get">
            <label>
                Package
                <select name="pkg">
                    <option value="">All packages</option>
                    <?php foreach ($summary as $row): ?>
                        <option value="<?= hp_escape($row['slug']) ?>" <?= $packageSlug === $row['slug'] ? 'selected' : '' ?>>
                            <?= hp_escape($row['name'].' ('.$row['data_label'].')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Show
                <select name="limit">
                    <?php foreach ([50, 100, 200, 500] as $n): ?>
                        <option value="<?= $n ?>" <?= $limit === $n ? 'selected' : '' ?>><?= $n ?> rows</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Filter</button>
        </form>

        <?php if ($sold === []): ?>
            <p class="muted" style="margin-top: 1.5rem;">No sold codes yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Package</th>
                        <th>Amount</th>
                        <th>Sold at</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sold as $row): ?>
                        <tr>
                            <td class="code"><?= hp_escape((string) $row['code']) ?></td>
                            <td><?= hp_escape($row['package_name'].' ('.$row['data_label'].')') ?></td>
                            <td><?= hp_escape(hp_format_ghs((int) ($row['amount_pesewas'] ?? 0))) ?></td>
                            <td><?= hp_escape((string) ($row['assigned_at'] ?: $row['paid_at'] ?: '—')) ?></td>
                            <td class="ref"><?= hp_escape((string) ($row['payment_reference'] ?: $row['paystack_reference'] ?: '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="links">
            <a href="index.php">← Stock</a>
            <a href="import.php">Import CSV</a>
            <a href="index.php?logout=1">Logout</a>
        </div>
    </main>
</body>
</html>
