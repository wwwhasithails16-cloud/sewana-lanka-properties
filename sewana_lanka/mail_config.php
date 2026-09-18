<?php

return [
    // Gmail SMTP settings. Use a Google App Password, not your normal password.
    'smtp_host' => getenv('SEWANA_SMTP_HOST') ?: 'smtp.gmail.com',
    'smtp_port' => (int) (getenv('SEWANA_SMTP_PORT') ?: 587),
    'smtp_encryption' => getenv('SEWANA_SMTP_ENCRYPTION') ?: 'tls',
    'smtp_username' => getenv('SEWANA_SMTP_USERNAME') ?: 'Sewanalanka.info@gmail.com',
    'smtp_password' => getenv('SEWANA_SMTP_PASSWORD') ?: '1234',
    'from_email' => getenv('SEWANA_FROM_EMAIL') ?: 'Sewanalanka.info@gmail.com',
    'from_name' => getenv('SEWANA_FROM_NAME') ?: 'Sewana Lanka',

    // Leave empty to detect the URL automatically (works with localhost/XAMPP).
    // For a live site, use your full folder URL, for example:
    // https://example.com/sewana_lanka_district_media_status
    'app_url' => getenv('SEWANA_APP_URL') ?: '',
    'reset_expiry_minutes' => 60,
];
