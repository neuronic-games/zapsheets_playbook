<?php
/**
 * collabGetBio.php — fetch bio + name for a signed-in collab user
 *
 * POST params:
 *   id    — sheet ID
 *   email — user email
 *
 * Returns JSON:
 *   { ok: true, bio: { name?, image?, description?, skills?, location?,
 *                      discord?, phone?, payment?, notes? } }
 *   { error: string }
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

$email   = strtolower(trim($_POST['email'] ?? ''));
$sheetId = trim($_POST['id']    ?? '');

if (!$email || !$sheetId) {
    echo json_encode(['error' => 'Missing required fields.']);
    exit;
}

$sheetsRoot = dirname(__DIR__) . '/sheets';
$bio = [];

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
            $fields = [
                'description' => 'Description', 'skills'  => 'Skills',
                'location'    => 'Location',    'discord' => 'Discord',
                'phone'       => 'Phone',       'payment' => 'Payment',
                'notes'       => 'Notes',
            ];
            foreach ($fields as $key => $col) {
                $val = ltrim(trim($b[$col] ?? ''), "'");
                if ($val) $bio[$key] = $val;
            }
            break;
        }
    }
}

echo json_encode(['ok' => true, 'bio' => $bio]);
?>
