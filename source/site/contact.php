<?php
// contact.php — handles AJAX form POST and sends email to site owner.
// Must be served from the same sheets/{id}/site/ directory as index.php.

header('Content-Type: application/json');

// ── Load site email from site.json ───────────────────────────────────────────
$dir  = dirname(__DIR__);   // sheets/{id}/
$site = json_decode(@file_get_contents($dir . '/site.json') ?: '[]', true) ?: [];
$toEmail   = '';
$company   = '';
foreach ($site as $row) {
    $n = trim($row['Name']  ?? '');
    $v = trim($row['Value'] ?? '');
    if ($n === 'Email')       $toEmail = $v;
    if ($n === 'CompanyName') $company = $v;
}

if (!$toEmail) {
    echo json_encode(['ok' => false, 'message' => 'Contact address not configured.']);
    exit;
}

// ── Validate input ────────────────────────────────────────────────────────────
$name    = trim(strip_tags($_POST['name']    ?? ''));
$email   = trim(strip_tags($_POST['email']   ?? ''));
$message = trim(strip_tags($_POST['message'] ?? ''));

if ($name === '' || $email === '') {
    echo json_encode(['ok' => false, 'message' => 'Please fill in both fields.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// ── Send email ────────────────────────────────────────────────────────────────
$subject = ($company ? $company . ' — ' : '') . 'New contact from ' . $name;
$body    = "Name:    $name\r\nEmail:   $email\r\n"
         . ($message !== '' ? "\r\nMessage:\r\n$message\r\n" : '');
$headers = implode("\r\n", [
    'From: ' . $name . ' <' . $email . '>',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . PHP_VERSION,
]);

$sent = @mail($toEmail, $subject, $body, $headers);

if ($sent) {
    echo json_encode(['ok' => true, 'message' => "Thanks, $name! We'll be in touch soon."]);
} else {
    echo json_encode(['ok' => false, 'message' => 'Message could not be sent. Please email us directly.']);
}
