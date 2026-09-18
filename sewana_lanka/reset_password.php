<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/sms_sender.php';

$smsConfig = require __DIR__ . '/sms_config.php';
$error = '';
$requestToken = trim((string) ($_POST['request'] ?? $_GET['request'] ?? ''));
$requestTokenHash = '';
$otpRecord = null;
$maxAttempts = max(3, min(10, (int) ($smsConfig['max_otp_attempts'] ?? 5)));

if (empty($_SESSION['password_reset_form_csrf'])) {
    $_SESSION['password_reset_form_csrf'] = bin2hex(random_bytes(32));
}

if (preg_match('/^[a-f0-9]{64}$/i', $requestToken)) {
    $requestTokenHash = hash('sha256', $requestToken);
    $stmt = $conn->prepare(
        'SELECT id, user_id, attempts
         FROM password_reset_otps
         WHERE request_token_hash = :request_token_hash
           AND used_at IS NULL
           AND expires_at > NOW()
           AND attempts < :max_attempts
         LIMIT 1'
    );
    $stmt->bindValue(':request_token_hash', $requestTokenHash);
    $stmt->bindValue(':max_attempts', $maxAttempts, PDO::PARAM_INT);
    $stmt->execute();
    $otpRecord = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $otpRecord) {
    $csrf = (string) ($_POST['csrf'] ?? '');
    $otp = trim((string) ($_POST['otp'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!hash_equals((string) $_SESSION['password_reset_form_csrf'], $csrf)) {
        $error = 'This form expired. Refresh the page and try again.';
    } elseif (!preg_match('/^[0-9]{6}$/', $otp)) {
        $error = 'Enter the six-digit OTP sent to your phone.';
    } elseif (strlen($password) < 8) {
        $error = 'Use at least 8 characters for the new password.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'The password must contain at least one letter and one number.';
    } elseif ($password !== $confirmPassword) {
        $error = 'The two passwords do not match.';
    } else {
        try {
            $conn->beginTransaction();

            $lock = $conn->prepare(
                'SELECT id, user_id, otp_hash, attempts
                 FROM password_reset_otps
                 WHERE id = :id
                   AND request_token_hash = :request_token_hash
                   AND used_at IS NULL
                   AND expires_at > NOW()
                   AND attempts < :max_attempts
                 FOR UPDATE'
            );
            $lock->bindValue(':id', (int) $otpRecord['id'], PDO::PARAM_INT);
            $lock->bindValue(':request_token_hash', $requestTokenHash);
            $lock->bindValue(':max_attempts', $maxAttempts, PDO::PARAM_INT);
            $lock->execute();
            $lockedOtp = $lock->fetch();

            if (!$lockedOtp) {
                throw new RuntimeException('This OTP request has expired or is no longer valid.');
            }

            // Compare SHA-256 values with hash_equals to avoid timing leaks.
            // The plain permanent OTP is never stored in the database or config.
            if (!hash_equals(strtolower((string) $lockedOtp['otp_hash']), hash('sha256', $otp))) {
                $newAttemptCount = (int) $lockedOtp['attempts'] + 1;
                $failed = $conn->prepare(
                    'UPDATE password_reset_otps
                     SET attempts = :attempts_value,
                         used_at = CASE
                             WHEN :attempts_compare >= :max_attempts THEN NOW()
                             ELSE used_at
                         END
                     WHERE id = :id'
                );
                $failed->bindValue(':attempts_value', $newAttemptCount, PDO::PARAM_INT);
                $failed->bindValue(':attempts_compare', $newAttemptCount, PDO::PARAM_INT);
                $failed->bindValue(':max_attempts', $maxAttempts, PDO::PARAM_INT);
                $failed->bindValue(':id', (int) $lockedOtp['id'], PDO::PARAM_INT);
                $failed->execute();
                $conn->commit();

                $remaining = max(0, $maxAttempts - $newAttemptCount);
                if ($remaining === 0) {
                    $otpRecord = null;
                    $error = 'Too many incorrect attempts. Request a new OTP.';
                } else {
                    $error = 'Incorrect OTP. ' . $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' remaining.';
                }
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                if ($passwordHash === false) {
                    throw new RuntimeException('The password could not be secured.');
                }

                $updateUser = $conn->prepare('UPDATE users SET password = :password WHERE id = :id');
                $updateUser->execute([
                    'password' => $passwordHash,
                    'id' => (int) $lockedOtp['user_id'],
                ]);

                $useOtps = $conn->prepare(
                    'UPDATE password_reset_otps
                     SET used_at = NOW()
                     WHERE user_id = :user_id AND used_at IS NULL'
                );
                $useOtps->execute(['user_id' => (int) $lockedOtp['user_id']]);

                $conn->commit();
                unset($_SESSION['password_reset_form_csrf']);
                session_regenerate_id(true);
                header('Location: sewana-admin-access.php?reset=success');
                exit;
            }
        } catch (Throwable $resetError) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Password update error: ' . $resetError->getMessage());
            $error = $resetError instanceof RuntimeException
                ? $resetError->getMessage()
                : 'The password could not be changed. Request a new OTP and try again.';
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
    <title>Verify OTP | Sewana Lanka</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<main class="login-card">
    <img src="assets/sewana-logo.jpg" alt="Sewana Lanka logo">
    <p class="eyebrow dark">Administrator account recovery</p>
    <h1>Enter OTP</h1>

    <?php if (!$otpRecord): ?>
        <div class="alert error"><?= e($error !== '' ? $error : 'This OTP request is invalid, expired, or has already been used.') ?></div>
        <a class="button primary full" href="forgot_password.php">Request a New OTP</a>
    <?php else: ?>
        <p>Enter your permanent six-digit recovery OTP, then choose a new password.</p>

        <?php if ($error): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="reset_password.php">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['password_reset_form_csrf']) ?>">
            <input type="hidden" name="request" value="<?= e($requestToken) ?>">
            <label>
                Permanent Recovery OTP
                <input class="otp-input" name="otp" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000">
            </label>
            <label>
                New Password
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
            <label>
                Confirm New Password
                <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
            </label>
            <button class="button primary full" type="submit">Verify OTP &amp; Change Password</button>
        </form>
        <p class="helper-text">The reset request expires after 10 minutes and is locked after <?= e((string) $maxAttempts) ?> incorrect attempts.</p>
        <a class="back-link" href="forgot_password.php">Start a new reset request</a>
    <?php endif; ?>

    <a class="back-link" href="sewana-admin-access.php">← Return to admin login</a>
</main>
</body>
</html>
