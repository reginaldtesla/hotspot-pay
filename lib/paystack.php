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
