<?php
declare(strict_types=1);

/*
 * OCEAN ACROSS — BREVO HTTPS API FORM HANDLER
 *
 * Recommended when SMTP ports are blocked or SMTP IP restrictions are unclear.
 * Upload as: the same directory as contact.html
 * Requirement: PHP 8+ and either cURL or allow_url_fopen.
 */

// --------------------------------------------------------------------------
// 1. CONFIGURATION — PASTE A BREVO API KEY, NOT THE SMTP KEY
// --------------------------------------------------------------------------
$brevoApiKey = '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';

// This sender is already proven in your Brevo/GMass test.
$fromEmail = 'info@oceanspay.co.uk';
$fromName  = 'Ocean Across Website';

// All website leads arrive here.
$toEmail = 'palkesh@oceanspay.co.uk';
$toName  = 'Ocean Across';

// --------------------------------------------------------------------------
// 2. INTERNAL FUNCTIONS
// --------------------------------------------------------------------------

function cleanHeader(string $value, int $limit = 200): string
{
    $value = preg_replace('/[\r\n\x00-\x1F\x7F]+/', ' ', $value) ?? '';
    return substr(trim($value), 0, $limit);
}

function cleanValue(mixed $value, int $limit = 5000): string
{
    if (is_array($value)) {
        $items = [];
        foreach (array_slice($value, 0, 20) as $item) {
            if (is_scalar($item)) $items[] = cleanValue((string)$item, 300);
        }
        return implode(', ', array_filter($items, static fn($item) => $item !== ''));
    }
    if (!is_scalar($value)) return '';
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string)$value) ?? '';
    return substr(trim($text), 0, $limit);
}

function displayLabel(string $key): string
{
    $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key) ?? $key;
    $key = str_replace(['_', '-'], ' ', $key);
    return ucwords(trim($key));
}

function finishRequest(bool $success): void
{
    header('Location: contact.html?lead=' . ($success ? 'sent' : 'error') . '#demo-form', true, 303);
    exit;
}

function sendBrevoRequest(string $apiKey, array $payload): array
{
    $url = 'https://api.brevo.com/v3/smtp/email';
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $headers = [
        'accept: application/json',
        'api-key: ' . $apiKey,
        'content-type: application/json',
    ];

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('Could not initialise HTTPS request');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false) throw new RuntimeException('Brevo HTTPS connection failed: ' . $error);
        return [$status, (string)$body];
    }

    if (!filter_var((string)ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        throw new RuntimeException('Neither cURL nor allow_url_fopen is available');
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $json,
            'ignore_errors' => true,
            'timeout' => 30,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $match)) $status = (int)$match[1];
    }
    if ($body === false) throw new RuntimeException('Brevo HTTPS connection failed');
    return [$status, (string)$body];
}

// --------------------------------------------------------------------------
// 3. PROCESS THE FORM
// --------------------------------------------------------------------------

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed');
}

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
    http_response_code(413);
    exit('Form submission is too large');
}

// Honeypot. Bots fill this; real visitors do not.
if (cleanValue($_POST['website'] ?? '', 300) !== '') {
    finishRequest(true);
}

$visitorName = cleanValue($_POST['name'] ?? $_POST['full_name'] ?? '', 120);
$visitorEmail = cleanValue($_POST['email'] ?? '', 254);
$formName = cleanHeader(cleanValue($_POST['form_name'] ?? 'Website Enquiry', 100));

if ($visitorName === '' || !filter_var($visitorEmail, FILTER_VALIDATE_EMAIL)) {
    finishRequest(false);
}

$ignoredFields = ['website', 'return_to', 'form_name', 'submit'];
$plainLines = [
    'A new Ocean Across website form has been submitted.',
    '',
    'Form: ' . $formName,
    'Submitted: ' . date('d M Y, H:i T'),
    '',
];
$htmlRows = '';

foreach ($_POST as $key => $value) {
    if (!is_string($key) || in_array($key, $ignoredFields, true)) continue;
    $label = displayLabel(substr($key, 0, 100));
    $clean = cleanValue($value);
    $displayValue = $clean !== '' ? $clean : '(not provided)';
    $plainLines[] = $label . ': ' . $displayValue;
    $plainLines[] = '';
    $htmlRows .= '<tr><th style="padding:8px 12px;text-align:left;vertical-align:top;border-bottom:1px solid #e8e5dd">' .
        htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
        '</th><td style="padding:8px 12px;border-bottom:1px solid #e8e5dd">' .
        nl2br(htmlspecialchars($displayValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</td></tr>';
}

$subject = '[Ocean Across] New ' . ($formName !== '' ? $formName : 'Website Enquiry');
$htmlContent = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#163c35">' .
    '<h2>New Ocean Across website enquiry</h2><p><strong>Form:</strong> ' .
    htmlspecialchars($formName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' .
    '<table style="border-collapse:collapse;width:100%;max-width:720px">' . $htmlRows . '</table></body></html>';

try {
    if ($brevoApiKey === 'PASTE_BREVO_API_KEY_HERE' || trim($brevoApiKey) === '') {
        throw new RuntimeException('Brevo API key has not been configured');
    }
    foreach ([$fromEmail, $toEmail] as $configuredEmail) {
        if (!filter_var($configuredEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid configured email address');
        }
    }

    [$status, $responseBody] = sendBrevoRequest($brevoApiKey, [
        'sender' => ['name' => $fromName, 'email' => $fromEmail],
        'to' => [['name' => $toName, 'email' => $toEmail]],
        'replyTo' => ['name' => $visitorName, 'email' => $visitorEmail],
        'subject' => $subject,
        'htmlContent' => $htmlContent,
        'tags' => ['website-lead'],
    ]);

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Brevo API returned HTTP ' . $status . ': ' . substr($responseBody, 0, 500));
    }

    finishRequest(true);
} catch (Throwable $error) {
    error_log('Ocean Across form email failed: ' . $error->getMessage());
    finishRequest(false);
}

