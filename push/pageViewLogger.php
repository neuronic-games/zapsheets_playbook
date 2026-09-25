<?php
/**
 * pageViewLogger.php — append a page-view entry to sheets/{sheetId}/page-views.json.
 *
 * Usage:
 *   require_once __DIR__ . '/push/pageViewLogger.php';
 *   logPageView($sheetId, 'pitchboard');
 *   logPageView($sheetId, 'share',      'Doll House');
 *   logPageView($sheetId, 'game',       'Doll House');
 *
 * Entry format:  { "type": "pitchboard"|"share"|"game", "date": "YYYY-MM-DD", "h": "<12-char hash>", "game": "..." }
 * Entries older than 90 days are pruned on each write.
 * IP addresses are never stored — only a daily+per-sheet hashed token.
 */

function logPageView($sheetId, $type, $game = '') {
    if (!$sheetId) return;
    $dir  = dirname(__FILE__, 2) . '/sheets/' . $sheetId;
    $file = $dir . '/page-views.json';
    if (!is_dir($dir)) return;

    // Anonymise: daily + per-sheet salt so the same person hashes differently across days
    $date = date('Y-m-d');
    $ip   = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip   = trim(explode(',', $ip)[0]);
    $h    = substr(hash('sha256', $ip . '|' . $date . '|' . $sheetId), 0, 12);

    $entry = ['type' => $type, 'date' => $date, 'h' => $h];
    if ($game !== '') $entry['game'] = $game;

    // Load, append, prune to last 90 days, save atomically
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
 * Compute summary stats from a page-views.json array.
 * Returns an array keyed by type, each with total, unique, recent (last 30 days),
 * and byGame (array of [game => [total, unique, recent]]) for share + game types.
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
        $t    = $e['type']  ?? '';
        $h    = $e['h']     ?? '';
        $d    = $e['date']  ?? '';
        $g    = $e['game']  ?? '';
        $isR  = $d >= $recent;

        if (!isset($stats[$t])) {
            $stats[$t] = ['total' => 0, 'hashes' => [], 'recent' => 0, 'recentHashes' => [], 'byGame' => []];
        }
        $stats[$t]['total']++;
        $stats[$t]['hashes'][$h] = true;
        if ($isR) { $stats[$t]['recent']++; $stats[$t]['recentHashes'][$h] = true; }

        if ($g !== '') {
            if (!isset($stats[$t]['byGame'][$g])) {
                $stats[$t]['byGame'][$g] = ['total'=>0,'hashes'=>[],'recent'=>0,'recentHashes'=>[]];
            }
            $stats[$t]['byGame'][$g]['total']++;
            $stats[$t]['byGame'][$g]['hashes'][$h] = true;
            if ($isR) { $stats[$t]['byGame'][$g]['recent']++; $stats[$t]['byGame'][$g]['recentHashes'][$h] = true; }
        }
    }

    // Collapse hashes → unique counts
    $out = [];
    foreach ($stats as $t => $s) {
        $row = [
            'total'  => $s['total'],
            'unique' => count($s['hashes']),
            'recent' => $s['recent'],
            'recentUnique' => count($s['recentHashes']),
        ];
        if ($s['byGame']) {
            $row['byGame'] = [];
            foreach ($s['byGame'] as $gName => $gs) {
                $row['byGame'][$gName] = [
                    'total'        => $gs['total'],
                    'unique'       => count($gs['hashes']),
                    'recent'       => $gs['recent'],
                    'recentUnique' => count($gs['recentHashes']),
                ];
            }
            // Sort by total desc
            arsort($row['byGame']);
        }
        $out[$t] = $row;
    }
    return $out;
}
?>
