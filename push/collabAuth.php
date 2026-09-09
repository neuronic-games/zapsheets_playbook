<?php
/**
 * collabAuth.php — sign in / sign up for collab (shared) view
 *
 * POST params:
 *   email    — user email
 *   password — plain-text password (hashed before storage)
 *   id       — Google Spreadsheet ID (used to load bio)
 *
 * Accounts are stored globally in sheets/accounts.json:
 *   { "email@example.com": { "hash": "$2y$..." }, ... }
 *
 * Returns JSON:
 *   { ok: true, new: bool, email: string, bio: { name?, image?, description?, ... } }
 *   { error: string }
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

$email    = strtolower(trim($_POST['email']    ?? ''));
$password = trim($_POST['password']            ?? '');
$sheetId  = trim($_POST['id']                  ?? '');

if (!$email || !$password) {
    echo json_encode(['error' => 'Email and password are required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}
if (strlen($password) < 4) {
    echo json_encode(['error' => 'Password must be at least 4 characters.']);
    exit;
}

$sheetsRoot   = dirname(__DIR__) . '/sheets';
$accountsFile = $sheetsRoot . '/accounts.json';

// Load or init accounts map
$accounts = [];
if (file_exists($accountsFile)) {
    $accounts = json_decode(file_get_contents($accountsFile), true) ?: [];
}

$isNew = !isset($accounts[$email]);

if ($isNew) {
    // Register new account
    $accounts[$email] = ['hash' => password_hash($password, PASSWORD_DEFAULT)];
    file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} else {
    // Verify existing account
    if (!password_verify($password, $accounts[$email]['hash'] ?? '')) {
        echo json_encode(['error' => 'Incorrect password. Try again.']);
        exit;
    }
}

// Build bio from sheet data
$bio = [];

if ($sheetId) {
    // Name from people.json
    $peopleFile = $sheetsRoot . '/' . $sheetId . '/people.json';
    if (file_exists($peopleFile)) {
        $people = json_decode(file_get_contents($peopleFile), true) ?: [];
        foreach ($people as $p) {
            $pEmail = strtolower(ltrim(trim($p['Email'] ?? ''), "'"));
            if ($pEmail === $email) {
                $name = ltrim(trim($p['Name'] ?? ''), "'");
                if ($name) $bio['name'] = $name;
                break;
            }
        }
    }

    // Extended bio from bios.json
    $biosFile = $sheetsRoot . '/' . $sheetId . '/bios.json';
    if (file_exists($biosFile)) {
        $bios = json_decode(file_get_contents($biosFile), true) ?: [];
        foreach ($bios as $b) {
            $bEmail = strtolower(ltrim(trim($b['Email'] ?? ''), "'"));
            if ($bEmail === $email) {
                $rawImg = trim($b['Image'] ?? '');
                if (preg_match('/^=IMAGE\("([^"]*)"\)$/i', $rawImg, $im)) {
                    $bio['image'] = $im[1];
                } elseif ($rawImg) {
                    $bio['image'] = ltrim($rawImg, "'");
                }
                $fields = ['description' => 'Description', 'skills' => 'Skills',
                           'location' => 'Location', 'discord' => 'Discord',
                           'phone' => 'Phone', 'payment' => 'Payment', 'notes' => 'Notes'];
                foreach ($fields as $key => $col) {
                    $val = ltrim(trim($b[$col] ?? ''), "'");
                    if ($val) $bio[$key] = $val;
                }
                break;
            }
        }
    }
}

echo json_encode(['ok' => true, 'new' => $isNew, 'email' => $email, 'bio' => $bio]);
?>
