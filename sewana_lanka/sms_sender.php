<?php
function smsConfigured(array $config): bool
{
    $apiToken = trim((string) ($config['api_token'] ?? ''));
    $senderId = trim((string) ($config['sender_id'] ?? ''));

    return $apiToken !== ''
        && $senderId !== ''
        && strpos($apiToken, 'PASTE_') !== 0
        && strpos($senderId, 'PASTE_') !== 0;
}

function textLkRecipient(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);

    if (preg_match('/^0[0-9]{9}$/', $digits)) {
        return '94' . substr($digits, 1);
    }

    if (preg_match('/^94[0-9]{9}$/', $digits)) {
        return $digits;
    }

    throw new RuntimeException('The recovery phone number must use a valid Sri Lankan format.');
}

function maskPhoneNumber(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) < 7) {
        return 'registered phone';
    }

    return substr($digits, 0, 3) . '****' . substr($digits, -3);
}

function sendPasswordResetOtp(
    array $config,
    string $recipient,
    string $otp,
    int $expiryMinutes
): void {
    if (!smsConfigured($config)) {
        throw new RuntimeException('Text.lk SMS is not configured.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension must be enabled to send SMS.');
    }

    $payload = json_encode([
        'recipient' => $recipient,
        'sender_id' => trim((string) $config['sender_id']),
        'type' => 'plain',
        'message' => 'Sewana Lanka password reset OTP: ' . $otp
            . '. It expires in ' . $expiryMinutes . ' minutes. Do not share this code.',
    ], JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        throw new RuntimeException('The SMS request could not be created.');
    }

    $curl = curl_init('https://app.text.lk/api/v3/sms/send');
    if ($curl === false) {
        throw new RuntimeException('The SMS connection could not be initialized.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . trim((string) $config['api_token']),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('SMS connection error: ' . $curlError);
    }

    $responseData = json_decode($response, true);
    $providerRejected = is_array($responseData)
        && strtolower((string) ($responseData['status'] ?? '')) === 'error';

    if ($httpCode < 200 || $httpCode >= 300 || $providerRejected) {
        $providerMessage = is_array($responseData)
            ? trim((string) ($responseData['message'] ?? ''))
            : '';
        throw new RuntimeException(
            'SMS provider rejected the request'
            . ($providerMessage !== '' ? ': ' . $providerMessage : ' (HTTP ' . $httpCode . ')')
        );
    }
}
