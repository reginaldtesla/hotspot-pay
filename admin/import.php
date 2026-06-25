<?php

declare(strict_types=1);

require_once __DIR__.'/auth.php';

hp_admin_require_login();

$db = hp_db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Choose a CSV file.';
    } else {
        $tmp = $_FILES['csv']['tmp_name'];
        try {
            $result = hp_import_csv($db, $tmp);
            $message = sprintf(
                'Imported %d codes. Skipped %d duplicates. Invalid rows: %d.',
                $result['imported'],
                $result['skipped'],
                $result['invalid']
            );
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$appName = (string) hp_setting('app_name', 'TesNet Pay');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= hp_escape($appName) ?> — Import</title>
    <link rel="stylesheet" href="../public/assets/style.css">
    <style>
        .links { margin-top: 1rem; }
        .links a { color: #93c5fd; }
        input[type=file] { width: 100%; margin: 1rem 0; color: #cbd5e1; }
        pre { background: #0f172a; padding: 0.75rem; border-radius: 8px; font-size: 0.85rem; overflow-x: auto; }
    </style>
</head>
<body>
    <main class="card" style="max-width: 640px;">
        <h1>Import voucher codes</h1>
        <p class="muted">CSV columns: <code>code,profile</code> or <code>code,package_slug</code></p>
        <pre>code,profile
TNPMZBY84G4H,Quick_Surf_1GB</pre>

        <?php if ($message): ?>
            <p class="success"><?= hp_escape($message) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error"><?= hp_escape($error) ?></p>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <input type="file" name="csv" accept=".csv,text/csv" required>
            <button class="btn" type="submit">Upload CSV</button>
        </form>
        <p class="links"><a href="index.php">← Back to stock</a> · <a href="sold.php">Sold codes</a></p>
    </main>
</body>
</html>
