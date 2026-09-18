<?php
declare(strict_types=1);

function isHttpsRequest(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isHttpsRequest(),'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

function sendSecurityHeaders(string $contentType = 'text/html; charset=utf-8'): void
{
    header('Content-Type: ' . $contentType);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; connect-src 'self'");
    if (isHttpsRequest()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

function csrfToken(string $key = 'csrf'): string
{
    if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) $_SESSION[$key] = bin2hex(random_bytes(32));
    return $_SESSION[$key];
}

function csrfIsValid(string $submitted, string $key = 'csrf'): bool
{
    return isset($_SESSION[$key]) && is_string($_SESSION[$key]) && $submitted !== '' && hash_equals($_SESSION[$key], $submitted);
}

function rateLimit(string $key, int $limit, int $windowSeconds): bool
{
    $now = time();
    $bucket = $_SESSION['_rate_limits'][$key] ?? [];
    $bucket = array_values(array_filter($bucket, static fn($time): bool => is_int($time) && $time > $now - $windowSeconds));
    if (count($bucket) >= $limit) { $_SESSION['_rate_limits'][$key] = $bucket; return false; }
    $bucket[] = $now;
    $_SESSION['_rate_limits'][$key] = $bucket;
    return true;
}

function requireSameOrigin(): void
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($origin !== '' && $host !== '' && strcasecmp((string) parse_url($origin, PHP_URL_HOST), $host) !== 0) {
        http_response_code(403); exit('Forbidden');
    }
}
