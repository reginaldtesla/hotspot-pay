<?php

declare(strict_types=1);

function hp_paystack_secret(): string
{
    $key = (string) hp_setting('paystack_secret_key', '');
    if ($key === '') {
        throw new RuntimeException('Paystack secret key is not configured.');
    }

    return $key;
}

function hp_paystack_initialize(
    string $reference,
    int $amountPesewas,
    string $email,
    string $callbackUrl,
    array $metadata
): array {
    $payload = [
        'reference' => $reference,
        'amount' => $amountPesewas,
        'email' => $email,
        'callback_url' => $callbackUrl,
        'metadata' => $metadata,
    ];

    return hp_paystack_request('POST', 'https://api.paystack.co/transaction/initialize', $payload);
}

function hp_paystack_verify_signature(string $rawBody, string $signature): bool
{
    if ($signature === '') {
        return false;
    }

    $computed = hash_hmac('sha512', $rawBody, hp_paystack_secret());

    return hash_equals($computed, $signature);
}

function hp_paystack_verify_transaction(string $reference): array
{
    return hp_paystack_request(
        'GET',
        'https://api.paystack.co/transaction/verify/'.rawurlencode($reference)
    );
}

function hp_paystack_transaction_ok(array $verifyResponse, int $expectedAmountPesewas): bool
{
    $data = $verifyResponse['data'] ?? null;
    if (! is_array($data)) {
        return false;
    }

    if (($data['status'] ?? '') !== 'success') {
        return false;
    }

    if ((string) ($data['currency'] ?? '') !== 'GHS') {
        return false;
    }

    return (int) ($data['amount'] ?? 0) === $expectedAmountPesewas;
}

function hp_paystack_request(string $method, string $url, ?array $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer '.hp_paystack_secret(),
            'Content-Type: application/json',
        ],
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException('Paystack request failed: '.$error);
    }

    $decoded = json_decode((string) $response, true);
    if (! is_array($decoded)) {
        throw new RuntimeException('Invalid Paystack response.');
    }

    if ($status < 200 || $status >= 300 || ! ($decoded['status'] ?? false)) {
        $message = (string) ($decoded['message'] ?? 'Paystack error');
        throw new RuntimeException($message);
    }

    return $decoded;
}

/**
 * Normalize Ghana MoMo numbers to 233XXXXXXXXX. Returns null for empty/invalid input.
 */
function hp_normalize_ghana_phone(?string $raw): ?string
{
    if ($raw === null || trim($raw) === '' || str_contains($raw, '***')) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '' || strlen($digits) < 9) {
        return null;
    }

    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return '233'.substr($digits, 1);
    }

    if (strlen($digits) === 9) {
        return '233'.$digits;
    }

    if (str_starts_with($digits, '233') && strlen($digits) >= 12) {
        return $digits;
    }

    return strlen($digits) >= 10 ? $digits : null;
}

/**
 * Masked MoMo identifier from Paystack authorization (e.g. 055***5735) when full phone is absent.
 */
function hp_paystack_mobile_money_mask(array $auth, string $channel): ?string
{
    $authChannel = (string) ($auth['channel'] ?? '');
    if ($channel !== 'mobile_money' && $authChannel !== 'mobile_money') {
        return null;
    }

    $prefix = preg_replace('/\D+/', '', (string) ($auth['bin'] ?? ''));
    $suffix = preg_replace('/\D+/', '', (string) ($auth['last4'] ?? ''));

    if (strlen($prefix) < 3 || strlen($suffix) < 3) {
        return null;
    }

    return substr($prefix, 0, 3).'***'.substr($suffix, -4);
}

/**
 * Extract buyer contact from a Paystack transaction payload (webhook or verify response).
 *
 * @return array{email: ?string, phone: ?string}
 */
function hp_paystack_extract_buyer(array $data): array
{
    $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
    $auth = is_array($data['authorization'] ?? null) ? $data['authorization'] : [];

    $email = trim((string) ($customer['email'] ?? ''));
    $email = $email !== '' ? $email : null;

    $phone = trim((string) ($customer['phone'] ?? ''));
    if ($phone === '' && ! empty($customer['international_format_phone'])) {
        $phone = trim((string) $customer['international_format_phone']);
    }

    $meta = $data['metadata'] ?? null;
    if ($phone === '' && is_array($meta)) {
        foreach (['phone', 'mobile_money_phone', 'customer_phone'] as $key) {
            if (! empty($meta[$key])) {
                $phone = trim((string) $meta[$key]);
                break;
            }
        }
    }

    if ($phone === '' && $email !== null && preg_match('/^(\d{10,15})@/', $email, $m)) {
        $phone = $m[1];
    }

    $normalized = hp_normalize_ghana_phone($phone !== '' ? $phone : null);
    if ($normalized !== null) {
        return ['email' => $email, 'phone' => $normalized];
    }

    $masked = hp_paystack_mobile_money_mask($auth, (string) ($data['channel'] ?? ''));
    if ($masked !== null) {
        return ['email' => $email, 'phone' => $masked];
    }

    return ['email' => $email, 'phone' => null];
}

/**
 * Prefer full phone over masked fragments when merging webhook + verify payloads.
 *
 * @param array{email: ?string, phone: ?string} ...$buyers
 * @return array{email: ?string, phone: ?string}
 */
function hp_paystack_merge_buyer(array ...$buyers): array
{
    $email = null;
    $phone = null;

    foreach ($buyers as $buyer) {
        if ($email === null && ! empty($buyer['email'])) {
            $email = $buyer['email'];
        }

        $candidate = $buyer['phone'] ?? null;
        if ($candidate === null || $candidate === '') {
            continue;
        }

        if ($phone === null) {
            $phone = $candidate;
            continue;
        }

        $phoneMasked = str_contains($phone, '***');
        $candidateMasked = str_contains($candidate, '***');

        if ($phoneMasked && ! $candidateMasked) {
            $phone = $candidate;
        } elseif (! $phoneMasked && ! $candidateMasked && strlen($candidate) > strlen($phone)) {
            $phone = $candidate;
        }
    }

    return ['email' => $email, 'phone' => $phone];
}
