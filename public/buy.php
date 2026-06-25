<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/bootstrap.php';
require_once dirname(__DIR__).'/lib/paystack.php';

$slug = trim((string) ($_GET['pkg'] ?? ''));
if ($slug === '') {
    http_response_code(400);
    echo 'Missing package.';
    exit;
}

$db = hp_db();
$package = hp_get_package($db, $slug);

if ($package === null) {
    http_response_code(404);
    echo 'Package not found.';
    exit;
}

if (hp_stock_count($db, $slug) < 1) {
    http_response_code(503);
    echo 'This package is temporarily out of stock. Please try again later.';
    exit;
}

$reference = hp_reference();
$payment = hp_create_payment($db, $slug, (int) $package['amount_pesewas'], $reference);
$accessToken = $payment['access_token'];

$appUrl = rtrim((string) hp_setting('app_url', ''), '/');
$callbackUrl = $appUrl.'/callback.php?ref='.urlencode($reference).'&tok='.urlencode($accessToken);
$email = (string) hp_setting('checkout_email', 'checkout@tesnet.xyz');

try {
    $result = hp_paystack_initialize(
        $reference,
        (int) $package['amount_pesewas'],
        $email,
        $callbackUrl,
        [
            'package_slug' => $slug,
            'package_name' => $package['name'],
            'custom_fields' => [
                [
                    'display_name' => 'Package',
                    'variable_name' => 'package',
                    'value' => $package['name'],
                ],
            ],
        ]
    );
} catch (Throwable $e) {
    http_response_code(502);
    echo 'Could not start payment. Please try again.';
    exit;
}

$authUrl = (string) ($result['data']['authorization_url'] ?? '');
if ($authUrl === '') {
    http_response_code(502);
    echo 'Invalid payment response.';
    exit;
}

hp_redirect($authUrl);
