<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!empty($_SESSION['admin_logged'])) {
    header('Location: admin.php');
    exit;
}

$error = '';
$notice = (($_GET['reset'] ?? '') === 'success')
    ? 'Your password has been changed. Log in with your new password.'
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT id, username, password FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        header('Location: admin.php');
        exit;
    }

    $error = 'Invalid username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Login | Sewana Lanka</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<main class="login-card">
    <img src="assets/sewana-logo.jpg" alt="Sewana Lanka logo">
    <p class="eyebrow dark">Restricted administrator area</p>
    <h1>Administrator Login</h1>
    <p>Only an authorized administrator can upload or manage property posts.</p>

    <?php if ($notice): ?>
        <div class="alert success"><?= e($notice) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>
            Username
            <input name="username" required autocomplete="username">
        </label>
        <label>
            Password
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="button primary full" type="submit">Log In</button>
    </form>
    <a class="back-link" href="forgot_password.php">Forgot your password?</a>
    <a class="back-link" href="index.php">← Return to website</a>
</main>
</body>
</html>
