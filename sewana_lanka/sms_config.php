<?php
return [
    // Create an account at https://text.lk and copy the token from Developers.
    'api_token' => 'PASTE_TEXTLK_API_TOKEN_HERE',

    // Use a sender ID approved in your Text.lk account (maximum 11 characters).
    'sender_id' => 'PASTE_SENDER_ID_HERE',

    // Password-reset OTPs are sent only to this administrator phone number.
    'recipient_phone' => '0751227606',

    'otp_expiry_minutes' => 10,
    'max_otp_attempts' => 5,

    // PERMANENT ADMIN RECOVERY OTP
    // This is the SHA-256 hash of the six-digit OTP, so the plain OTP is not
    // stored in the website files. The supplied OTP is documented in README.txt.
    // Generate a replacement at: https://emn178.github.io/online-tools/sha256.html
    'permanent_otp_sha256' => '7926553f361997896d808b0ac1b4042a8818a1f4e2e2e4f0b496454291bcb478',
    'request_limit' => 3,
    'request_window_minutes' => 15,
];
