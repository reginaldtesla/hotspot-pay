<?php

declare(strict_types=1);

require_once __DIR__.'/auth.php';
require_once __DIR__.'/layout.php';

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

hp_admin_layout_start('Import vouchers', 'import');
?>

<div class="admin-panel admin-form-panel">
    <div class="admin-panel-head">
        <div>
            <h2>Upload voucher CSV</h2>
            <p>Codes must already exist on MikroTik</p>
        </div>
    </div>

    <div style="padding: 1.25rem;">
        <?php if ($message): ?>
            <div class="admin-alert admin-alert-success"><?= hp_escape($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="admin-alert admin-alert-error"><?= hp_escape($error) ?></div>
        <?php endif; ?>

        <p class="admin-muted-text">
            CSV columns: <strong>code,profile</strong> or <strong>code,package_slug</strong>
        </p>
        <pre class="admin-pre">code,profile
TNPMZBY84G4H,Quick_Surf_1GB</pre>

        <form method="post" enctype="multipart/form-data">
            <div class="admin-field">
                <label for="csv">CSV file</label>
                <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
            </div>
            <button class="admin-btn-primary" type="submit" style="width:auto;display:inline-flex;">Upload CSV</button>
        </form>
    </div>
</div>

<?php
hp_admin_layout_end();
