<?php

declare(strict_types=1);

function hp_seed_packages(PDO $db): void
{
    $config = hp_config();

    $stmt = $db->prepare(
        'INSERT INTO packages (slug, name, data_label, amount_pesewas, mikrotik_profile, sort_order, is_active)
         VALUES (:slug, :name, :data_label, :amount, :profile, :sort_order, 1)
         ON CONFLICT(slug) DO UPDATE SET
            name = excluded.name,
            data_label = excluded.data_label,
            amount_pesewas = excluded.amount_pesewas,
            mikrotik_profile = excluded.mikrotik_profile,
            sort_order = excluded.sort_order'
    );

    foreach ($config['packages'] as $pkg) {
        $stmt->execute([
            'slug' => $pkg['slug'],
            'name' => $pkg['name'],
            'data_label' => $pkg['data_label'],
            'amount' => $pkg['amount_pesewas'],
            'profile' => $pkg['mikrotik_profile'],
            'sort_order' => $pkg['sort_order'],
        ]);
    }
}

function hp_get_package(PDO $db, string $slug): ?array
{
    $stmt = $db->prepare('SELECT * FROM packages WHERE slug = :slug AND is_active = 1');
    $stmt->execute(['slug' => $slug]);

    $row = $stmt->fetch();

    return $row ?: null;
}

function hp_stock_count(PDO $db, string $packageSlug): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM voucher_codes WHERE package_slug = :slug AND status = 'available'"
    );
    $stmt->execute(['slug' => $packageSlug]);

    return (int) $stmt->fetchColumn();
}

function hp_stock_summary(PDO $db): array
{
    $stmt = $db->query(
        "SELECT p.slug, p.name, p.data_label,
                SUM(CASE WHEN v.status = 'available' THEN 1 ELSE 0 END) AS available,
                SUM(CASE WHEN v.status = 'assigned' THEN 1 ELSE 0 END) AS assigned,
                SUM(CASE WHEN v.status = 'revoked' THEN 1 ELSE 0 END) AS revoked
         FROM packages p
         LEFT JOIN voucher_codes v ON v.package_slug = p.slug
         GROUP BY p.slug
         ORDER BY p.sort_order"
    );

    return $stmt->fetchAll();
}

function hp_sold_codes(PDO $db, int $limit = 100, ?string $packageSlug = null): array
{
    $limit = max(1, min($limit, 500));
    $sql = "SELECT v.code, v.package_slug, v.paystack_reference, v.assigned_at,
                   p.name AS package_name, p.data_label,
                   pay.amount_pesewas, pay.paid_at, pay.reference AS payment_reference
            FROM voucher_codes v
            JOIN packages p ON p.slug = v.package_slug
            LEFT JOIN payments pay ON pay.voucher_code_id = v.id
            WHERE v.status = 'assigned'";

    $params = [];
    if ($packageSlug !== null && $packageSlug !== '') {
        $sql .= ' AND v.package_slug = :slug';
        $params['slug'] = $packageSlug;
    }

    $sql .= ' ORDER BY COALESCE(v.assigned_at, pay.paid_at) DESC, v.id DESC LIMIT :limit';

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':'.$key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function hp_format_ghs(int $pesewas): string
{
    return 'GH¢'.number_format($pesewas / 100, 2);
}

function hp_profile_to_slug(string $profile): ?string
{
    $map = hp_setting('profile_to_slug', []);

    return $map[$profile] ?? null;
}

function hp_import_csv(PDO $db, string $csvPath): array
{
    $handle = fopen($csvPath, 'r');
    if ($handle === false) {
        throw new RuntimeException('Could not open CSV file.');
    }

    $header = fgetcsv($handle, 0, ',', '"', '\\');
    if ($header === false) {
        fclose($handle);
        throw new RuntimeException('CSV is empty.');
    }

    $columns = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
    $codeIdx = array_search('code', $columns, true);
    $slugIdx = array_search('package_slug', $columns, true);
    $profileIdx = array_search('profile', $columns, true);

    if ($codeIdx === false) {
        fclose($handle);
        throw new RuntimeException('CSV must have a "code" column.');
    }

    if ($slugIdx === false && $profileIdx === false) {
        fclose($handle);
        throw new RuntimeException('CSV must have "package_slug" or "profile" column.');
    }

    $insert = $db->prepare(
        "INSERT OR IGNORE INTO voucher_codes (code, package_slug, status, created_at)
         VALUES (:code, :slug, 'available', datetime('now'))"
    );

    $imported = 0;
    $skipped = 0;
    $invalid = 0;

    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $code = strtoupper(trim((string) ($row[$codeIdx] ?? '')));
        if ($code === '') {
            $invalid++;
            continue;
        }

        $slug = '';
        if ($slugIdx !== false) {
            $slug = trim((string) ($row[$slugIdx] ?? ''));
        } else {
            $profile = trim((string) ($row[$profileIdx] ?? ''));
            $slug = hp_profile_to_slug($profile) ?? '';
        }

        if ($slug === '' || hp_get_package($db, $slug) === null) {
            $invalid++;
            continue;
        }

        $insert->execute(['code' => $code, 'slug' => $slug]);
        if ($insert->rowCount() > 0) {
            $imported++;
        } else {
            $skipped++;
        }
    }

    fclose($handle);

    return compact('imported', 'skipped', 'invalid');
}

function hp_create_payment(PDO $db, string $packageSlug, int $amountPesewas, string $reference): array
{
    $accessToken = bin2hex(random_bytes(16));

    $stmt = $db->prepare(
        'INSERT INTO payments (reference, access_token, package_slug, amount_pesewas, status, created_at)
         VALUES (:ref, :token, :slug, :amount, :status, datetime(\'now\'))'
    );
    $stmt->execute([
        'ref' => $reference,
        'token' => $accessToken,
        'slug' => $packageSlug,
        'amount' => $amountPesewas,
        'status' => 'pending',
    ]);

    return [
        'id' => (int) $db->lastInsertId(),
        'reference' => $reference,
        'access_token' => $accessToken,
    ];
}

function hp_payment_access_ok(?array $payment, ?string $accessToken): bool
{
    if ($payment === null || $accessToken === null || $accessToken === '') {
        return false;
    }

    $stored = (string) ($payment['access_token'] ?? '');

    return $stored !== '' && hash_equals($stored, $accessToken);
}

function hp_get_payment_by_reference(PDO $db, string $reference, ?string $accessToken = null): ?array
{
    $stmt = $db->prepare('SELECT * FROM payments WHERE reference = :ref');
    $stmt->execute(['ref' => $reference]);
    $payment = $stmt->fetch();

    if (! $payment) {
        return null;
    }

    if ($accessToken !== null && ! hp_payment_access_ok($payment, $accessToken)) {
        return null;
    }

    if ($payment['voucher_code_id']) {
        $codeStmt = $db->prepare('SELECT code FROM voucher_codes WHERE id = :id');
        $codeStmt->execute(['id' => $payment['voucher_code_id']]);
        $payment['code'] = $codeStmt->fetchColumn() ?: null;
    } else {
        $payment['code'] = null;
    }

    return $payment;
}

function hp_fulfill_payment(
    PDO $db,
    string $reference,
    ?string $buyerEmail,
    ?string $buyerPhone,
    ?int $verifiedAmountPesewas = null
): array {
    $db->beginTransaction();

    try {
        $stmt = $db->prepare('SELECT * FROM payments WHERE reference = :ref');
        $stmt->execute(['ref' => $reference]);
        $payment = $stmt->fetch();

        if (! $payment) {
            $db->rollBack();

            return ['ok' => false, 'reason' => 'payment_not_found'];
        }

        if ($verifiedAmountPesewas !== null
            && (int) $payment['amount_pesewas'] !== $verifiedAmountPesewas) {
            $db->rollBack();

            return ['ok' => false, 'reason' => 'amount_mismatch'];
        }

        if ($payment['status'] === 'paid' && $payment['voucher_code_id']) {
            $db->commit();

            return ['ok' => true, 'already' => true, 'payment' => hp_get_payment_by_reference($db, $reference)];
        }

        if ($payment['status'] === 'paid_no_stock') {
            $db->commit();

            return ['ok' => false, 'reason' => 'no_stock'];
        }

        $codeStmt = $db->prepare(
            "SELECT id, code FROM voucher_codes
             WHERE package_slug = :slug AND status = 'available'
             ORDER BY id ASC LIMIT 1"
        );
        $codeStmt->execute(['slug' => $payment['package_slug']]);
        $voucher = $codeStmt->fetch();

        if (! $voucher) {
            $db->prepare(
                "UPDATE payments SET status = 'paid_no_stock', paid_at = datetime('now'),
                 buyer_email = COALESCE(:email, buyer_email), buyer_phone = COALESCE(:phone, buyer_phone)
                 WHERE id = :id"
            )->execute([
                'email' => $buyerEmail,
                'phone' => $buyerPhone,
                'id' => $payment['id'],
            ]);
            $db->commit();

            return ['ok' => false, 'reason' => 'no_stock'];
        }

        $db->prepare(
            "UPDATE voucher_codes SET status = 'assigned', paystack_reference = :ref,
             buyer_email = :email, buyer_phone = :phone, assigned_at = datetime('now')
             WHERE id = :id"
        )->execute([
            'ref' => $reference,
            'email' => $buyerEmail,
            'phone' => $buyerPhone,
            'id' => $voucher['id'],
        ]);

        $db->prepare(
            "UPDATE payments SET status = 'paid', voucher_code_id = :vid, paid_at = datetime('now'),
             buyer_email = COALESCE(:email, buyer_email), buyer_phone = COALESCE(:phone, buyer_phone)
             WHERE id = :id"
        )->execute([
            'vid' => $voucher['id'],
            'email' => $buyerEmail,
            'phone' => $buyerPhone,
            'id' => $payment['id'],
        ]);

        $db->commit();

        return ['ok' => true, 'payment' => hp_get_payment_by_reference($db, $reference)];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }
}
