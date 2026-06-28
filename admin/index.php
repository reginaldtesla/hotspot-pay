<?php

declare(strict_types=1);

require_once __DIR__.'/auth.php';
require_once __DIR__.'/layout.php';

if (isset($_GET['logout'])) {
    hp_admin_logout();
    hp_redirect('index.php');
}

hp_admin_require_login();

$db = hp_db();
$summary = hp_stock_summary($db);
$today = hp_sales_today($db);
$totalAvailable = hp_total_available_stock($db);
$recent = hp_recent_transactions($db, 12);

$lowStock = false;
foreach ($summary as $row) {
    if ((int) $row['available'] < 30) {
        $lowStock = true;
        break;
    }
}

hp_admin_layout_start('Overview', 'overview');
?>

<div class="admin-stats">
    <div class="admin-stat-card">
        <div class="admin-stat-label">Today's sales</div>
        <div class="admin-stat-value"><?= hp_escape(hp_format_ghs($today['total_pesewas'])) ?></div>
        <div class="admin-stat-meta"><?= (int) $today['count'] ?> payment<?= $today['count'] === 1 ? '' : 's' ?> today</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Available vouchers</div>
        <div class="admin-stat-value"><?= number_format($totalAvailable) ?></div>
        <div class="admin-stat-meta<?= $lowStock ? ' is-warn' : ' is-ok' ?>">
            <?= $lowStock ? 'Low stock on one or more packages' : 'Pool healthy' ?>
        </div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">Packages</div>
        <div class="admin-stat-value"><?= count($summary) ?></div>
        <div class="admin-stat-meta">Active in catalog</div>
    </div>
</div>

<div class="admin-grid">
    <section class="admin-panel">
        <div class="admin-panel-head">
            <div>
                <h2>Recent sales</h2>
                <p>Latest Paystack payments and voucher assignments</p>
            </div>
            <div class="admin-panel-actions">
                <a class="admin-btn-ghost" href="sold.php">View all</a>
            </div>
        </div>

        <?php if ($recent === []): ?>
            <p class="admin-empty">No sales yet. Vouchers appear here after Paystack payment.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                        <th>Reference</th>
                        <th>Package</th>
                        <th>Code</th>
                        <th>MoMo number</th>
                        <th>Amount</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $tx): ?>
                            <?php
                            $badge = hp_admin_tx_status_badge((string) $tx['status']);
                            $statusLabel = hp_admin_tx_status_label((string) $tx['status']);
                            $buyerPhone = hp_admin_buyer_phone($tx);
                            ?>
                            <tr>
                                <td class="admin-ref"><?= hp_escape((string) $tx['reference']) ?></td>
                                <td>
                                    <div class="admin-pkg-name"><?= hp_escape((string) $tx['package_name']) ?></div>
                                    <div class="admin-pkg-meta"><?= hp_escape((string) $tx['data_label']) ?></div>
                                </td>
                                <td class="admin-code"><?= hp_escape((string) ($tx['code'] ?: '—')) ?></td>
                                <td class="admin-buyer"><?php if ($buyerPhone !== ''): ?><?= hp_escape($buyerPhone) ?><?php else: ?><span class="admin-pkg-meta">—</span><?php endif; ?></td>
                                <td><?= hp_escape(hp_format_ghs((int) $tx['amount_pesewas'])) ?></td>
                                <td><span class="admin-badge-pill <?= hp_escape($badge) ?>"><?= hp_escape($statusLabel) ?></span></td>
                                <td><?= hp_escape(hp_admin_format_datetime((string) ($tx['paid_at'] ?: $tx['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <aside class="admin-panel">
        <div class="admin-panel-head">
            <div>
                <h2>Stock by package</h2>
                <p>Available codes in the pool</p>
            </div>
        </div>

        <ul class="admin-inventory-list">
            <?php foreach ($summary as $row): ?>
                <?php
                $available = (int) $row['available'];
                $assigned = (int) $row['assigned'];
                $total = max($available + $assigned, 1);
                $pct = (int) round(($available / $total) * 100);
                $health = hp_stock_health($available);
                $healthLabel = hp_stock_health_label($health);
                ?>
                <li class="admin-inventory-item">
                    <div class="admin-inventory-top">
                        <span class="admin-inventory-name"><?= hp_escape($row['name']) ?> · <?= hp_escape($row['data_label']) ?></span>
                        <span class="admin-inventory-count <?= hp_escape($health) ?>"><?= hp_escape($healthLabel) ?> (<?= $available ?>)</span>
                    </div>
                    <div class="admin-progress" title="<?= $available ?> available, <?= $assigned ?> sold">
                        <div class="admin-progress-bar <?= hp_escape($health) ?>" style="width: <?= $pct ?>%"></div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="admin-panel-foot">
            <a class="admin-btn-primary" href="import.php">Refill stock</a>
        </div>
    </aside>
</div>

<?php
hp_admin_layout_end();
