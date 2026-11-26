<?php
/**
 * cardboardShare.php — the sheet owner's view-only share link (see cardboardView.php).
 *
 * POST id (the sheet), action get | create | stop, cbt (account token)
 *   → {ok, url}   (url '' when not shared)
 * Only the person who set the sheet up (its owner) manages the link.
 */
error_reporting(0);
header('Content-Type: application/json');
require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/gameboardAuth.php';
require_once __DIR__ . '/cardboardView.php';

function shOut(array $a): void { echo json_encode($a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; }

$sheet  = trim((string) ($_POST['id'] ?? ''));
$action = (string) ($_POST['action'] ?? 'get');
if (!preg_match('/^[A-Za-z0-9_\-]{20,128}$/', $sheet)) shOut(['error' => 'Invalid sheet id']);

$p = cbReadToken(cbRequestToken());
if (!$p) shOut(['error' => 'Sign in with your GameBoard account first.', 'need_auth' => true]);
if (($p['x'] ?? 0) < time()) shOut(['error' => 'Your sign-in needs refreshing.', 'need_auth' => true, 'refresh' => true]);
if (!in_array($sheet, (array) ($p['sh'] ?? []), true)) shOut(['error' => 'Only the person who set this sheet up can share it.']);

$base = function_exists('gbLinkBase') ? gbLinkBase() : '/';
$url  = function ($t) use ($base) { return $t !== '' ? $base . 'v-' . $t . '/cardboard' : ''; };

if ($action === 'create') shOut(['ok' => true, 'url' => $url(cbViewCreate($sheet))]);
if ($action === 'stop')   { cbViewStop($sheet); shOut(['ok' => true, 'url' => '']); }
shOut(['ok' => true, 'url' => $url(cbViewToken($sheet))]);
