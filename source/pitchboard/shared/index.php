<?php
/**
 * PitchBoard shared view — read-only game card with same layout as the dashboard.
 * URL:       /pitchboard/share/{24-char-hex-hash}
 * JSON mode: /pitchboard/share/{hash}?data  (importable snapshot)
 */
error_reporting(0);

$_bpFile = __DIR__ . '/../../../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rp_stripped = ($_bp !== '' && str_starts_with($_rp, $_bp))
    ? substr($_rp, strlen($_bp)) : $_rp;

preg_match('#^/pitchboard/share/([a-f0-9]{24})/?$#', $_rp_stripped, $_m);
$_token = $_m[1] ?? '';

$_viewFile = __DIR__ . '/../../../shares/pitch-collab-readonly/' . $_token . '.json';
if (!$_token || !file_exists($_viewFile)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not found</title></head>'
       . '<body style="font-family:sans-serif;padding:3rem;text-align:center">'
       . '<h2>Share link not found or expired.</h2></body></html>';
    exit;
}

$_meta    = json_decode(file_get_contents($_viewFile), true) ?: [];
$_sheetId = $_meta['sheet_id'] ?? '';
$_gameName= $_meta['game']     ?? '';
$_sharer  = trim($_meta['sharer'] ?? '');

if (!$_sheetId || !$_gameName) { http_response_code(404); exit; }

// ── Load data ────────────────────────────────────────────────────────────────

$_pitchesFile = __DIR__ . '/../../../sheets/' . $_sheetId . '/pitches.json';
$_allPitches  = file_exists($_pitchesFile)
    ? (json_decode(file_get_contents($_pitchesFile), true) ?: [])
    : [];

$_pitches = array_values(array_filter($_allPitches, function($r) use ($_gameName) {
    return isset($r['Game']) && $r['Game'] === $_gameName;
}));

$_gamesFile = __DIR__ . '/../../../sheets/' . $_sheetId . '/games.json';
$_gameInfo  = [];
if (file_exists($_gamesFile)) {
    foreach (json_decode(file_get_contents($_gamesFile), true) ?: [] as $g) {
        if (($g['Name'] ?? '') === $_gameName) { $_gameInfo = $g; break; }
    }
}

$_d1 = trim($_gameInfo['Designer1'] ?? '');
$_d2 = trim($_gameInfo['Designer2'] ?? '');
$_designers = implode(', ', array_filter([$_d1, $_d2]));
// Fall back to the pitchboard owner's name (from settings.json) for old token files
if ($_sharer === '') {
    $_settingsFile = __DIR__ . '/../../../sheets/' . $_sheetId . '/settings.json';
    if (file_exists($_settingsFile)) {
        $_settings = json_decode(file_get_contents($_settingsFile), true) ?: [];
        if (!empty($_settings[0])) {
            foreach (array_keys($_settings[0]) as $_sk) {
                if ($_sk !== 'My Name') { $_sharer = $_sk; break; }
            }
        }
    }
}

// ── PWA manifest mode (?manifest) ───────────────────────────────────────────
if (isset($_GET['manifest'])) {
    header('Content-Type: application/manifest+json');
    header('Cache-Control: public, max-age=86400');
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base    = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_bp;
    echo json_encode([
        'name'             => $_gameName . ' — PitchBoard',
        'short_name'       => $_gameName,
        'start_url'        => $_rp,
        'display'          => 'standalone',
        'background_color' => '#1a1a2e',
        'theme_color'      => '#1a1a2e',
        'icons'            => [
            ['src' => $base . '/images/pb_icon_192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => $base . '/images/pb_icon_512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// ── JSON / import mode (?data) ───────────────────────────────────────────────
if (isset($_GET['data'])) {
    header('Content-Type: application/json');
    $people = []; $seen = [];
    foreach ($_pitches as $p) {
        $n = trim($p['Contact'] ?? ''); $pub = trim($p['Publisher'] ?? '');
        if ($n && !isset($seen[$n])) { $seen[$n]=1; $people[]=['Name'=>$n,'Company'=>$pub,'Email'=>'']; }
    }
    foreach (['Designer1','Designer2','Designer3','Designer4'] as $f) {
        $n = trim($_gameInfo[$f] ?? '');
        if ($n && !isset($seen[$n])) { $seen[$n]=1; $people[]=['Name'=>$n,'Email'=>'','Company'=>'']; }
    }
    echo json_encode(['game'=>array_merge(['Name'=>$_gameName],$_gameInfo),'exported'=>date('Y-m-d'),'pitches'=>$_pitches,'people'=>$people]);
    exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function _ps_e(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

function _ps_field(array $info, array $keys): string {
    foreach ($keys as $k) {
        $v = trim($info[$k] ?? '');
        if ($v !== '') return $v;
    }
    return '';
}

function _ps_latest(array $entries): array {
    usort($entries, function($a,$b){
        return strtotime($b['Date']??'0') - strtotime($a['Date']??'0');
    });
    return $entries[0] ?? [];
}

function _ps_status_class(string $s): string {
    $sl = strtolower(trim($s));
    if ($sl==='signed')     return 'signed';
    if ($sl==='interested') return 'interested';
    if ($sl==='passed')     return 'passed';
    if ($sl==='gone cold')  return 'gone-cold';
    if ($sl==='returned')   return 'returned';
    if ($sl==='published')  return 'published';
    return 'pitched';
}

function _ps_age_tag(array $entries): string {
    $latest = _ps_latest($entries);
    $ls = strtolower($latest['Status'] ?? '');
    if ($ls !== 'pitched' && $ls !== 'interested') return '';
    $ints = array_filter($entries, function($e){ return strtolower($e['Status']??'')==='interested'; });
    if (!$ints) return '';
    $li = _ps_latest(array_values($ints));
    if (!($li['Date'] ?? '')) return '';
    $d = new DateTime($li['Date']); $now = new DateTime();
    $months = ($now->format('Y')-$d->format('Y'))*12 + ($now->format('n')-$d->format('n'));
    if ($months >= 6) return '<span class="badge badge-age-6mo">6mo+</span>';
    if ($months >= 3) return '<span class="badge badge-age-3mo">3mo+</span>';
    return '';
}

function _ps_entry_row(array $e): string {
    $sc      = _ps_status_class($e['Status'] ?? '');
    $contact = ($e['Contact'] && $e['Contact'] !== '(Unknown)') ? $e['Contact'] : '';
    $notes   = $e['Notes'] ?? '';
    $dataAttr = _ps_e(json_encode($e, JSON_UNESCAPED_UNICODE));
    return '<div class="entry-row" data-entry="' . $dataAttr . '" onclick="openEditDialog(this)">'
        . '<span class="entry-date">'    . _ps_e($e['Date']   ?? '') . '</span>'
        . '<span class="entry-contact">' . _ps_e($contact)           . '</span>'
        . '<span class="entry-event">'   . _ps_e($e['Event']  ?? '') . '</span>'
        . '<span class="entry-status badge badge-' . $sc . '">' . _ps_e($e['Status'] ?? '—') . '</span>'
        . '<span class="entry-notes">'   . _ps_e($notes)             . '</span>'
        . '</div>';
}

// ── Group pitches: Publisher → Contact ───────────────────────────────────────

$_byPub = [];
foreach ($_pitches as $p) {
    $pub = $p['Publisher'] ?: '(Unknown)';
    $con = $p['Contact']   ?: '(Unknown)';
    $_byPub[$pub][$con][] = $p;
}
ksort($_byPub);

// Build publisher chunks, separating active from collapsed
$_activePubs    = '';
$_collapsedPubs = '';
$_collapsedCount= 0;
$_activeAltIdx  = 0;
$_collapsedAltIdx = 0;

foreach ($_byPub as $pub => $contacts) {
    // Flatten all entries for this publisher
    $allEntries = [];
    foreach ($contacts as $con => $rows) {
        foreach ($rows as $r) { $allEntries[] = $r; }
    }
    // Sort entries newest first
    usort($allEntries, function($a,$b){
        return strtotime($b['Date']??'0') - strtotime($a['Date']??'0');
    });

    $latest    = _ps_latest($allEntries);
    $pubStatus = strtolower($latest['Status'] ?? '');
    $isCollapsed = ($pubStatus === 'passed' || $pubStatus === 'gone cold');

    // Publisher status badge
    $badge = '';
    if ($pubStatus === 'passed')      $badge = '<span class="badge badge-passed" style="margin-right:.75rem">Passed</span>';
    elseif ($pubStatus === 'gone cold') $badge = '<span class="badge badge-gone-cold" style="margin-right:.75rem">Gone Cold</span>';
    elseif ($pubStatus === 'signed')    $badge = '<span class="badge badge-signed" style="margin-right:.75rem">Signed</span>';
    elseif ($pubStatus === 'interested') $badge = '<span class="badge badge-interested" style="margin-right:.75rem">Interested</span>';
    elseif ($pubStatus === 'returned')  $badge = '<span class="badge badge-returned" style="margin-right:.75rem">Returned</span>';
    elseif ($pubStatus === 'published') $badge = '<span class="badge badge-published" style="margin-right:.75rem">Published</span>';
    else                                $badge = '<span class="badge badge-pitched" style="margin-right:.75rem">Pitched</span>';

    $ageTag      = $isCollapsed ? '' : _ps_age_tag($allEntries);
    $headerColor = $isCollapsed ? 'color:#aaa;' : 'color:#333;';
    $altIdx      = $isCollapsed ? $_collapsedAltIdx++ : $_activeAltIdx++;
    $altClass    = ($altIdx % 2 === 1) ? ' pub-alt' : '';

    $entryHtml = '';
    foreach ($allEntries as $e) { $entryHtml .= _ps_entry_row($e); }

    $pubJs  = 'openAddDialogForPub(' . json_encode($pub) . ',event)';
    $pubAddBtn = '<button class="add-entry-btn" onclick="' . _ps_e($pubJs) . '">+ Pitch</button>';
    $chunk = '<div class="sub-group' . $altClass . '">'
        . '<div class="sub-label pub-passed-header" onclick="togglePubPassed(this)" style="' . $headerColor . 'font-size:.75rem">'
        .   '<span class="pub-title-group"><span>' . _ps_e($pub) . '</span>' . $pubAddBtn . '</span>'
        .   $ageTag . $badge
        .   '<span class="pub-expand-chevron">▶</span>'
        . '</div>'
        . '<div class="pub-body-wrap"><div class="pub-passed-body">'
        .   $entryHtml
        . '</div></div>'
        . '</div>';

    if ($isCollapsed) {
        $_collapsedPubs .= $chunk;
        $_collapsedCount++;
    } else {
        $_activePubs .= $chunk;
    }
}

// ── Combo data for JS ────────────────────────────────────────────────────────
$_pubList = array_values(array_keys($_byPub));
$_contactsByPub = [];
foreach ($_byPub as $_p => $_cs) {
    $_names = array_values(array_filter(array_keys($_cs), function($c){ return $c !== '(Unknown)'; }));
    if ($_names) $_contactsByPub[$_p] = $_names;
}

// ── URLs ─────────────────────────────────────────────────────────────────────

$_scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_importUrl = $_scheme . '://' . $_SERVER['HTTP_HOST'] . $_rp . '?data';
$_pbUrl     = $_scheme . '://' . $_SERVER['HTTP_HOST'] . $_bp . '/pitchboard';
$_base      = $_bp . '/';

// ── Game links for footer ─────────────────────────────────────────────────────
$_rulesUrl     = _ps_field($_gameInfo, ['Rules', 'Rules URL', 'Rules Link', 'Link Rules']);
$_playUrl      = _ps_field($_gameInfo, ['Play', 'Play URL', 'Play Link', 'Link Play']);
$_printUrl     = _ps_field($_gameInfo, ['Print', 'Print URL', 'Print Link', 'Link Print']);
$_sellsheetUrl = _ps_field($_gameInfo, ['Sellsheet URL', 'Sellsheet', 'Sell Sheet URL', 'Sell Sheet', 'Link Sellsheet']);
$_videoUrl     = _ps_field($_gameInfo, ['Video', 'Video URL', 'Video Link', 'Link Video', 'YouTube', 'YouTube URL']);

// Game Page: check whether a token file has been generated for this game
$_gameToken     = substr(md5($_sheetId . '|game|' . $_gameName), 0, 24);
$_gameTokenFile = __DIR__ . '/../../../shares/pitch-game-view/' . $_gameToken . '.json';
$_gamePageUrl   = file_exists($_gameTokenFile)
    ? $_scheme . '://' . $_SERVER['HTTP_HOST'] . $_bp . '/game/' . $_gameToken
    : '';

$_hasFooter = $_rulesUrl || $_playUrl || $_printUrl || $_sellsheetUrl || $_videoUrl || $_gamePageUrl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= _ps_e($_gameName) ?> — Pitch Overview</title>
<base href="<?= _ps_e($_base) ?>" />
<link rel="manifest" href="<?= _ps_e($_rp) ?>?manifest" />
<meta name="theme-color" content="#1a1a2e" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="<?= _ps_e($_gameName) ?>" />
<link rel="icon" type="image/png" sizes="192x192" href="<?= _ps_e($_base) ?>images/pb_icon_192.png" />
<link rel="apple-touch-icon" sizes="180x180" href="<?= _ps_e($_base) ?>images/pb_icon_180.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2') format('woff2'),url('fonts/DINBlack.ttf'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf'); }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'DINRegular', Arial, sans-serif;
  background: #f3f2ef;
  color: #111;
  min-height: 100vh;
}

/* ── Top bar ── */
.top-bar {
  position: sticky; top: 0; z-index: 100;
  background: #1a1a2e; color: #fff;
  padding: .65rem 1.25rem;
  display: flex; align-items: center; gap: .75rem;
  box-shadow: 0 2px 8px rgba(0,0,0,.18);
}
.top-bar-logo {
  font-family: 'DINBlack', sans-serif;
  font-size: 1rem; letter-spacing: .03em;
  color: #fff; text-decoration: none;
}
.top-bar-logo .pb-pitch { color: #A8C8F0; }
.top-bar-logo .pb-board { color: #FF8A80; }
.top-bar-sep  { color: rgba(255,255,255,.3); }
.top-bar-game { font-family: 'DINBlack', sans-serif; font-size: .88rem; color: rgba(255,255,255,.85); letter-spacing: .03em; }
.top-bar-readonly {
  margin-left: auto;
  font-family: 'DINBlack', sans-serif;
  font-size: .62rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;
  color: rgba(255,255,255,.45); background: rgba(255,255,255,.1);
  padding: .22rem .6rem; border-radius: 999px; white-space: nowrap;
}

/* ── Content ── */
.content { padding: 1rem 1.25rem 3rem; max-width: 900px; margin: 0 auto; }

/* ── Card ── */
.card {
  background: #fff; border-radius: 10px;
  box-shadow: 0 1px 4px rgba(0,0,0,.08);
  margin-bottom: .9rem; overflow: hidden;
}
.card-header {
  background: #1a1a2e; color: #fff;
  padding: .55rem 1rem;
  display: flex; align-items: center; gap: .5rem;
  user-select: none;
}
.card-title {
  font-family: 'DINBlack', sans-serif; font-size: .9rem;
  letter-spacing: .03em; flex: 1; min-width: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.card-badges { display: flex; align-items: center; gap: .35rem; flex-shrink: 0; }
.card-body { padding: 0; }

/* ── Sub-group / publisher row ── */
.sub-group { border-top: 1px solid #f0f0f0; }
.sub-group:first-child { border-top: none; }
.sub-label {
  font-family: 'DINBlack', sans-serif; font-size: .72rem;
  color: #666; text-transform: uppercase; letter-spacing: .05em;
  padding: .45rem 1rem .2rem 1.6rem;
  display: flex; align-items: center; gap: .5rem;
  cursor: pointer; user-select: none;
}
.sub-label:hover { background: #fafafa; }
.sub-group.pub-alt > .sub-label { background: #f4f5fb; }
.sub-group.pub-alt > .sub-label:hover { background: #eaebf5; }
.sub-group.pub-alt .entry-row { background: #f4f5fb; }
.sub-group.pub-alt .entry-row:hover { background: #eaebf5; }

.pub-title-group { display: flex; align-items: center; gap: .35rem; flex: 1; min-width: 0; }
.pub-title-group > span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
.pub-expand-chevron {
  font-size: .58rem; color: #bbb; margin-left: .2rem; flex-shrink: 0;
  display: inline-block; transition: transform .18s ease;
}

/* Collapsed/expanded body */
.pub-body-wrap {
  display: grid; grid-template-rows: 0fr;
  transition: grid-template-rows .18s ease;
}
.pub-body-wrap.open { grid-template-rows: 1fr; }
.pub-passed-body { overflow: hidden; min-height: 0; }

/* ── Entry row ── */
.entry-row {
  display: grid;
  grid-template-columns: 86px 130px 80px auto 1fr;
  align-items: center; gap: .5rem;
  padding: .32rem 1rem .32rem 2rem;
  border-top: 1px solid #f5f5f5;
  font-size: .8rem;
  cursor: pointer;
  transition: background .12s;
}
.entry-row:hover { background: #f0f1fa !important; }
.entry-date    { color: #777; white-space: nowrap; }
.entry-contact { color: #555; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.entry-event   { color: #999; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.entry-status  { justify-self: start; }
.entry-notes   { color: #444; line-height: 1.42; min-width: 0; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }

/* ── Badges ── */
.badge {
  font-family: 'DINBlack', sans-serif; font-size: .65rem;
  text-transform: uppercase; letter-spacing: .06em;
  padding: .38rem .65rem; border-radius: 999px; white-space: nowrap;
  line-height: 1; display: inline-flex; align-items: center;
}
.badge-interested { background: #dcfce7; color: #166534; }
.badge-passed     { background: #fee2e2; color: #991b1b; }
.badge-pitched    { background: #e2e8f0; color: #334155; }
.badge-signed     { background: #7c3aed; color: #fff; }
.badge-published  { background: #0369a1; color: #fff; }
.badge-returned   { background: #f97316; color: #fff; }
.badge-gone-cold  { background: #dbeafe; color: #1e40af; }
.badge-age-6mo    { background: #ef4444; color: #fff; }
.badge-age-3mo    { background: #f59e0b; color: #fff; }

/* ── Collapsed publishers ── */
.pub-show-all-btn {
  display: block; width: 100%; background: none; border: none; border-top: 1px solid #eee;
  padding: .45rem 1rem; cursor: pointer; text-align: center;
  font-family: 'DINBlack', sans-serif; font-size: .65rem;
  text-transform: uppercase; letter-spacing: .06em; color: #aaa;
}
.pub-show-all-btn:hover { color: #555; }

/* ── Empty ── */
.empty-pub { padding: .75rem 1rem; color: #aaa; font-size: .8rem; font-style: italic; }

/* ── Shared card footer ── */
.shared-footer {
  display: flex; gap: .45rem; flex-wrap: wrap; align-items: center;
  padding: .5rem 1rem .55rem;
  background: #2a2006;
  border-radius: 0 0 10px 10px;
}
.footer-btn {
  display: inline-flex; align-items: center;
  padding: .32rem .85rem; border-radius: 999px;
  font-family: 'DINBlack', sans-serif; font-size: .65rem;
  text-transform: uppercase; letter-spacing: .05em;
  text-decoration: none; cursor: pointer;
  border: none; transition: opacity .15s;
  background: #f5c518; color: #1a1a2e;
}
.footer-btn:hover { opacity: .8; }

/* ── CTA section ── */
.cta-section {
  margin-top: 1.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1.4rem 1.5rem 1.2rem;
  background: #fff;
  box-shadow: 0 1px 4px rgba(0,0,0,.08);
  text-align: center;
}
.cta-shared-by { font-size: .85rem; color: #555; margin-bottom: .75rem; }
.cta-title { font-family: 'DINBlack', sans-serif; font-size: .9rem; color: #1a1a2e; margin-bottom: .3rem; text-transform: uppercase; letter-spacing: .04em; }
.cta-sub   { font-size: .8rem; color: #888; margin-bottom: 1rem; line-height: 1.5; }
.cta-buttons { display: flex; gap: .65rem; justify-content: center; flex-wrap: wrap; margin-bottom: .8rem; }
.cta-btn {
  display: inline-block; padding: .5rem 1.1rem; border-radius: 999px;
  font-family: 'DINBlack', sans-serif; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em;
  cursor: pointer; border: none; text-decoration: none; transition: opacity .15s, background .15s;
}
.cta-btn:hover { opacity: .85; }
.cta-btn-primary   { background: #1a1a2e; color: #fff; }
.cta-btn-secondary { background: #e8e8f0; color: #1a1a2e; }
.cta-btn-secondary.copied { background: #dcfce7; color: #166534; }
.cta-hint { font-size: .74rem; color: #aaa; line-height: 1.5; }
.cta-hint code { background: #f0f0f8; padding: .1rem .35rem; border-radius: 4px; font-size: .71rem; color: #555; }

@media (max-width: 540px) {
  .entry-row { grid-template-columns: 74px 1fr auto; }
  .entry-contact, .entry-event, .entry-notes { display: none; }
}

/* ── Game sub-bar (dark navy, below card header) ── */
.game-sub-bar { background: #1a1a2e; }
.game-links { padding: .5rem 1rem .45rem; }
.game-links-meta {
  display: flex; gap: .35rem; flex-wrap: wrap; align-items: center; min-width: 0;
}
.game-links-designers {
  font-family: 'DINRegular', sans-serif; font-size: .74rem;
  color: rgba(255,255,255,.55); white-space: nowrap; flex-shrink: 0;
}
.game-action-btns {
  display: flex; flex-wrap: nowrap; gap: .35rem; align-items: center;
  overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none;
}
.game-action-btns::-webkit-scrollbar { display: none; }
.game-action-btn {
  font-family: 'DINBlack', sans-serif; font-size: .6rem;
  text-transform: uppercase; letter-spacing: .06em;
  background: rgba(255,196,76,.14); color: #ffd166;
  border: none; border-radius: 999px;
  padding: .38rem .65rem; cursor: pointer; white-space: nowrap; flex-shrink: 0;
  transition: background .15s, color .15s;
}
.game-action-btn:hover { background: rgba(255,196,76,.26); color: #ffe099; }

/* ── Inline publisher + Pitch button ── */
.add-entry-btn {
  display: inline-flex; align-items: center;
  font-family: 'DINBlack', sans-serif; font-size: .62rem;
  text-transform: uppercase; letter-spacing: .05em;
  color: #1a1a2e; background: #e8e8f0; border: none;
  border-radius: 999px; padding: .12rem .5rem;
  cursor: pointer; white-space: nowrap; transition: background .15s, color .15s;
}
.add-entry-btn:hover { background: #1a1a2e; color: #fff; }

/* ── Collab dialogs ── */
.collab-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.5); z-index: 200;
  align-items: center; justify-content: center;
  padding: 1rem;
}
.collab-overlay.open { display: flex; }
.collab-dialog {
  background: #fff; border-radius: 12px; padding: 1.5rem;
  width: 100%; max-width: 480px;
  box-shadow: 0 8px 40px rgba(0,0,0,.25);
  max-height: 90vh; overflow-y: auto;
}
.collab-dialog-title {
  font-family: 'DINBlack', sans-serif; font-size: .95rem;
  color: #1a1a2e; letter-spacing: .03em;
  margin-bottom: 1.1rem; text-transform: uppercase;
}
.collab-field { margin-bottom: .85rem; }
.collab-label {
  display: block;
  font-family: 'DINBlack', sans-serif; font-size: .62rem;
  text-transform: uppercase; letter-spacing: .05em;
  color: #888; margin-bottom: .28rem;
}
.collab-input, .collab-select, .collab-textarea {
  display: block; width: 100%; padding: .5rem .7rem;
  font-family: 'DINRegular', Arial, sans-serif; font-size: .88rem; color: #111;
  border: 1.5px solid #d8d8e0; border-radius: 7px; outline: none;
  background: #fafafa; transition: border-color .15s;
}
.collab-input:focus, .collab-select:focus, .collab-textarea:focus {
  border-color: #1a1a2e; background: #fff;
}
.collab-textarea { resize: vertical; min-height: 60px; }
.collab-input[readonly], .collab-input:disabled { color: #999; background: #f4f4f8; cursor: default; }

/* ── Combo drop ── */
.combo-wrap { position: relative; }
.combo-drop {
  display: none; position: absolute; top: calc(100% + 2px); left: 0; right: 0; z-index: 9999;
  background: #fff; border: 1px solid #ccc; border-radius: 6px;
  max-height: 160px; overflow-y: auto;
  box-shadow: 0 4px 12px rgba(0,0,0,.13);
}
.combo-drop.open { display: block; }
.combo-opt {
  padding: .4rem .7rem; font-family: 'DINRegular', sans-serif; font-size: .83rem;
  cursor: pointer; color: #111; text-transform: none; letter-spacing: normal;
}
.combo-opt:hover, .combo-opt.active { background: #1a1a2e; color: #fff; }
.combo-sep { height: 1px; background: #e0dbd3; margin: .25rem .5rem; pointer-events: none; }
.collab-dialog-actions {
  display: flex; gap: .65rem; justify-content: flex-end; margin-top: 1.1rem;
}
.collab-btn {
  display: inline-block; padding: .48rem 1.1rem; border-radius: 999px;
  font-family: 'DINBlack', sans-serif; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em;
  cursor: pointer; border: none; transition: opacity .15s;
}
.collab-btn:hover:not(:disabled) { opacity: .8; }
.collab-btn:disabled { opacity: .4; cursor: default; }
.collab-btn-primary { background: #1a1a2e; color: #fff; }
.collab-btn-cancel  { background: #e8e8f0; color: #1a1a2e; }
.collab-err { font-size: .75rem; color: #dc2626; margin-top: .6rem; display: none; }
</style>
</head>
<body>

<div class="top-bar">
  <a class="top-bar-logo" href="<?= _ps_e($_pbUrl) ?>"><span class="pb-pitch">Pitch</span><span class="pb-board">Board</span></a>
  <span class="top-bar-sep">›</span>
  <span class="top-bar-game"><?= _ps_e($_gameName) ?></span>
  <span class="top-bar-readonly">Collab</span>
</div>

<div class="content">

  <?php
  $_ol = _ps_latest($_pitches);
  $_os = $_ol['Status'] ?? '';
  $_ob = $_os ? '<span class="badge badge-' . _ps_status_class($_os) . '">' . _ps_e($_os) . '</span>' : '';
  ?>
  <div class="card">
    <div class="card-header">
      <span class="card-title"><?= _ps_e($_gameName) ?></span>
      <?php if ($_ob): ?>
        <div class="card-badges"><?= $_ob ?></div>
      <?php endif ?>
    </div>
    <div class="game-sub-bar">
      <div class="game-links">
        <div class="game-links-meta">
          <?php if ($_designers): ?>
            <span class="game-links-designers"><?= _ps_e($_designers) ?></span>
          <?php endif ?>
          <div class="game-action-btns">
            <button class="game-action-btn" onclick="openAddDialog()">New Pitch</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card-body">
      <?php if (empty($_byPub)): ?>
        <div class="empty-pub">No pitches yet.</div>
      <?php else: ?>
        <?= $_activePubs ?>
        <?php if ($_collapsedPubs): ?>
          <div class="pub-collapsed-wrap" style="display:none"><?= $_collapsedPubs ?></div>
          <button class="pub-show-all-btn" onclick="toggleCollapsedPubs(this)">
            Show <?= $_collapsedCount ?> passed / gone cold
          </button>
        <?php endif ?>
      <?php endif ?>
    </div>
    <?php if ($_hasFooter): ?>
    <div class="shared-footer">
      <?php if ($_rulesUrl): ?>
        <a class="footer-btn" href="<?= _ps_e($_rulesUrl) ?>" target="_blank">Rules</a>
      <?php endif ?>
      <?php if ($_printUrl): ?>
        <a class="footer-btn" href="<?= _ps_e($_printUrl) ?>" target="_blank">Print</a>
      <?php endif ?>
      <?php if ($_playUrl): ?>
        <a class="footer-btn" href="<?= _ps_e($_playUrl) ?>" target="_blank">Play</a>
      <?php endif ?>
      <?php if ($_sellsheetUrl): ?>
        <a class="footer-btn" href="<?= _ps_e($_sellsheetUrl) ?>" target="_blank">Sellsheet</a>
      <?php endif ?>
      <?php if ($_videoUrl): ?>
        <a class="footer-btn" href="<?= _ps_e($_videoUrl) ?>" target="_blank">Video</a>
      <?php endif ?>
      <?php if ($_gamePageUrl): ?>
        <a class="footer-btn" href="<?= _ps_e($_gamePageUrl) ?>">Page</a>
      <?php endif ?>
    </div>
    <?php endif ?>
  </div>

  <!-- ── CTA ── -->
  <div class="cta-section">
    <?php if ($_sharer !== ''): ?>
    <div class="cta-shared-by"><?= _ps_e($_sharer) ?> shared &ldquo;<?= _ps_e($_gameName) ?>&rdquo; with you.</div>
    <?php endif ?>
    <div class="cta-title">Track your own pitches</div>
    <div class="cta-sub">PitchBoard helps board game designers manage publisher relationships and pitch history.</div>
    <div class="cta-buttons">
      <a class="cta-btn cta-btn-primary" href="<?= _ps_e($_pbUrl) ?>" target="_blank">Get PitchBoard →</a>
      <button class="cta-btn cta-btn-secondary" id="importBtn" onclick="copyImportLink()">Import this data</button>
    </div>
    <p class="cta-hint" id="importHint">Already have PitchBoard? Click Import, then go to <code>Menu → Import</code> and paste the link.</p>
  </div>

  <!-- ── Add Pitch dialog ── -->
  <div class="collab-overlay" id="addOverlay" onclick="if(event.target===this)closeAddDialog()">
    <div class="collab-dialog">
      <div class="collab-dialog-title">Add Pitch</div>
      <div class="collab-field">
        <label class="collab-label">Publisher *</label>
        <div class="combo-wrap">
          <input class="collab-input" id="addPubInput" type="text" placeholder="Publisher name" autocomplete="off" />
          <div class="combo-drop" id="addPubDrop"></div>
        </div>
      </div>
      <div class="collab-field">
        <label class="collab-label">Contact</label>
        <div class="combo-wrap">
          <input class="collab-input" id="addContactInput" type="text" placeholder="Contact name" autocomplete="off" />
          <div class="combo-drop" id="addContactDrop"></div>
        </div>
      </div>
      <div class="collab-field">
        <label class="collab-label">Date *</label>
        <input class="collab-input" id="addDateInput" type="date" />
      </div>
      <div class="collab-field">
        <label class="collab-label">Event</label>
        <input class="collab-input" id="addEventInput" type="text" placeholder="e.g. Gen Con, email, etc." />
      </div>
      <div class="collab-field">
        <label class="collab-label">Status</label>
        <select class="collab-select" id="addStatusSel">
          <option>Pitched</option>
          <option>Interested</option>
          <option>Passed</option>
          <option>Gone Cold</option>
          <option>Signed</option>
          <option>Published</option>
          <option>Returned</option>
        </select>
      </div>
      <div class="collab-field">
        <label class="collab-label">Notes</label>
        <textarea class="collab-textarea" id="addNotesInput" placeholder="Optional notes…"></textarea>
      </div>
      <div class="collab-err" id="addErr"></div>
      <div class="collab-dialog-actions">
        <button class="collab-btn collab-btn-cancel" onclick="closeAddDialog()">Cancel</button>
        <button class="collab-btn collab-btn-primary" id="addSubmitBtn" onclick="submitAdd()">Add Pitch</button>
      </div>
    </div>
  </div>

  <!-- ── Edit Pitch dialog ── -->
  <div class="collab-overlay" id="editOverlay" onclick="if(event.target===this)closeEditDialog()">
    <div class="collab-dialog">
      <div class="collab-dialog-title">Edit Pitch</div>
      <div class="collab-field">
        <label class="collab-label">Publisher</label>
        <input class="collab-input" id="editPubDisplay" type="text" readonly />
      </div>
      <div class="collab-field">
        <label class="collab-label">Contact</label>
        <input class="collab-input" id="editContactInput" type="text" placeholder="Contact name" />
      </div>
      <div class="collab-field">
        <label class="collab-label">Date *</label>
        <input class="collab-input" id="editDateInput" type="date" />
      </div>
      <div class="collab-field">
        <label class="collab-label">Event</label>
        <input class="collab-input" id="editEventInput" type="text" placeholder="e.g. Gen Con, email, etc." />
      </div>
      <div class="collab-field">
        <label class="collab-label">Status</label>
        <select class="collab-select" id="editStatusSel">
          <option>Pitched</option>
          <option>Interested</option>
          <option>Passed</option>
          <option>Gone Cold</option>
          <option>Signed</option>
          <option>Published</option>
          <option>Returned</option>
        </select>
      </div>
      <div class="collab-field">
        <label class="collab-label">Notes</label>
        <textarea class="collab-textarea" id="editNotesInput" placeholder="Optional notes…"></textarea>
      </div>
      <div class="collab-err" id="editErr"></div>
      <div class="collab-dialog-actions">
        <button class="collab-btn collab-btn-cancel" onclick="closeEditDialog()">Cancel</button>
        <button class="collab-btn collab-btn-primary" id="editSubmitBtn" onclick="submitEdit()">Save</button>
      </div>
    </div>
  </div>

</div><!-- .content -->

<script>
var _importUrl      = <?= json_encode($_importUrl) ?>;
var _base           = <?= json_encode($_base) ?>;
var _sheetId        = <?= json_encode($_sheetId) ?>;
var _gameName       = <?= json_encode($_gameName) ?>;
var _pubList        = <?= json_encode($_pubList, JSON_UNESCAPED_UNICODE) ?>;
var _contactsByPub  = <?= json_encode($_contactsByPub, JSON_UNESCAPED_UNICODE) ?>;

// ── Combo engine ─────────────────────────────────────────────────────────────
function _comboInit(inputId, dropId, getItems, onSelect) {
  var inp  = document.getElementById(inputId);
  var drop = document.getElementById(dropId);
  if (!inp || !drop) return;
  var _ai = -1;
  function renderDrop(q) {
    if (inp.disabled) return;
    if (q === undefined) q = inp.value.trim().toLowerCase();
    var allItems = getItems();
    var items;
    if (q) {
      items = allItems.filter(function(s){ return s !== '---' && s.toLowerCase().indexOf(q) !== -1; });
    } else {
      items = allItems.slice();
      while (items.length && items[0] === '---') items.shift();
      while (items.length && items[items.length-1] === '---') items.pop();
    }
    if (!items.length) { closeDrop(); return; }
    _ai = -1; drop.innerHTML = '';
    items.forEach(function(item) {
      var div = document.createElement('div');
      if (item === '---') { div.className = 'combo-sep'; }
      else {
        div.className = 'combo-opt';
        div.textContent = item;
        div.addEventListener('mousedown', function(e) {
          e.preventDefault();
          inp.value = item;
          closeDrop();
          if (onSelect) onSelect(item);
        });
      }
      drop.appendChild(div);
    });
    drop.classList.add('open');
  }
  function closeDrop() { drop.classList.remove('open'); _ai = -1; }
  function moveActive(dir) {
    var opts = drop.querySelectorAll('.combo-opt');
    if (!opts.length) return;
    _ai = Math.max(0, Math.min(opts.length-1, _ai+dir));
    opts.forEach(function(o,i){ o.classList.toggle('active', i===_ai); if(i===_ai) o.scrollIntoView({block:'nearest'}); });
  }
  inp.addEventListener('input',  function() { renderDrop(); });
  inp.addEventListener('focus',  function() { inp.select(); renderDrop(''); });
  inp.addEventListener('blur',   function() { setTimeout(closeDrop, 150); });
  inp.addEventListener('keydown', function(e) {
    if (e.key==='ArrowDown') { e.preventDefault(); if (!drop.classList.contains('open')) renderDrop(); else moveActive(1); }
    else if (e.key==='ArrowUp')  { e.preventDefault(); moveActive(-1); }
    else if (e.key==='Enter') {
      var opts = drop.querySelectorAll('.combo-opt');
      if (_ai>=0 && opts[_ai]) { e.preventDefault(); inp.value=opts[_ai].textContent; closeDrop(); if(onSelect) onSelect(inp.value); }
    } else if (e.key==='Escape') { closeDrop(); }
  });
}

var _addCombosReady = false;
function _setupAddCombos() {
  if (_addCombosReady) return;
  _addCombosReady = true;
  _comboInit('addPubInput', 'addPubDrop',
    function() { return _pubList; },
    function() { document.getElementById('addContactInput').value = ''; }
  );
  _comboInit('addContactInput', 'addContactDrop',
    function() {
      var pub = document.getElementById('addPubInput').value.trim();
      return _contactsByPub[pub] || [];
    }, null
  );
  var pubInp = document.getElementById('addPubInput');
  if (pubInp) pubInp.addEventListener('input', function() { document.getElementById('addContactInput').value = ''; });
}

function togglePubPassed(header) {
  var wrap    = header.nextElementSibling;
  var chevron = header.querySelector('.pub-expand-chevron');
  var isOpen  = wrap.classList.toggle('open');
  if (chevron) chevron.style.transform = isOpen ? 'rotate(90deg)' : 'rotate(0deg)';
}

function toggleCollapsedPubs(btn) {
  var wrap   = btn.previousElementSibling;
  var isOpen = wrap.style.display !== 'none';
  wrap.style.display = isOpen ? 'none' : '';
  var t = btn.textContent.trim();
  btn.textContent = isOpen ? t.replace(/^Hide/i, 'Show') : t.replace(/^Show/i, 'Hide');
}


function copyImportLink() {
  var btn  = document.getElementById('importBtn');
  var hint = document.getElementById('importHint');
  navigator.clipboard.writeText(_importUrl).then(function() {
    btn.textContent = 'Link copied!';
    btn.classList.add('copied');
    hint.innerHTML = 'Paste it in <code>Menu → Import</code> in PitchBoard.';
    setTimeout(function() {
      btn.textContent = 'Import this data';
      btn.classList.remove('copied');
      hint.innerHTML = 'Already have PitchBoard? Click Import, then go to <code>Menu → Import</code> and paste the link.';
    }, 4000);
  }).catch(function() {
    window.prompt('Copy this link, then paste it in PitchBoard → Menu → Import:', _importUrl);
  });
}

// ── Date helpers ─────────────────────────────────────────────────────────────

function _toDateInput(v) {
  // Convert M/D/YYYY → YYYY-MM-DD for <input type="date">
  if (!v) return '';
  var m = v.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
  if (!m) return '';
  return m[3] + '-' + ('0'+m[1]).slice(-2) + '-' + ('0'+m[2]).slice(-2);
}

function _fromDateInput(v) {
  // Convert YYYY-MM-DD → M/D/YYYY for sheet storage
  if (!v) return '';
  var m = v.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) return '';
  return parseInt(m[2]) + '/' + parseInt(m[3]) + '/' + m[1];
}

// ── Add Pitch ─────────────────────────────────────────────────────────────────

function openAddDialog() {
  _setupAddCombos();
  var pub = document.getElementById('addPubInput');
  pub.value    = '';
  pub.disabled = false;
  document.getElementById('addContactInput').value = '';
  document.getElementById('addDateInput').value    = '';
  document.getElementById('addEventInput').value   = '';
  document.getElementById('addStatusSel').value    = 'Pitched';
  document.getElementById('addNotesInput').value   = '';
  document.getElementById('addErr').style.display  = 'none';
  document.getElementById('addSubmitBtn').disabled = false;
  document.getElementById('addOverlay').classList.add('open');
  setTimeout(function(){ pub.focus(); }, 60);
}

function openAddDialogForPub(pubName, event) {
  if (event) event.stopPropagation();
  _setupAddCombos();
  var pub = document.getElementById('addPubInput');
  pub.value    = pubName;
  pub.disabled = true;
  document.getElementById('addContactInput').value = '';
  document.getElementById('addDateInput').value    = '';
  document.getElementById('addEventInput').value   = '';
  document.getElementById('addStatusSel').value    = 'Pitched';
  document.getElementById('addNotesInput').value   = '';
  document.getElementById('addErr').style.display  = 'none';
  document.getElementById('addSubmitBtn').disabled = false;
  document.getElementById('addOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('addContactInput').focus(); }, 60);
}

function closeAddDialog() {
  document.getElementById('addOverlay').classList.remove('open');
}

function submitAdd() {
  var pub     = document.getElementById('addPubInput').value.trim();
  var contact = document.getElementById('addContactInput').value.trim();
  var dateIn  = document.getElementById('addDateInput').value.trim();
  var event   = document.getElementById('addEventInput').value.trim();
  var status  = document.getElementById('addStatusSel').value;
  var notes   = document.getElementById('addNotesInput').value.trim();
  var err     = document.getElementById('addErr');
  var btn     = document.getElementById('addSubmitBtn');

  err.style.display = 'none';
  if (!pub)    { err.textContent = 'Publisher is required.'; err.style.display = 'block'; return; }
  if (!dateIn) { err.textContent = 'Date is required.';      err.style.display = 'block'; return; }

  btn.disabled = true;
  var body = 'id='        + encodeURIComponent(_sheetId)
           + '&game='     + encodeURIComponent(_gameName)
           + '&publisher='+ encodeURIComponent(pub)
           + '&contact='  + encodeURIComponent(contact)
           + '&date='     + encodeURIComponent(_fromDateInput(dateIn))
           + '&event='    + encodeURIComponent(event)
           + '&status='   + encodeURIComponent(status)
           + '&notes='    + encodeURIComponent(notes);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', _base + 'push/addRow.php');
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.timeout = 30000;
  xhr.ontimeout = function() { btn.disabled = false; err.textContent = 'Request timed out.'; err.style.display = 'block'; };
  xhr.onerror   = function() { btn.disabled = false; err.textContent = 'Network error.';     err.style.display = 'block'; };
  xhr.onload    = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
    if (r && r.ok) { location.reload(); return; }
    btn.disabled = false;
    err.textContent = (r && r.error) ? r.error : 'Failed to add entry.';
    err.style.display = 'block';
  };
  xhr.send(body);
}

// ── Edit Pitch ────────────────────────────────────────────────────────────────

var _editEntry = null;

function openEditDialog(el) {
  var entry = null;
  try { entry = JSON.parse(el.dataset.entry); } catch(e) { return; }
  _editEntry = entry;

  document.getElementById('editPubDisplay').value   = entry['Publisher'] || '';
  document.getElementById('editContactInput').value = entry['Contact']   || '';
  document.getElementById('editDateInput').value    = _toDateInput(entry['Date'] || '');
  document.getElementById('editEventInput').value   = entry['Event']     || '';
  var sel = document.getElementById('editStatusSel');
  sel.value = entry['Status'] || 'Pitched';
  if (!sel.value) sel.selectedIndex = 0;
  document.getElementById('editNotesInput').value   = entry['Notes']     || '';
  document.getElementById('editErr').style.display  = 'none';
  document.getElementById('editSubmitBtn').disabled = false;
  document.getElementById('editOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('editContactInput').focus(); }, 60);
}

function closeEditDialog() {
  document.getElementById('editOverlay').classList.remove('open');
  _editEntry = null;
}

function submitEdit() {
  if (!_editEntry) return;
  var contact = document.getElementById('editContactInput').value.trim();
  var dateIn  = document.getElementById('editDateInput').value.trim();
  var event   = document.getElementById('editEventInput').value.trim();
  var status  = document.getElementById('editStatusSel').value;
  var notes   = document.getElementById('editNotesInput').value.trim();
  var err     = document.getElementById('editErr');
  var btn     = document.getElementById('editSubmitBtn');

  err.style.display = 'none';
  if (!dateIn) { err.textContent = 'Date is required.'; err.style.display = 'block'; return; }

  btn.disabled = true;
  var body = 'id='           + encodeURIComponent(_sheetId)
           + '&game='        + encodeURIComponent(_gameName)
           + '&publisher='   + encodeURIComponent(_editEntry['Publisher'] || '')
           + '&orig_contact='+ encodeURIComponent(_editEntry['Contact']   || '')
           + '&orig_date='   + encodeURIComponent(_editEntry['Date']      || '')
           + '&orig_event='  + encodeURIComponent(_editEntry['Event']     || '')
           + '&contact='     + encodeURIComponent(contact)
           + '&date='        + encodeURIComponent(_fromDateInput(dateIn))
           + '&event='       + encodeURIComponent(event)
           + '&status='      + encodeURIComponent(status)
           + '&notes='       + encodeURIComponent(notes);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', _base + 'push/updateRow.php');
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.timeout = 30000;
  xhr.ontimeout = function() { btn.disabled = false; err.textContent = 'Request timed out.'; err.style.display = 'block'; };
  xhr.onerror   = function() { btn.disabled = false; err.textContent = 'Network error.';     err.style.display = 'block'; };
  xhr.onload    = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
    if (r && r.ok) { location.reload(); return; }
    btn.disabled = false;
    err.textContent = (r && r.error) ? r.error : 'Failed to update entry.';
    err.style.display = 'block';
  };
  xhr.send(body);
}

// Close dialogs on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') { closeAddDialog(); closeEditDialog(); }
});
</script>
</body>
</html>
