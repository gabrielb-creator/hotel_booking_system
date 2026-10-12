<?php

$localMail = __DIR__ . '/mail.local.php';
if (is_file($localMail)) {
    $mailSettings = require $localMail;
    if (!is_array($mailSettings)) $mailSettings = [];
} else {
    $mailSettings = [];
}
$setting = static function (string $key, string $default = '') use ($mailSettings): string {
    $env = getenv($key);
    if ($env !== false && $env !== '') return trim((string)$env);
    return trim((string)($mailSettings[$key] ?? $default));
};
if (!defined('LUXESTAY_MAIL_FROM')) define('LUXESTAY_MAIL_FROM', $setting('LUXESTAY_MAIL_FROM'));
if (!defined('LUXESTAY_MAIL_FROM_NAME')) define('LUXESTAY_MAIL_FROM_NAME', $setting('LUXESTAY_MAIL_FROM_NAME', 'LuxeStay Hotel'));
if (!defined('SMTP_HOST')) define('SMTP_HOST', $setting('SMTP_HOST', 'smtp.gmail.com'));
if (!defined('SMTP_PORT')) define('SMTP_PORT', (int)$setting('SMTP_PORT', '587'));
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', $setting('SMTP_USERNAME'));
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', $setting('SMTP_PASSWORD'));
?>
