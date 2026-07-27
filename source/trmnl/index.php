<?php
/**
 * TRMNL e-paper plugin — PitchBoard Follow-Ups
 *
 * How it works:
 *   TRMNL private plugin "Polling URL" strategy: TRMNL fetches this URL,
 *   expects JSON {"merge_variables": {...}}, and injects the variables into
 *   the Liquid markup template you paste into the TRMNL editor.
 *
 * URL:  /{sheetId}/trmnl           → JSON (for TRMNL polling)
 *       /{sheetId}/trmnl?preview=1 → full HTML page (browser preview)
 *
 * Query params:
 *   ?tier1=30   First follow-up threshold in days (default: 30)
 *   ?tier2=60   Second follow-up threshold in days (default: 60)
 *   ?preview=1  Return full HTML instead of JSON (for browser testing)
 */

require_once __DIR__ . '/../../dotEnv.php';

// ── Extract sheet ID from URL ─────────────────────────────────────────────────
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#/([A-Za-z0-9_\-]{20,})/trmnl#', $uri, $m);
$sheetId = $m[1] ?? '';
if (!$sheetId && !empty($_GET['id'])) {
    $sheetId = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['id']);
}

// ── Config ────────────────────────────────────────────────────────────────────
$tier1 = max(1,       (int)($_GET['tier1'] ?? 30));
$tier2 = max($tier1+1,(int)($_GET['tier2'] ?? 60));

// ── Load pitches ──────────────────────────────────────────────────────────────
$pitchFile  = __DIR__ . '/../../sheets/' . $sheetId . '/pitches.json';
$rawPitches = [];
if ($sheetId && is_file($pitchFile)) {
    $rawPitches = json_decode(file_get_contents($pitchFile), true) ?? [];
}

// ── Build set of games that are "done" ───────────────────────────────────────
$terminalStatuses = ['signed', 'published', 'contracted', 'licensed'];
$doneGames = [];
foreach ($rawPitches as $p) {
    $status = strtolower(trim($p['Status'] ?? ''));
    if (in_array($status, $terminalStatuses)) {
        $doneGames[strtolower(trim($p['Game'] ?? ''))] = true;
    }
}

// ── Filter & bucket ───────────────────────────────────────────────────────────
$today  = new DateTime('today');
$second = [];   // 60+ days (urgent)
$first  = [];   // 30–59 days (due)

foreach ($rawPitches as $p) {
    $status = strtolower(trim($p['Status'] ?? ''));
    if ($status !== 'pitched') continue;
    if (isset($doneGames[strtolower(trim($p['Game'] ?? ''))])) continue;

    $rawDate = trim($p['Date'] ?? '');
    if (!$rawDate) continue;
    try { $date = new DateTime($rawDate); }
    catch (Exception $e) { continue; }

    $age = (int)$today->diff($date)->days;
    if ($age < $tier1) continue;

    $entry = [
        'game'      => trim($p['Game']      ?? ''),
        'publisher' => trim($p['Publisher'] ?? '') ?: '—',
        'contact'   => trim($p['Contact']   ?? ''),
        'age'       => $age,
    ];

    if ($age >= $tier2) $second[] = $entry;
    else                $first[]  = $entry;
}

// Sort oldest-first
$cmp = fn($a, $b) => $b['age'] - $a['age'];
usort($second, $cmp);
usort($first,  $cmp);

// Save totals before cap (for stats)
$allUrgent = count($second);
$allDue    = count($first);

// Cap at 10 total
if (count($second) + count($first) > 10) {
    $sc = min(count($second), 10);
    $fc = min(count($first),  10 - $sc);
    $second = array_slice($second, 0, $sc);
    $first  = array_slice($first,  0, $fc);
}

$count = count($second) + count($first);

// ── Pipeline-wide stats ───────────────────────────────────────────────────────
// Latest status per game-publisher pair (excluding done games)
$pairs = [];
foreach ($rawPitches as $p) {
    $game   = strtolower(trim($p['Game']      ?? ''));
    $pub    = trim($p['Publisher'] ?? '');
    $status = strtolower(trim($p['Status']    ?? ''));
    $date   = trim($p['Date']      ?? '');
    if (!$game || !$pub || !$status) continue;
    if (isset($doneGames[$game])) continue;
    $key = $game . '||' . $pub;
    if (!isset($pairs[$key]) || $date > ($pairs[$key]['date'] ?? '')) {
        $pairs[$key] = ['status' => $status, 'date' => $date];
    }
}
$statInterested = 0;
$statPitched    = 0;
foreach ($pairs as $pair) {
    if ($pair['status'] === 'interested') $statInterested++;
    elseif ($pair['status'] === 'pitched') $statPitched++;
}
$statActive = $statPitched + $statInterested;

// ── Helpers ───────────────────────────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function ageBadge(int $days): string {
    if ($days >= 365) return round($days / 365, 1) . 'y';
    if ($days >= 30)  return round($days / 30)  . 'mo';
    return $days . 'd';
}
function mapEntry(array $fu): array {
    return [
        'game'      => $fu['game'],
        'publisher' => $fu['publisher'],
        'contact'   => $fu['contact'],
        'age_label' => ageBadge($fu['age']),
    ];
}

// ── Output ────────────────────────────────────────────────────────────────────
$isPreview = !empty($_GET['preview']);

if (!$isPreview) {
    // ── JSON for TRMNL polling ────────────────────────────────────────────────
    // TRMNL polling expects flat root-level JSON — no "merge_variables" wrapper.
    // Arrays of objects with dot-notation (fu.game, fu.pub) are supported.
    $toEntry = fn(array $fu) => [
        'age' => ageBadge($fu['age']),
        'pub' => $fu['publisher'],
        'game' => $fu['game'],
        'contact' => $fu['contact'],
    ];

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'fu_count'       => $count,
        'fu_tier1'       => $tier1,
        'fu_tier2'       => $tier2,
        'fu_t2m1'        => $tier2 - 1,
        'fu_has_both'    => !empty($second) && !empty($first),
        'fu_overdue'     => array_map($toEntry, $second),
        'fu_due'         => array_map($toEntry, $first),
        'all_urgent'     => $allUrgent,
        'all_due'        => $allDue,
        'all_active'     => $statActive,
        'all_interested' => $statInterested,
        'all_pitched'    => $statPitched,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── HTML preview (browser only) ───────────────────────────────────────────────
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=800">
  <link rel="stylesheet" href="https://trmnl.com/css/latest/plugins.css">
  <script src="https://trmnl.com/js/latest/plugins.js"></script>
  <style>
    .fu-list { list-style:none; margin:0; padding:0; width:100%; }
    .fu-item { display:flex; align-items:baseline; gap:10px; padding:9px 0; border-bottom:1px solid #ccc; line-height:1.2; }
    .fu-item:last-child { border-bottom:none; }
    .fu-age { flex:0 0 42px; font-size:13px; font-weight:700; text-align:center; padding:2px 4px; border-radius:3px; border:1px solid #000; color:#000; background:transparent; }
    .fu-age.urgent { background:#e00; color:#fff; border-color:#e00; }
    .fu-pub { flex:0 0 210px; font-size:15px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .fu-game { flex:1; font-size:14px; color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .fu-contact { flex:0 0 148px; font-size:13px; color:#555; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-align:right; }
    .fu-divider { display:flex; align-items:center; gap:6px; margin:4px 0 2px; }
    .fu-divider-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; white-space:nowrap; color:#000; }
    .fu-divider-line { flex:1; height:1px; background:#ccc; }
    .fu-empty { font-size:20px; font-weight:700; text-align:center; padding-top:80px; color:#555; }
    /* Layout: pin footer to bottom */
    .view--full { display:flex !important; flex-direction:column !important; }
    .layout { flex:1 !important; overflow:hidden !important; min-height:0 !important; }
    .column { overflow:hidden !important; }
    /* Yellow footer */
    .title_bar { flex-shrink:0 !important; background:#FFD700 !important; }
    .title_bar .title, .title_bar .instance { color:#000 !important; }
  </style>
</head>
<body class="environment trmnl">
  <div class="screen">
    <div class="view view--full">
      <div class="layout">
        <div class="columns">
          <div class="column">

<?php if ($count === 0): ?>
            <div class="fu-empty">
              No follow-ups due today<br>
              <span style="font-size:13px;font-weight:400;color:#777">All active pitches are within <?= $tier1 ?> days</span>
            </div>
<?php else: ?>
            <ul class="fu-list">
<?php foreach ($second as $fu): ?>
              <li class="fu-item">
                <span class="fu-age urgent"><?= h(ageBadge($fu['age'])) ?></span>
                <span class="fu-pub"><?= h($fu['publisher']) ?></span>
                <span class="fu-game"><?= h($fu['game']) ?></span>
                <?php if ($fu['contact']): ?><span class="fu-contact"><?= h($fu['contact']) ?></span><?php endif ?>
              </li>
<?php endforeach ?>
<?php if ($first && $second): ?>
              <li style="list-style:none;padding:0">
                <div class="fu-divider">
                  <span class="fu-divider-label"><?= $tier1 ?>–<?= $tier2-1 ?> days</span>
                  <span class="fu-divider-line"></span>
                </div>
              </li>
<?php endif ?>
<?php foreach ($first as $fu): ?>
              <li class="fu-item">
                <span class="fu-age"><?= h(ageBadge($fu['age'])) ?></span>
                <span class="fu-pub"><?= h($fu['publisher']) ?></span>
                <span class="fu-game"><?= h($fu['game']) ?></span>
                <?php if ($fu['contact']): ?><span class="fu-contact"><?= h($fu['contact']) ?></span><?php endif ?>
              </li>
<?php endforeach ?>
            </ul>
<?php endif ?>

          </div>
        </div>
      </div>

      <div class="title_bar">
        <img class="image" src="https://trmnl.com/images/plugins/trmnl--render.svg" />
        <span class="title">PitchBoard</span>
        <span class="instance"><?= $count ?> pitches</span>
      </div>
    </div>
  </div>
</body>
</html>
