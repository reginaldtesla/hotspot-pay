<?php

declare(strict_types=1);

require_once __DIR__.'/auth.php';

if (isset($_GET['logout'])) {
    hp_admin_logout();
    hp_redirect('index.php');
}

hp_admin_require_login();

$db = hp_db();
$summary = hp_stock_summary($db);
$appName = (string) hp_setting('app_name', 'TesNet Pay');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= hp_escape($appName) ?> Admin</title>
    <link rel="stylesheet" href="../public/assets/style.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.9rem; }
        th, td { padding: 0.55rem 0.35rem; border-bottom: 1px solid #334155; text-align: left; }
        .links { margin-top: 1.25rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .links a { color: #93c5fd; }
    </style>
</head>
<body>
    <main class="card" style="max-width: 640px;">
        <h1><?= hp_escape($appName) ?> — Stock</h1>
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Available</th>
                    <th>Sold</th>
                    <th>Revoked</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($summary as $row): ?>
                    <tr>
                        <td><?= hp_escape($row['name'].' ('.$row['data_label'].')') ?></td>
                        <td><?= (int) $row['available'] ?></td>
                        <td><?= (int) $row['assigned'] ?></td>
                        <td><?= (int) $row['revoked'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="links">
            <a href="sold.php">Sold codes</a>
            <a href="import.php">Import CSV</a>
            <a href="index.php?logout=1">Logout</a>
        </div>
    </main>
</body>
</html>
