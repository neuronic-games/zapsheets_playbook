<?php
/**
 * gbGate.php — the GameBoard account check for endpoints that change a DevBoard, PitchBoard or BillBoard sheet.
 *
 *   require_once __DIR__ . '/gbGate.php'; gbGate(['devboard']);                 // always checked
 *   require_once __DIR__ . '/gbGate.php'; gbGate(['devboard'], false);          // only from the owner apps
 *
 * The owner apps (gb-account.js) send the account token (X-CB-Token) and their name (X-GB-App) with every
 * request. A POST is let through when:
 *   - the person is signed in, and the sheet is theirs (it's in their Sheets in the Admin sheet), and
 *   - the app is theirs: free week, code, subscription (that app or the all-in-one) or activation —
 *     or the sheet itself is paid for (a subscription from before accounts), activated or comped.
 * Otherwise it answers {error, need_auth|view_only|locked} and stops; the apps show sign-in or the plans.
 *
 * $always = false: endpoints the shared (collaborator) pages use too. Those pages don't send X-GB-App,
 * so they keep working with their own per-sheet profiles; requests from an owner app are checked.
 * GET requests (reading, checking) are never stopped.
 */
require_once __DIR__ . '/gameboardAuth.php';

function gbGateSheet(): string {
    foreach (['id', 'sheet_id', 'sheetId', 'sheet'] as $k) {
        $v = trim((string) ($_POST[$k] ?? ($_GET[$k] ?? '')));
        if ($v !== '') return $v;
    }
    return '';
}

function gbGateStop(array $d): void {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function gbGate(array $apps, bool $always = true): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    $asked = strtolower(trim((string) ($_SERVER['HTTP_X_GB_APP'] ?? ($_POST['gb_app'] ?? ''))));
    if (!$always && $asked === '') return;
    $app  = in_array($asked, $apps, true) ? $asked : $apps[0];
    $name = array_key_exists($app, GB_APP_COLS) ? GB_APP_COLS[$app] : 'this app';

    $p = cbReadToken(cbRequestToken());
    if (!$p) gbGateStop(['error' => 'Sign in with your GameBoard account to make changes.', 'need_auth' => true]);
    if (($p['x'] ?? 0) < time()) gbGateStop(['error' => 'Your sign-in needs refreshing.', 'need_auth' => true, 'refresh' => true]);

    $sheet = gbGateSheet();
    if (stripeValidSheetId($sheet)) {
        if (!isset($p['sh'])) gbGateStop(['error' => 'Your sign-in needs refreshing.', 'need_auth' => true, 'refresh' => true]);
        if (!in_array($sheet, (array) $p['sh'], true))
            gbGateStop(['error' => 'This sheet belongs to another GameBoard account — you can view it, not change it.', 'view_only' => true]);
        if (cbSheetUnlocked($sheet)) return;
        if (stripeSheetPlan($sheet, $app)['is_core']) return;   // paid for on the sheet, before accounts
    }
    if (gbAppAccess($p, $app)['u'] < time())
        gbGateStop(['error' => 'Your free week has ended — subscribe to ' . $name . ' (or get all four) to keep going.', 'locked' => true, 'app' => $app]);
}
