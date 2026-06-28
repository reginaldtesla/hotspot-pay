<?php

declare(strict_types=1);

require_once __DIR__.'/auth.php';
require_once __DIR__.'/layout.php';

hp_admin_require_login();

$db = hp_db();
$summary = hp_stock_summary($db);

$packageSlug = trim((string) ($_GET['pkg'] ?? ''));
$period = trim((string) ($_GET['period'] ?? 'all'));
$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? 'completed'));
$limit = (int) ($_GET['limit'] ?? 50);
$page = max(1, (int) ($_GET['page'] ?? 1));

$allowedPeriods = ['all', 'today', '7d', '30d'];
if (! in_array($period, $allowedPeriods, true)) {
    $period = 'all';
}

$allowedStatus = ['completed', 'paid', 'paid_no_stock'];
if (! in_array($status, $allowedStatus, true)) {
    $status = 'completed';
}

$filters = [
    'package_slug' => $packageSlug !== '' ? $packageSlug : null,
    'period' => $period,
    'search' => $search,
    'status' => $status,
    'limit' => $limit,
    'offset' => ($page - 1) * $limit,
];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export = hp_sales_history($db, array_merge($filters, ['limit' => 5000, 'offset' => 0]));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tesnet-sales-'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['reference', 'status', 'code', 'package', 'data', 'amount_ghs', 'buyer_phone', 'buyer_email', 'sold_at']);
    foreach ($export['rows'] as $row) {
        fputcsv($out, [
            $row['reference'],
            $row['status'],
            $row['code'] ?? '',
            $row['package_name'],
            $row['data_label'],
            number_format((int) $row['amount_pesewas'] / 100, 2, '.', ''),
            $row['buyer_phone'] ?? '',
            $row['buyer_email'] ?? '',
            $row['paid_at'] ?: $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

$stats = hp_sales_history_stats($db, $filters);
$result = hp_sales_history($db, $filters);
$rows = $result['rows'];
$total = $result['total'];
$totalPages = max(1, (int) ceil($total / max($limit, 1)));

if ($page > $totalPages) {
    $page = $totalPages;
    $filters['offset'] = ($page - 1) * $limit;
    $result = hp_sales_history($db, $filters);
    $rows = $result['rows'];
}

$salesQuery = static function (array $extra = []) use ($packageSlug, $period, $search, $status, $limit, $page): string {
    $params = array_filter(array_merge([
        'pkg' => $packageSlug,
        'period' => $period !== 'all' ? $period : '',
        'q' => $search,
        'status' => $status !== 'completed' ? $status : '',
        'limit' => $limit !== 50 ? (string) $limit : '',
        'page' => $page > 1 ? (string) $page : '',
    ], $extra), static fn ($v) => $v !== '' && $v !== null);

    return 'sold.php?'.http_build_query($params);
};

hp_admin_layout_start('Sales history', 'sales');
?>

<div class="admin-stats admin-stats-compact">
    <div class="admin-stat-card">
        <div class="admin-stat-label">Revenue (filtered)</div>
        <div class="admin-stat-value"><?= hp_escape(hp_format_ghs($stats['revenue_pesewas'])) ?></div>
        <div class="admin-stat-meta"><?= number_format($stats['count']) ?> sale<?= $stats['count'] === 1 ? '' : 's' ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Codes delivered</div>
        <div class="admin-stat-value"><?= number_format($stats['fulfilled']) ?></div>
        <div class="admin-stat-meta is-ok">Payment + voucher assigned</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">No stock</div>
        <div class="admin-stat-value"><?= number_format($stats['no_stock']) ?></div>
        <div class="admin-stat-meta<?= $stats['no_stock'] > 0 ? ' is-warn' : '' ?>">Paid but pool empty</div>
    </div>
</div>

<section class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <h2>All sales</h2>
            <p>Paystack payments — search, filter, export</p>
        </div>
        <div class="admin-panel-actions">
            <a class="admin-btn-ghost" href="sold.php?<?= hp_escape(http_build_query(array_filter([
                'export' => 'csv',
                'pkg' => $packageSlug ?: null,
                'period' => $period !== 'all' ? $period : null,
                'q' => $search ?: null,
                'status' => $status !== 'completed' ? $status : null,
            ]))) ?>">Export CSV</a>
        </div>
    </div>

    <form class="admin-filters admin-filters-sales" method="get">
        <label class="admin-filter-search">
            Search
            <input type="search" name="q" value="<?= hp_escape($search) ?>" placeholder="Reference, code, phone, email…">
        </label>
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
            Period
            <select name="period">
                <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>All time</option>
                <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="7d" <?= $period === '7d' ? 'selected' : '' ?>>Last 7 days</option>
                <option value="30d" <?= $period === '30d' ? 'selected' : '' ?>>Last 30 days</option>
            </select>
        </label>
        <label>
            Status
            <select name="status">
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>All completed</option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Success (code sent)</option>
                <option value="paid_no_stock" <?= $status === 'paid_no_stock' ? 'selected' : '' ?>>No stock</option>
            </select>
        </label>
        <label>
            Per page
            <select name="limit">
                <?php foreach ([25, 50, 100, 200] as $n): ?>
                    <option value="<?= $n ?>" <?= $limit === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="admin-btn-primary admin-btn-filter" type="submit">Apply</button>
        <?php if ($packageSlug !== '' || $search !== '' || $period !== 'all' || $status !== 'completed'): ?>
            <a class="admin-btn-ghost" href="sold.php">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($rows === []): ?>
        <p class="admin-empty">No sales match your filters.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-sales">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Package</th>
                        <th>Code</th>
                        <th>MoMo number</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Sold at</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $badge = hp_admin_tx_status_badge((string) $row['status']);
                        $statusLabel = hp_admin_tx_status_label((string) $row['status']);
                        $code = (string) ($row['code'] ?? '');
                        $buyerPhone = hp_admin_buyer_phone($row);
                        ?>
                        <tr>
                            <td class="admin-ref"><?= hp_escape((string) $row['reference']) ?></td>
                            <td>
                                <div class="admin-pkg-name"><?= hp_escape($row['package_name']) ?></div>
                                <div class="admin-pkg-meta"><?= hp_escape($row['data_label']) ?></div>
                            </td>
                            <td>
                                <?php if ($code !== ''): ?>
                                    <span class="admin-code" id="code-<?= hp_escape(md5($code.$row['reference'])) ?>"><?= hp_escape($code) ?></span>
                                <?php else: ?>
                                    <span class="admin-pkg-meta">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="admin-buyer"><?php if ($buyerPhone !== ''): ?><?= hp_escape($buyerPhone) ?><?php else: ?><span class="admin-pkg-meta">—</span><?php endif; ?></td>
                            <td class="admin-amount"><?= hp_escape(hp_format_ghs((int) $row['amount_pesewas'])) ?></td>
                            <td><span class="admin-badge-pill <?= hp_escape($badge) ?>"><?= hp_escape($statusLabel) ?></span></td>
                            <td class="admin-date"><?= hp_escape(hp_admin_format_datetime((string) ($row['paid_at'] ?: $row['created_at']))) ?></td>
                            <td class="admin-row-actions">
                                <?php if ($code !== ''): ?>
                                    <button type="button" class="admin-copy-btn" data-copy="<?= hp_escape($code) ?>" title="Copy code">Copy</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">
            <span class="admin-pagination-info">
                Showing <?= number_format(min($total, ($page - 1) * $limit + 1)) ?>–<?= number_format(min($total, $page * $limit)) ?> of <?= number_format($total) ?>
            </span>
            <div class="admin-pagination-links">
                <?php if ($page > 1): ?>
                    <a class="admin-btn-ghost" href="<?= hp_escape($salesQuery(['page' => (string) ($page - 1)])) ?>">← Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="admin-btn-ghost" href="<?= hp_escape($salesQuery(['page' => (string) ($page + 1)])) ?>">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
document.querySelectorAll('.admin-copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var text = btn.getAttribute('data-copy') || '';
        if (!text) return;
        navigator.clipboard.writeText(text).then(function () {
            var prev = btn.textContent;
            btn.textContent = 'Copied';
            setTimeout(function () { btn.textContent = prev; }, 1500);
        });
    });
});
</script>

<?php
hp_admin_layout_end();
