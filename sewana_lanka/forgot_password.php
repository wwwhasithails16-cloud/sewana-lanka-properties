<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$smsConfig = require __DIR__ . '/sms_config.php';
$error = '';
$phone = trim((string) ($smsConfig['recipient_phone'] ?? ''));

if (empty($_SESSION['password_reset_request_csrf'])) {
    $_SESSION['password_reset_request_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');

    if (!hash_equals((string) $_SESSION['password_reset_request_csrf'], $csrf)) {
        $error = 'This form expired. Refresh the page and try again.';
    } else {
        try {

            $stmt = $conn->prepare(
                'SELECT id, phone
                 FROM users
                 WHERE username = :username AND phone = :phone
                 LIMIT 1'
            );
            $stmt->execute([
                'username' => 'admin',
                'phone' => $phone,
            ]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new RuntimeException('The administrator recovery phone is missing. Import phone_otp_update.sql in phpMyAdmin.');
            }

            $requestLimit = max(1, min(10, (int) ($smsConfig['request_limit'] ?? 3)));
            $windowMinutes = max(5, min(60, (int) ($smsConfig['request_window_minutes'] ?? 15)));
            $rateStmt = $conn->prepare(
                'SELECT COUNT(*)
                 FROM password_reset_otps
                 WHERE user_id = :user_id
                   AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $windowMinutes . ' MINUTE)'
            );
            $rateStmt->execute(['user_id' => (int) $user['id']]);

            if ((int) $rateStmt->fetchColumn() >= $requestLimit) {
                throw new RuntimeException('Too many OTP requests. Wait ' . $windowMinutes . ' minutes and try again.');
            }

            // Store the configured permanent OTP hash in this short-lived reset
            // request. The permanent code itself is never sent to the browser.
            $otpHash = trim((string) ($smsConfig['permanent_otp_sha256'] ?? ''));
            if (!preg_match('/^[a-f0-9]{64}$/i', $otpHash)) {
                throw new RuntimeException('The permanent recovery OTP is not configured correctly.');
            }

            $requestToken = bin2hex(random_bytes(32));
            $requestTokenHash = hash('sha256', $requestToken);
            $expiryMinutes = max(5, min(30, (int) ($smsConfig['otp_expiry_minutes'] ?? 10)));

            $insert = $conn->prepare(
                'INSERT INTO password_reset_otps
                    (user_id, request_token_hash, otp_hash, expires_at, request_ip)
                 VALUES
                    (:user_id, :request_token_hash, :otp_hash,
                     DATE_ADD(NOW(), INTERVAL ' . $expiryMinutes . ' MINUTE), :request_ip)'
            );
            $insert->execute([
                'user_id' => (int) $user['id'],
                'request_token_hash' => $requestTokenHash,
                'otp_hash' => $otpHash,
                'request_ip' => requestIp(),
            ]);
            $otpId = (int) $conn->lastInsertId();

            $_SESSION['password_reset_request_csrf'] = bin2hex(random_bytes(32));
            header('Location: reset_password.php?request=' . rawurlencode($requestToken));
            exit;
        } catch (RuntimeException $requestError) {
            error_log('Password reset OTP request error: ' . $requestError->getMessage());
            $error = $requestError->getMessage();
        } catch (Throwable $requestError) {
            error_log('Password reset OTP request error: ' . $requestError->getMessage());
            $error = 'The OTP could not be generated. Check the database update, then try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Forgot Password | Sewana Lanka</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<main class="login-card">
    <img src="assets/sewana-logo.jpg" alt="Sewana Lanka logo">
    <p class="eyebrow dark">Administrator account recovery</p>
    <h1>Reset Password</h1>
    <p>Start a secure password reset using your permanent six-digit recovery OTP.</p>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['password_reset_request_csrf']) ?>">
        <button class="button primary full" type="submit">Start Password Reset</button>
    </form>
    <p class="helper-text">Your permanent OTP is not displayed or sent. The reset request expires after 10 minutes.</p>

    <a class="back-link" href="sewana-admin-access.php">← Return to admin login</a>
</main>
</body>
</html>
