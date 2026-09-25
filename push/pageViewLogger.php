<?php
/**
 * pageViewLogger.php — append a page-view entry to sheets/{sheetId}/page-views.json.
 *
 * Usage:
 *   require_once __DIR__ . '/push/pageViewLogger.php';
 *   logPageView($sheetId, 'pitchboard');
 *   logPageView($sheetId, 'share', 'Doll House');
 *   logPageView($sheetId, 'game',  'Doll House');
 *
 * Entry format:  { "type": "pitchboard"|"share"|"game", "date": "YYYY-MM-DD", "game": "..." }
 * Entries older than 90 days are pruned on each write.
 */

function logPageView($sheetId, $type, $game = '') {
    if (!$sheetId) return;
    $dir  = dirname(__FILE__, 2) . '/sheets/' . $sheetId;
    $file = $dir . '/page-views.json';
    if (!is_dir($dir)) return;

    $entry = ['type' => $type, 'date' => date('Y-m-d')];
    if ($game !== '') $entry['game'] = $game;

    $existing = [];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw) $existing = json_decode($raw, true) ?: [];
    }
    $existing[] = $entry;

    $cutoff   = date('Y-m-d', strtotime('-90 days'));
    $existing = array_values(array_filter($existing, function($e) use ($cutoff) {
        return ($e['date'] ?? '') >= $cutoff;
    }));

    @file_put_contents($file, json_encode($existing, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Compute summary stats from page-views.json.
 * Returns totals and per-game breakdowns for share + game types.
 */
function pageViewStats($sheetId) {
    $dir  = dirname(__FILE__, 2) . '/sheets/' . $sheetId;
    $file = $dir . '/page-views.json';
    if (!file_exists($file)) return [];

    $raw     = @file_get_contents($file);
    $entries = $raw ? (json_decode($raw, true) ?: []) : [];
    $recent  = date('Y-m-d', strtotime('-30 days'));

    $stats = [];
    foreach ($entries as $e) {
        $t   = $e['type'] ?? '';
        $g   = $e['game'] ?? '';
        $isR = ($e['date'] ?? '') >= $recent;

        if (!isset($stats[$t])) $stats[$t] = ['total' => 0, 'recent' => 0, 'byGame' => []];
        $stats[$t]['total']++;
        if ($isR) $stats[$t]['recent']++;

        if ($g !== '') {
            if (!isset($stats[$t]['byGame'][$g])) $stats[$t]['byGame'][$g] = ['total' => 0, 'recent' => 0];
            $stats[$t]['byGame'][$g]['total']++;
            if ($isR) $stats[$t]['byGame'][$g]['recent']++;
        }
    }

    foreach ($stats as $t => &$s) {
        arsort($s['byGame']);
    }

    // ── Time-series: daily hit counts per line ───────────────────────────────
    // Shape: { seriesName: { "YYYY-MM-DD": count } }
    // 'PitchBoard' = owner board visits; game names = share + product page visits
    $timeSeries = [];
    foreach ($entries as $e) {
        $t = $e['type'] ?? '';
        $g = $e['game'] ?? '';
        $d = $e['date'] ?? '';
        if ($d === '') continue;
        if ($t === 'pitchboard') {
            $key = 'PitchBoard';
            if (!isset($timeSeries[$key])) $timeSeries[$key] = [];
            $timeSeries[$key][$d] = ($timeSeries[$key][$d] ?? 0) + 1;
        } elseif (($t === 'share' || $t === 'game') && $g !== '') {
            if (!isset($timeSeries[$g])) $timeSeries[$g] = [];
            $timeSeries[$g][$d] = ($timeSeries[$g][$d] ?? 0) + 1;
        }
    }
    foreach ($timeSeries as &$days) { ksort($days); }
    $stats['_timeSeries'] = $timeSeries;

    return $stats;
}
?>
