<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';
startSecureSession();
sendSecurityHeaders();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$setupKey = getenv('SEWANA_SETUP_KEY') ?: 'SL-ADMIN-2026-8F4C2A';
$error = '';

try {
    $conn->exec('CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
} catch (Throwable $exception) {
    error_log('Admin setup check failed: ' . $exception->getMessage());
    $error = 'The database is not ready. Check the database details and try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    requireSameOrigin();
    $submittedKey = trim((string) ($_POST['setup_key'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!csrfIsValid((string) ($_POST['csrf'] ?? ''), 'admin_setup_csrf')) {
        $error = 'This form expired. Refresh the page and try again.';
    } elseif (!hash_equals($setupKey, $submittedKey)) {
        $error = 'The setup key is incorrect.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $error = 'Use 3–50 letters, numbers, dots, underscores or hyphens for the username.';
    } elseif (strlen($password) < 12 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Use at least 12 characters containing letters and numbers.';
    } elseif ($password !== $confirmPassword) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($hash === false) throw new RuntimeException('Password hashing failed.');
            $stmt = $conn->prepare('INSERT INTO users (username, password) VALUES (:username, :password) ON DUPLICATE KEY UPDATE password = VALUES(password)');
            $stmt->execute(['username' => $username, 'password' => $hash]);
            session_regenerate_id(true);
            $_SESSION = [];
            header('Location: sewana-admin-access.php?created=success');
            exit;
        } catch (Throwable $exception) {
            error_log('Admin creation failed: ' . $exception->getMessage());
            $error = 'The administrator account could not be created.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Create Administrator | Sewana Lanka</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page"><main class="login-card">
    <img src="assets/sewana-logo.jpg" alt="Sewana Lanka logo">
    <p class="eyebrow dark">Protected account setup</p>
    <h1>Create Administrator</h1>
    <p>Create or repair the administrator login, then use the normal login page.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrfToken('admin_setup_csrf')) ?>">
        <label>Setup key<input type="password" name="setup_key" required autocomplete="off"></label>
        <label>Admin username<input name="username" value="admin" required maxlength="50" autocomplete="username"></label>
        <label>New password<input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
        <label>Confirm password<input type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></label>
        <button class="button primary full" type="submit">Create Administrator</button>
    </form>
    <p class="helper-text">Delete create_admin.php after the administrator login works.</p>
    <a class="back-link" href="sewana-admin-access.php">Return to admin login</a>
</main></body></html>
