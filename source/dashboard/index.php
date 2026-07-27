<?php
$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#^(.*?)(?:sheets/)?([A-Za-z0-9_\-]+)/dashboard/?$#', $_rp, $_bm);
$_base     = (isset($_bm[1]) && $_bm[1] !== '') ? $_bm[1] : '/';
if (substr($_base, -1) !== '/') $_base .= '/';
$_sheet_id = $_bm[2] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PitchBoard</title>
  <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($_base) ?>images/pb_icon_180.png" />
  <link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars($_base) ?>images/pb_icon_192.png" />
  <link rel="manifest" href="<?= htmlspecialchars($_base) ?>manifest.php?id=<?= urlencode($_sheet_id) ?>&amp;app=pitchboard&amp;base=<?= urlencode($_base) ?>" />
  <style>
    @font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2') format('woff2'),url('fonts/DINBlack.ttf'); }
    @font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf'); }
    *, *::before, *::after { box-sizing: border-box; }
    body { margin:0; background:#f3f2ef; font-family:'DINRegular',Arial,sans-serif; color:#111; }

    /* ── Top bar ─────────────────────────────────────── */
    .top-bar {
      background:#1a1a2e; color:#fff;
      padding:.75rem 1.25rem;
      position:sticky; top:0; z-index:100;
    }
    .top-bar-inner {
      max-width:900px; margin:0 auto;
      display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;
    }
    .top-bar-left { flex:1; min-width:0; }
    .top-bar h1 { font-family:'DINBlack',sans-serif; font-size:1rem; margin:0; letter-spacing:.03em; cursor:pointer; }
    .top-bar h1:hover { opacity:.8; }
    .top-bar .sub { font-size:.73rem; opacity:.6; margin:0; letter-spacing:.01em; }
    .version-tag { opacity:.4; font-size:.65rem; }

    @media (max-width:500px) {
      .top-bar-left { flex-basis:100%; }
    }

    /* ── View toggle ─────────────────────────────────── */
    .view-toggle {
      display:flex; background:rgba(255,255,255,.12);
      border-radius:6px; overflow:hidden; flex-shrink:0;
    }
    .view-toggle button {
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.06em;
      background:none; color:rgba(255,255,255,.65);
      border:none; padding:.38rem .85rem; cursor:pointer;
      transition:background .15s, color .15s;
    }
    .view-toggle button.active { background:#fff; color:#1a1a2e; }

    /* ── Top-bar icon buttons ────────────────────────── */
    .sync-btn {
      display:inline-flex; align-items:center; justify-content:center;
      background:rgba(255,255,255,.15); color:#fff;
      border:1px solid rgba(255,255,255,.3); border-radius:6px;
      padding:.38rem .5rem; cursor:pointer;
      transition:background .15s; flex-shrink:0;
    }
    .sync-btn:hover { background:rgba(255,255,255,.25); }
    .sync-btn:disabled { opacity:.5; cursor:default; }
    .sync-btn:disabled:hover { background:rgba(255,255,255,.15); }
    @keyframes spin { to { transform:rotate(360deg); } }
    .sync-icon { display:inline-flex; align-items:center; justify-content:center; line-height:1; }
    .sync-btn.syncing .sync-icon { animation:spin .8s linear infinite; }
    /* ── Account menu ────────────────────────────────── */
    .account-menu-wrap { position:relative; flex-shrink:0; }
    .account-menu {
      display:none; position:absolute; top:calc(100% + .4rem); right:0;
      background:#1a1a2e; border:1px solid rgba(255,255,255,.2);
      border-radius:8px; min-width:130px; z-index:300;
      box-shadow:0 6px 20px rgba(0,0,0,.4); overflow:hidden;
    }
    .account-menu.open { display:block; }
    .account-menu-item {
      display:block; width:100%; background:none; border:none;
      color:rgba(255,255,255,.85); text-align:left; cursor:pointer;
      font-family:'DINBlack',sans-serif; font-size:.72rem;
      text-transform:uppercase; letter-spacing:.07em;
      padding:.6rem 1rem;
      transition:background .12s;
    }
    .account-menu-item:hover { background:rgba(255,255,255,.1); color:#fff; }

    /* ── Summary bar ─────────────────────────────────── */
    .summary-bar {
      display:flex; gap:.5rem; flex-wrap:wrap;
      padding:.75rem 1.25rem .4rem;
      max-width:900px; margin:0 auto;
    }
    .pill {
      display:inline-flex; align-items:center; gap:.3rem;
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.05em;
      padding:.25rem .65rem; border-radius:999px;
    }
    .pill-total     { background:#1a1a2e; color:#fff; }
    .pill-pitched   { background:#e2e8f0; color:#334155; cursor:pointer; border:none; transition:opacity .15s,box-shadow .15s; }
    .pill-int       { background:#dcfce7; color:#166534; cursor:pointer; border:none; transition:opacity .15s,box-shadow .15s; }
    .pill-passed    { background:#fee2e2; color:#991b1b; cursor:pointer; border:none; transition:opacity .15s,box-shadow .15s; }
    .pill-signed    { background:#7c3aed; color:#fff; cursor:pointer; border:none; transition:opacity .15s,box-shadow .15s; }
    .pill-published { background:#0369a1; color:#fff; cursor:pointer; border:none; transition:opacity .15s,box-shadow .15s; }
    .pill-cold      { background:#dbeafe; color:#1e40af; cursor:pointer; border:none; transition:opacity .15s,box-shadow .15s; }
    .pill-pitched:hover, .pill-int:hover, .pill-passed:hover,
    .pill-signed:hover, .pill-published:hover, .pill-cold:hover { opacity:.85; }
    .pill-pitched.filter-active   { box-shadow:0 0 0 2px #fff, 0 0 0 4px #94a3b8; }
    .pill-int.filter-active       { box-shadow:0 0 0 2px #fff, 0 0 0 4px #16a34a; }
    .pill-passed.filter-active    { box-shadow:0 0 0 2px #fff, 0 0 0 4px #dc2626; }
    .pill-signed.filter-active    { box-shadow:0 0 0 2px #fff, 0 0 0 4px #7c3aed; }
    .pill-published.filter-active { box-shadow:0 0 0 2px #fff, 0 0 0 4px #0369a1; }
    .pill-cold.filter-active      { box-shadow:0 0 0 2px #fff, 0 0 0 4px #1e40af; }

    /* ── Search + sort ───────────────────────────────── */
    .search-bar { padding:.4rem 1.25rem .5rem; display:flex; align-items:stretch; gap:.6rem; max-width:900px; margin:0 auto; }
    .search-wrap { position:relative; flex:1; display:flex; align-items:stretch; }
    .search-wrap input {
      width:100%; height:100%; box-sizing:border-box;
      font-family:'DINRegular',sans-serif; font-size:.68rem; line-height:1;
      border:1px solid #ccc; border-radius:6px;
      padding:0 2rem 0 .9rem; outline:none;
      background:#fff; color:#111;
    }
    .search-wrap input:focus { border-color:#1a1a2e; }
    .search-clear {
      position:absolute; right:.55rem;
      top:50%; transform:translateY(-50%);
      background:none; border:none; cursor:pointer;
      font-size:1rem; color:#aaa; line-height:1;
      padding:0; display:none;
    }
    .search-clear:hover { color:#555; }
    .search-wrap.has-text .search-clear { display:block; }
    .sort-toggle {
      display:flex; background:#e2e8f0;
      border-radius:6px; overflow:hidden; flex-shrink:0;
    }
    .sort-toggle button {
      font-family:'DINBlack',sans-serif; font-size:.68rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:none; color:#64748b;
      border:none; padding:.38rem .75rem; cursor:pointer;
      transition:background .15s, color .15s;
    }
    .sort-toggle button.active { background:#1a1a2e; color:#fff; }

    .new-game-btn {
      font-family:'DINBlack',sans-serif; font-size:.68rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:#1a1a2e; color:#fff;
      border:none; border-radius:6px;
      padding:.38rem .85rem; cursor:pointer; flex-shrink:0;
      white-space:nowrap; transition:background .15s;
    }
    .new-game-btn:hover { background:#2d2d4e; }

    /* ── Content ─────────────────────────────────────── */
    .content { padding:.4rem 1.25rem 3rem; max-width:900px; margin:0 auto; }

    /* ── Card ────────────────────────────────────────── */
    .card {
      background:#fff; border-radius:10px;
      box-shadow:0 1px 4px rgba(0,0,0,.08);
      margin-bottom:.9rem; overflow:hidden;
    }
    .card-header {
      background:#1a1a2e; color:#fff;
      padding:.55rem 1rem;
      display:flex; align-items:center; gap:.5rem;
      cursor:pointer; user-select:none;
    }
    .card-header:hover { background:#252545; }
    .card-title {
      font-family:'DINBlack',sans-serif; font-size:.9rem;
      letter-spacing:.03em; flex:1; min-width:0;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .card-badges { display:flex; align-items:center; gap:.35rem; flex-shrink:0; }
    .game-links-designers {
      font-family:'DINRegular',sans-serif; font-size:.72rem;
      color:rgba(255,255,255,.55); white-space:nowrap;
      align-self:center; flex-shrink:0;
      padding-right:.15rem;
    }
    .designer-chip {
      font-family:'DINRegular',sans-serif; font-size:.72rem;
      color:rgba(255,255,255,.7); cursor:pointer;
      background:none; border:none; padding:0; margin:0;
      text-decoration:underline;
      text-decoration-color:rgba(255,255,255,.3);
      text-underline-offset:2px;
      transition:color .12s, text-decoration-color .12s;
    }
    .designer-chip:hover { color:#fff; text-decoration-color:rgba(255,255,255,.75); }
    /* ── Designer info dialog ────────────────────────────── */
    .di-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.45); z-index:1000;
      align-items:center; justify-content:center; padding:1rem;
    }
    .di-overlay.open { display:flex; }
    .di-dialog {
      background:#fff; border-radius:10px;
      padding:1.4rem; width:min(420px,94vw);
      box-shadow:0 8px 32px rgba(0,0,0,.22);
      display:flex; flex-direction:column; gap:.75rem;
    }
    .di-title {
      font-family:'DINBlack',sans-serif; font-size:.95rem;
      color:#1a1a2e; margin:0;
    }
    .di-not-found {
      font-family:'DINRegular',sans-serif; font-size:.8rem;
      color:#e57; margin-top:-.25rem;
    }
    .di-dialog .combo-wrap input {
      font-size:.82rem; color:#222; border-color:#ddd; padding:.38rem .55rem;
    }
    .card-chevron {
      font-size:.65rem; opacity:.55; flex-shrink:0;
      transition:transform .22s ease; transform:rotate(-90deg);
    }
    .card.open .card-chevron { transform:rotate(0deg); }
    .card-body-wrap {
      display:grid; grid-template-rows:0fr;
      transition:grid-template-rows .22s ease;
    }
    .card.open .card-body-wrap { grid-template-rows:1fr; }
    .card-body { overflow:hidden; min-height:0; }

    /* ── Status badges ───────────────────────────────── */
    .badge {
      font-family:'DINBlack',sans-serif; font-size:.65rem;
      text-transform:uppercase; letter-spacing:.06em;
      padding:.38rem .65rem; border-radius:999px; white-space:nowrap;
      line-height:1; display:inline-flex; align-items:center;
    }
    .badge-interested  { background:#dcfce7; color:#166534; }
    .badge-passed      { background:#fee2e2; color:#991b1b; }
    .badge-pitched     { background:#e2e8f0; color:#334155; }
    .badge-signed      { background:#7c3aed; color:#fff; }
    .badge-published   { background:#0369a1; color:#fff; }
    .badge-returned    { background:#f97316; color:#fff; }
    .badge-gone-cold   { background:#dbeafe; color:#1e40af; }
    .status-date       { font-family:'DINRegular',sans-serif; font-size:.65rem; color:#888; font-weight:normal; text-transform:none; letter-spacing:0; white-space:nowrap; }
    .badge-age-6mo     { background:#ef4444; color:#fff; }
    .badge-age-3mo     { background:#f59e0b; color:#fff; }

    /* ── Sub-group ───────────────────────────────────── */
    .sub-group { border-top:1px solid #f0f0f0; }
    .sub-group:first-child { border-top:none; }
    .pub-passed-header { cursor:pointer; user-select:none; }
    .pub-passed-header:hover { background:#fafafa; }
    .pub-title-group {
      display:flex; align-items:center; gap:.35rem; flex:1; min-width:0;
    }
    .pub-title-group > span {
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0;
    }
    .pub-expand-chevron {
      font-size:.58rem; color:#bbb; margin-left:.2rem; flex-shrink:0;
      display:inline-block; transition:transform .18s ease;
    }
    .pub-body-wrap {
      display:grid; grid-template-rows:0fr;
      transition:grid-template-rows .18s ease;
    }
    .pub-body-wrap.open { grid-template-rows:1fr; }
    .pub-passed-body { overflow:hidden; min-height:0; }
    .sub-label {
      font-family:'DINBlack',sans-serif; font-size:.72rem;
      color:#666; text-transform:uppercase; letter-spacing:.05em;
      padding:.45rem 1rem .2rem 1.6rem;
      display:flex; align-items:center; gap:.5rem;
    }
    .contact-email-btn {
      display:inline-flex; align-items:center;
      font-family:'DINBlack',sans-serif; font-size:.62rem;
      text-transform:uppercase; letter-spacing:.05em;
      color:#1a1a2e; background:#e8e8f0; border:none;
      border-radius:999px; padding:.12rem .5rem;
      text-decoration:none; cursor:pointer; white-space:nowrap;
      transition:background .15s;
    }
    .contact-email-btn:hover { background:#1a1a2e; color:#fff; }

    /* ── Entry row ───────────────────────────────────── */
    .entry-row {
      display:grid;
      grid-template-columns: 86px 130px 80px auto 1fr;
      align-items:center; gap:.5rem;
      padding:.32rem 1rem .32rem 2rem;
      border-top:1px solid #f5f5f5;
      font-size:.8rem;
    }
    .entry-date    { color:#777; white-space:nowrap; }
    .entry-contact { color:#555; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .entry-event   { color:#999; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .entry-status  { justify-self:start; }
    .entry-notes   { color:#444; line-height:1.42; min-width:0;
                     overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
    /* ── Alternating publisher background ───────────── */
    .sub-group.pub-alt > .sub-label { background:#f4f5fb; }
    .sub-group.pub-alt > .sub-label.pub-passed-header:hover { background:#eaebf5; }
    .sub-group.pub-alt .entry-row { background:#f4f5fb; }
    .sub-group.pub-alt .entry-row:hover { background:#eaebf5; }
    /* ── Publisher subtitle row (inside expanded card) ─ */
    .pub-subtitle-row {
      display:flex; align-items:center; gap:.75rem;
      padding:.48rem 1rem .48rem 1.1rem;
      border-bottom:1px solid rgba(255,255,255,.06);
      background:#1a1a2e;
    }
    .pub-subtitle-info {
      font-family:'DINRegular',sans-serif; font-size:.74rem;
      color:rgba(255,255,255,.55); flex:1; min-width:0;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }

    /* ── Empty / loading ─────────────────────────────── */
    .empty { padding:3rem; text-align:center; color:#999; font-size:.88rem; }

    /* ── Dashboard view ──────────────────────────────── */
    .db-stats { display:flex; flex-wrap:wrap; gap:.65rem; margin-bottom:1rem; }
    .db-stat {
      background:#fff; border-radius:8px;
      box-shadow:0 1px 4px rgba(0,0,0,.08);
      padding:.85rem 1.1rem; flex:1; min-width:110px;
    }
    .db-stat-value {
      font-family:'DINBlack',sans-serif; font-size:1.55rem; color:#1a1a2e; line-height:1;
    }
    .db-stat-label {
      font-size:.67rem; color:#888; text-transform:uppercase;
      letter-spacing:.05em; margin-top:.3rem;
    }
    .db-charts {
      display:grid; grid-template-columns:1fr 1fr; gap:.65rem; margin-bottom:.65rem;
    }
    .db-chart-card {
      background:#fff; border-radius:8px;
      box-shadow:0 1px 4px rgba(0,0,0,.08);
      padding:1rem 1.1rem;
    }
    .db-chart-card h3 {
      font-family:'DINBlack',sans-serif; font-size:.75rem; margin:0 0 .75rem;
      text-transform:uppercase; letter-spacing:.05em; color:#555;
    }
    .db-chart-wide { grid-column:1 / -1; }
    @media (max-width:600px) { .db-charts { grid-template-columns:1fr; } }

    /* ── Game sub-bar (links row + actions row) ─────── */
    .game-sub-bar {
      background:#1a1a2e;
      display:flex; align-items:center;
    }
    .game-links {
      flex:1; min-width:0;
      padding:.5rem 1rem .55rem;
      display:flex; gap:.35rem; flex-wrap:wrap; align-items:center;
    }
    /* Pills wrapper — sits inline on desktop, becomes its own scrollable row on mobile */
    .game-link-pills {
      display:contents; /* transparent on desktop — pills flow directly into .game-links */
    }
    .game-actions {
      display:flex; gap:.35rem; align-items:center; flex-shrink:0;
      padding:.5rem 1rem .5rem 0;
    }
    /* On narrow / portrait screens, stack designers / pills / actions each on own row */
    @media (max-width:540px) {
      .game-sub-bar { flex-direction:column; align-items:stretch; }
      .game-links {
        flex-direction:column; align-items:flex-start;
        gap:.3rem; padding-bottom:.35rem;
      }
      /* Designers line: left-aligned, wraps if needed */
      .game-links-designers { white-space:normal; align-self:flex-start; }
      /* Pills: single scrollable row */
      .game-link-pills {
        display:flex; flex-wrap:nowrap;
        gap:.35rem; align-items:center;
        overflow-x:auto; -webkit-overflow-scrolling:touch;
        scrollbar-width:none; width:100%;
      }
      .game-link-pills::-webkit-scrollbar { display:none; }
      /* Actions row: single scrollable row */
      .game-actions {
        padding:.3rem 1rem .55rem;
        border-top:1px solid rgba(255,255,255,.08);
        flex-wrap:nowrap; overflow-x:auto;
        -webkit-overflow-scrolling:touch; scrollbar-width:none;
      }
      .game-actions::-webkit-scrollbar { display:none; }
    }
    .game-link-pill {
      display:inline-block; padding:.18rem .65rem;
      background:rgba(255,255,255,.12); color:#e2e8f0; border-radius:20px;
      font-family:'DINBlack',sans-serif; font-size:.63rem;
      letter-spacing:.05em; text-transform:uppercase;
      text-decoration:none; transition:background .15s, color .15s;
    }
    .game-link-pill:hover { background:rgba(255,255,255,.25); color:#fff; }

    /* ── Game Timelines ──────────────────────────────── */
    .tl-list { display:flex; flex-direction:column; gap:1rem; }
    .tl-game {
      background:#fff; border-radius:8px; padding:.85rem 1.1rem;
      box-shadow:0 1px 4px rgba(0,0,0,.08);
    }
    .tl-game-name {
      font-family:'DINBlack',sans-serif; font-size:.8rem;
      color:#1a1a2e; margin-bottom:.65rem;
    }
    .tl-designers {
      font-family:'DINRegular',sans-serif; font-size:.72rem;
      color:#888; font-weight:normal;
    }
    .tl-row { display:flex; align-items:flex-start; }
    .tl-ms-wrap {
      display:flex; flex-direction:column; align-items:center;
      min-width:64px; flex-shrink:0;
    }
    .tl-dot {
      width:18px; height:18px; border-radius:50%;
      border:2.5px solid #dde1ea; background:#f8fafc;
      flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
      font-size:8.5px; font-weight:bold; color:transparent; line-height:1;
    }
    .tl-ms-wrap.reached .tl-dot { background:#1a1a2e; border-color:#1a1a2e; color:#fff; }
    .tl-ms-wrap.stage-signed.reached .tl-dot    { background:#7c3aed; border-color:#7c3aed; }
    .tl-ms-wrap.stage-published.reached .tl-dot { background:#0369a1; border-color:#0369a1; }
    .tl-ms-wrap.stage-pitching { min-width:auto; }
    .tl-pitching-pill { border-radius:999px; padding:0 5px; }
    .tl-connector {
      flex:1; height:3px; background:#dde1ea;
      margin-top:7px; min-width:8px;
    }
    .tl-connector.filled { background:#1a1a2e; }
    .tl-ms-label {
      font-size:.57rem; text-transform:uppercase; letter-spacing:.04em;
      color:#bbb; margin-top:.3rem; text-align:center; line-height:1.2;
    }
    .tl-ms-wrap.reached .tl-ms-label { color:#555; }
    .tl-ms-date {
      font-size:.55rem; color:#aaa; margin-top:.1rem;
      text-align:center; white-space:nowrap;
    }
    @media (max-width:500px) {
      .tl-ms-wrap { min-width:46px; }
      .tl-ms-label { font-size:.5rem; }
      .tl-ms-date  { font-size:.47rem; }
    }

    /* ── Import dialog ──────────────────────────────── */
    .import-dialog { width:min(560px,96vw); }
    .import-body { max-height:42vh; overflow-y:auto; -webkit-overflow-scrolling:touch; margin:.25rem 0 .5rem; }
    .import-game-name { font-family:'DINBlack',sans-serif; font-size:1rem; margin-bottom:.2rem; }
    .import-meta { color:#999; font-size:.73rem; margin-bottom:.6rem; }
    .import-section-label {
      font-family:'DINBlack',sans-serif; font-size:.62rem;
      text-transform:uppercase; letter-spacing:.06em;
      color:#666; margin:.6rem 0 .3rem;
    }
    .import-table-wrap { max-height:18vh; overflow-y:auto; border:1px solid #e5e5e5; border-radius:6px; -webkit-overflow-scrolling:touch; }
    .import-table { width:100%; border-collapse:collapse; font-size:.76rem; }
    .import-table th {
      text-align:left; color:#888; font-family:'DINBlack',sans-serif;
      font-size:.6rem; text-transform:uppercase; letter-spacing:.04em;
      border-bottom:1px solid #e5e5e5; padding:.25rem .45rem;
      position:sticky; top:0; background:#fff;
    }
    .import-table td { padding:.25rem .45rem; border-bottom:1px solid #f2f2f2; }
    .import-table tr:last-child td { border-bottom:none; }
    .import-people-list { margin:.2rem 0 0 1.1rem; padding:0; list-style:disc; font-size:.8rem; }
    .import-people-list li { margin:.2rem 0; }
    .import-company { color:#999; font-size:.9em; }
    .import-empty { color:#aaa; font-style:italic; font-size:.8rem; }

    /* ── Game sub-bar action buttons (dark bg) ──────── */
    .game-action-btn {
      font-family:'DINBlack',sans-serif; font-size:.6rem;
      text-transform:uppercase; letter-spacing:.06em;
      background:rgba(255,196,76,.14); color:#ffd166;
      border:1px solid rgba(255,196,76,.30); border-radius:999px; line-height:1;
      padding:.38rem .65rem; cursor:pointer; white-space:nowrap; flex-shrink:0;
      transition:background .15s, color .15s;
    }
    .game-action-btn:hover { background:rgba(255,196,76,.26); color:#ffe099; }

    /* ── Add-entry buttons ──────────────────────────── */
    /* Button next to contact name (light bg) */
    .add-entry-btn {
      display:inline-flex; align-items:center;
      font-family:'DINBlack',sans-serif; font-size:.62rem;
      text-transform:uppercase; letter-spacing:.05em;
      color:#1a1a2e; background:#e8e8f0; border:none;
      border-radius:999px; padding:.12rem .5rem;
      cursor:pointer; white-space:nowrap; transition:background .15s, color .15s;
    }
    .add-entry-btn:hover { background:#1a1a2e; color:#fff; }

    /* ── Add-entry dialog ───────────────────────────── */
    .add-entry-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.45); z-index:1000;
      align-items:center; justify-content:center; padding:1rem;
    }
    .add-entry-overlay.open { display:flex; }
    .add-entry-dialog {
      background:#fff; border-radius:10px;
      padding:1.4rem 1.5rem; width:min(480px,94vw);
      box-shadow:0 8px 32px rgba(0,0,0,.22);
      display:flex; flex-direction:column; gap:.85rem;
    }
    .add-entry-title {
      font-family:'DINBlack',sans-serif; font-size:.95rem; margin:0;
    }
    .add-entry-ctx {
      font-size:.75rem; color:#888; line-height:1.6;
      background:#f8f8f8; border-radius:6px; padding:.5rem .75rem;
      white-space:pre-line;
    }
    .add-entry-fields { display:flex; flex-direction:column; gap:.6rem; }
    .add-entry-fields label {
      display:flex; flex-direction:column; gap:.22rem;
      font-family:'DINBlack',sans-serif; font-size:.68rem;
      text-transform:uppercase; letter-spacing:.05em; color:#555;
    }
    .add-entry-fields input,
    .add-entry-fields select,
    .add-entry-fields textarea {
      font-family:'DINRegular',sans-serif; font-size:.83rem;
      border:1px solid #ccc; border-radius:6px;
      padding:.42rem .7rem; color:#111; outline:none; background:#fff;
    }
    .add-entry-fields input:focus,
    .add-entry-fields select:focus,
    .add-entry-fields textarea:focus { border-color:#1a1a2e; }
    .add-entry-fields textarea { resize:vertical; min-height:4.5rem; }
    /* iOS: date inputs ignore padding/font-size without this */
    .add-entry-fields input[type="date"] {
      -webkit-appearance:none; appearance:none;
      box-sizing:border-box;
      padding-top:0; padding-bottom:0;
      height:2.15rem;
    }
    .add-entry-row { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; }
    /* Game label at top of dialog */
    .add-game-label {
      font-family:'DINBlack',sans-serif; font-size:.8rem;
      color:#1a1a2e; letter-spacing:.03em; padding:.4rem .75rem;
      background:#f0f0f8; border-radius:6px;
    }
    /* Pub/contact locked display */
    .add-locked-ctx {
      font-size:.78rem; color:#555; background:#f8f8f8;
      border-radius:6px; padding:.45rem .75rem; line-height:1.5;
    }
    /* Select + "+ New" row */
    .add-select-row { display:flex; gap:.4rem; align-items:stretch; }
    .add-select-row select { flex:1; }
    .add-new-btn {
      font-family:'DINBlack',sans-serif; font-size:.65rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:#e8e8f0; color:#1a1a2e; border:none;
      border-radius:6px; padding:0 .65rem; cursor:pointer;
      white-space:nowrap; transition:background .15s;
    }
    .add-new-btn:hover { background:#1a1a2e; color:#fff; }
    /* Searchable combobox (publisher / contact) */
    .combo-wrap { position:relative; }
    .combo-wrap input {
      width:100%; box-sizing:border-box;
      font-family:'DINRegular',sans-serif; font-size:.83rem;
      border:1px solid #ccc; border-radius:6px;
      padding:.42rem .7rem; color:#111; outline:none; background:#fff;
      transition:border-color .15s;
    }
    .combo-wrap input:focus { border-color:#1a1a2e; }
    .combo-wrap input:disabled { opacity:.5; background:#f4f4f4; cursor:default; }
    .combo-drop {
      display:none; position:absolute; top:calc(100% + 2px); left:0; right:0; z-index:9999;
      background:#fff; border:1px solid #ccc; border-radius:6px;
      max-height:160px; overflow-y:auto;
      box-shadow:0 4px 12px rgba(0,0,0,.13);
    }
    .combo-drop.open { display:block; }
    .combo-opt {
      padding:.4rem .7rem; font-family:'DINRegular',sans-serif; font-size:.83rem;
      cursor:pointer; color:#111;
      text-transform:none; letter-spacing:normal;
    }
    .combo-opt:hover, .combo-opt.active { background:#1a1a2e; color:#fff; }
    /* Sub-dialog (New Publisher / New Contact) */
    .add-new-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.55); z-index:1100;
      align-items:center; justify-content:center; padding:1rem;
    }
    .add-new-overlay.open { display:flex; }
    .add-new-dialog {
      background:#fff; border-radius:10px;
      padding:1.3rem 1.4rem; width:min(360px,92vw);
      box-shadow:0 8px 32px rgba(0,0,0,.28);
      display:flex; flex-direction:column; gap:.75rem;
    }
    .add-new-title {
      font-family:'DINBlack',sans-serif; font-size:.88rem; margin:0;
    }
    .add-entry-actions { display:flex; justify-content:flex-end; align-items:center; gap:.6rem; margin-top:.1rem; }
    .add-email-btn {
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:none; color:#555; border:1px solid #ddd;
      border-radius:6px; padding:.42rem .9rem; cursor:pointer;
      margin-right:auto;
    }
    .add-email-btn:hover { background:#f0f4ff; color:#1a1a2e; border-color:#b0b8d0; }
    .add-cancel-btn {
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:none; color:#999; border:1px solid #ddd;
      border-radius:6px; padding:.42rem .9rem; cursor:pointer;
    }
    .add-cancel-btn:hover { background:#f5f5f5; color:#333; }
    .add-submit-btn {
      font-family:'DINBlack',sans-serif; font-size:.72rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:#1a1a2e; color:#fff; border:none;
      border-radius:6px; padding:.45rem .9rem;
      cursor:pointer; transition:background .15s;
    }
    .add-submit-btn:hover:not(:disabled) { background:#2d2d50; }
    .add-submit-btn:disabled { opacity:.45; cursor:default; }

    /* ── Entry row clickable ────────────────────────── */
    .entry-row { cursor:pointer; }
    .entry-row:hover { background:#f5f6fa; }
    .entry-notes a { color:inherit; text-decoration:underline; text-underline-offset:2px; }

    /* ── Notes dialog ────────────────────────────────── */
    .notes-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.45); z-index:1000;
      align-items:center; justify-content:center;
      padding:1rem;
    }
    .notes-overlay.open { display:flex; }
    .notes-dialog {
      background:#fff; border-radius:10px;
      padding:1.4rem 1.5rem; width:min(580px,94vw);
      max-height:82vh; overflow-y:auto;
      box-shadow:0 8px 32px rgba(0,0,0,.22);
      display:flex; flex-direction:column; gap:.75rem;
    }
    .notes-dialog-meta {
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      color:#aaa; text-transform:uppercase; letter-spacing:.05em;
      border-bottom:1px solid #f0f0f0; padding-bottom:.6rem;
    }
    .notes-field-row {
      display:grid; grid-template-columns:1fr 1fr 1fr; gap:.65rem;
    }
    .notes-field-label {
      display:flex; flex-direction:column; gap:.28rem;
      font-family:'DINBlack',sans-serif; font-size:.62rem;
      text-transform:uppercase; letter-spacing:.05em; color:#999;
    }
    .notes-field-input {
      font-family:'DINRegular',sans-serif; font-size:.82rem; color:#222;
      border:1px solid #ddd; border-radius:6px; padding:.38rem .55rem;
      outline:none; width:100%; min-width:0; box-sizing:border-box; background:#fff;
      transition:border-color .15s;
      -webkit-appearance:none; appearance:none;
    }
    .notes-field-input:focus { border-color:#1a1a2e; }
    /* Re-apply native arrow for <select> elements only */
    select.notes-field-input { -webkit-appearance:auto; appearance:auto; }
    .notes-edit-area {
      width:100%; min-height:6rem; font-family:'DINRegular',sans-serif;
      font-size:.88rem; line-height:1.7; color:#222;
      border:1px solid #ddd; border-radius:6px;
      padding:.5rem .65rem; resize:vertical; outline:none;
      box-sizing:border-box; transition:border-color .15s;
    }
    .notes-edit-area:focus { border-color:#1a1a2e; }
    .notes-dialog-actions {
      display:flex; gap:.5rem; align-items:center; justify-content:flex-end;
      border-top:1px solid #f0f0f0; padding-top:.6rem; margin-top:.1rem;
    }
    .notes-delete-btn {
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:none; color:#dc2626; border:1px solid rgba(220,38,38,.35);
      border-radius:6px; padding:.42rem .9rem; cursor:pointer;
      margin-right:auto; transition:background .15s, color .15s;
    }
    .notes-delete-btn:hover:not(:disabled) { background:#dc2626; color:#fff; }
    .notes-delete-btn:disabled { opacity:.4; cursor:default; }
    .notes-delete-confirm { margin-right:0; }
    .notes-confirm-msg {
      font-family:'DINRegular',sans-serif; font-size:.78rem;
      color:#dc2626; flex:1;
    }
    .notes-update-btn {
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:#1a1a2e; color:#fff; border:none;
      border-radius:6px; padding:.42rem .9rem; cursor:pointer;
      transition:background .15s;
    }
    .notes-update-btn:hover:not(:disabled) { background:#2d2d50; }
    .notes-update-btn:disabled { opacity:.4; cursor:default; }
    .notes-close {
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:none; color:#999; border:1px solid #ddd;
      border-radius:6px; padding:.42rem .9rem; cursor:pointer;
    }
    .notes-close:hover { background:#f5f5f5; color:#333; }
    .notes-close:disabled { opacity:.4; cursor:default; }

    /* ── Sync dialog ─────────────────────────────────── */
    .sync-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.45); z-index:1000;
      align-items:center; justify-content:center;
    }
    .sync-overlay.open { display:flex; }
    .sync-dialog {
      background:#fff; border-radius:10px;
      padding:1.4rem; width:min(480px,92vw);
      box-shadow:0 8px 32px rgba(0,0,0,.22);
      display:flex; flex-direction:column; gap:.75rem;
    }
    .sync-dialog h2 {
      font-family:'DINBlack',sans-serif; font-size:.95rem; margin:0;
    }
    .sync-log {
      background:#0f172a; border-radius:6px;
      padding:.75rem 1rem; min-height:6rem; max-height:14rem;
      overflow-y:auto; font-family:monospace; font-size:.75rem;
      line-height:1.6; color:#94a3b8;
    }
    .sync-log-line { display:block; }
    .sync-log-line.ok    { color:#4ade80; }
    .sync-log-line.skip  { color:#94a3b8; }
    .sync-log-line.error { color:#f87171; }
    .sync-log-line.info  { color:#60a5fa; }
    .sync-dialog-actions {
      display:flex; align-items:center; justify-content:flex-end; gap:.5rem;
    }
    .sync-update-btn {
      font-family:'DINBlack',sans-serif; font-size:.72rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:#16a34a; color:#fff; border:none;
      border-radius:6px; padding:.42rem .9rem;
      cursor:pointer; transition:background .15s;
    }
    .sync-update-btn:hover:not(:disabled) { background:#15803d; }
    .sync-update-btn:disabled { opacity:.45; cursor:default; }
    .sync-done-btn {
      font-family:'DINBlack',sans-serif; font-size:.72rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:#1a1a2e; color:#fff; border:none;
      border-radius:6px; padding:.45rem .9rem;
      cursor:pointer; align-self:flex-end;
      transition:background .15s;
    }
    .sync-done-btn:hover { background:#2d2d50; }
    .sync-done-btn:disabled { opacity:.45; cursor:default; }

    /* ── Game edit dialog ────────────────────────────── */
    .game-edit-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.45); z-index:1000;
      align-items:center; justify-content:center; padding:1rem;
      touch-action:none;
    }
    .game-edit-overlay.open { display:flex; }
    .game-edit-dialog {
      background:#fff; border-radius:10px;
      padding:1.4rem 1.5rem; width:min(580px,94vw);
      max-height:90vh; overflow-y:auto;
      -webkit-overflow-scrolling:touch; overscroll-behavior:contain;
      touch-action:pan-y;
      box-shadow:0 8px 32px rgba(0,0,0,.22);
      display:flex; flex-direction:column; gap:.65rem;
    }
    .game-edit-heading {
      font-family:'DINBlack',sans-serif; font-size:.85rem;
      color:#1a1a2e; letter-spacing:.03em;
      padding-bottom:.55rem; border-bottom:1px solid #f0f0f0;
    }
    .ge-section {
      font-family:'DINBlack',sans-serif; font-size:.62rem;
      text-transform:uppercase; letter-spacing:.06em; color:#bbb;
      margin-top:.2rem;
    }
    .ge-row { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; }
    .ge-label {
      display:flex; flex-direction:column; gap:.28rem;
      font-family:'DINBlack',sans-serif; font-size:.62rem;
      text-transform:uppercase; letter-spacing:.05em; color:#999;
    }
    .ge-input {
      font-family:'DINRegular',sans-serif; font-size:.82rem; color:#222;
      border:1px solid #ddd; border-radius:6px; padding:.38rem .55rem;
      outline:none; width:100%; box-sizing:border-box; background:#fff;
      transition:border-color .15s;
    }
    .ge-input:focus { border-color:#1a1a2e; }
    input[type="date"].ge-input {
      -webkit-appearance:none; appearance:none;
      line-height:1.4; min-height:2.1rem;
    }
    /* Designer comboboxes inside the game-edit dialog match .ge-input sizing */
    .ge-label .combo-wrap input {
      font-size:.82rem; color:#222; border-color:#ddd; padding:.38rem .55rem;
    }
    .ge-label .combo-wrap input:focus { border-color:#1a1a2e; }
    .ge-actions {
      display:flex; gap:.5rem; align-items:center; justify-content:flex-end;
      border-top:1px solid #f0f0f0; padding-top:.6rem; margin-top:.1rem;
    }
    .ge-save-btn {
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:#1a1a2e; color:#fff; border:none;
      border-radius:6px; padding:.42rem .9rem; cursor:pointer;
      transition:background .15s;
    }
    .ge-save-btn:hover:not(:disabled) { background:#2d2d50; }
    .ge-save-btn:disabled { opacity:.4; cursor:default; }
    .ge-cancel-btn {
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:none; color:#999; border:1px solid #ddd;
      border-radius:6px; padding:.42rem .9rem; cursor:pointer;
    }
    .ge-cancel-btn:hover { background:#f5f5f5; color:#333; }

    /* ── Copyable error dialog ───────────────────────── */
    .err-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.45); z-index:5000;
      align-items:center; justify-content:center; padding:1rem;
    }
    .err-overlay.open { display:flex; }

    /* ── Scroll-bleed prevention ─────────────────────────────────────────────────
       Make every overlay the scroll container so touches on the dialog or its
       backdrop never reach the list behind. The body is locked completely while
       any overlay is open.
    ────────────────────────────────────────────────────────────────────────────── */
    .di-overlay, .add-entry-overlay, .add-new-overlay, .notes-overlay,
    .sync-overlay, .game-edit-overlay, .err-overlay {
      overflow-y: auto;
      overscroll-behavior: contain;
    }
    body:has(.di-overlay.open, .add-entry-overlay.open, .add-new-overlay.open,
             .notes-overlay.open, .sync-overlay.open, .game-edit-overlay.open,
             .err-overlay.open) {
      overflow: hidden;
    }
    .err-dialog {
      background:#fff; border-radius:10px;
      padding:1.4rem 1.5rem; width:min(540px,94vw);
      box-shadow:0 8px 32px rgba(0,0,0,.22);
      display:flex; flex-direction:column; gap:.75rem;
    }
    .err-heading {
      font-family:'DINBlack',sans-serif; font-size:.9rem;
      text-transform:uppercase; letter-spacing:.05em; color:#c0392b;
    }
    .err-body {
      font-family:monospace; font-size:.78rem; line-height:1.5;
      background:#fafafa; border:1px solid #e0e0e0; border-radius:6px;
      padding:.75rem; white-space:pre-wrap; word-break:break-all;
      user-select:text; -webkit-user-select:text;
      max-height:260px; overflow-y:auto; cursor:text;
    }
    .err-actions {
      display:flex; justify-content:flex-end; gap:.5rem; align-items:center;
    }
    .err-copy-btn {
      font-family:'DINBlack',sans-serif; font-size:.68rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:#f0f0f0; color:#444; border:none; border-radius:6px;
      padding:.38rem .8rem; cursor:pointer; transition:background .15s;
    }
    .err-copy-btn:hover { background:#e0e0e0; }

    /* ── PitchBoard brand ───────────────────────────────────── */
    /* Used in the top-bar h1 on the dark (#1a1a2e) background  */
    .pb-pitch { color: #A8C8F0; }  /* soft blue  */
    .pb-board { color: #FF8A80; }  /* soft coral */

    /* ── View Page publish dialog (inherits sync-* styles) ─ */
    #vpOverlay { z-index:5000; }

    /* ── VP summary panel ────────────────────────────────── */
    .vp-summary {
      background:#f7f8fa; border-radius:6px;
      padding:.85rem 1rem;
      display:flex; flex-direction:column; gap:.55rem;
    }
    .vp-sum-name {
      font-family:'DINBlack',sans-serif;
      font-size:1.05rem; color:#1a1a2e; line-height:1.2;
    }
    .vp-sum-row {
      display:flex; gap:.5rem; align-items:baseline;
      font-size:.82rem; color:#333;
    }
    .vp-sum-label {
      font-family:'DINBlack',sans-serif;
      font-size:.65rem; text-transform:uppercase; letter-spacing:.05em;
      color:#999; flex-shrink:0; width:78px;
    }
    .vp-sum-row--wrap {
      align-items:flex-start; flex-wrap:wrap;
    }
    .vp-sum-row--wrap .vp-sum-label { padding-top:.15rem; }
    .vp-sum-desc {
      font-size:.8rem; color:#555; line-height:1.5;
      display:-webkit-box; -webkit-line-clamp:4; -webkit-box-orient:vertical;
      overflow:hidden; flex:1; min-width:0;
    }
    .vp-sum-counts {
      display:flex; gap:.45rem; flex-wrap:wrap; margin-top:.1rem;
    }
    .vp-sum-count {
      background:#e8e8f0; border-radius:4px;
      padding:.15rem .45rem;
      font-size:.73rem; color:#1a1a2e;
      font-family:'DINBlack',sans-serif;
    }
    .vp-sum-note {
      font-size:.78rem; color:#aaa; font-style:italic;
    }
    #vpLogPanel { margin-top:.25rem; }

    /* ── In-app view page overlay ───────────────────────── */
    #viewerOverlay {
      display:none; position:fixed; inset:0; z-index:6000;
      flex-direction:column; background:#fff;
    }
    #viewerOverlay.open { display:flex; }
    #viewerBar {
      display:flex; align-items:center; gap:.75rem;
      background:#1a1a2e; padding:.5rem .85rem;
      flex-shrink:0;
    }
    #viewerTitle {
      font-family:'DINBlack',sans-serif; font-size:.8rem;
      text-transform:uppercase; letter-spacing:.06em;
      color:#A8C8F0; flex:1; overflow:hidden;
      white-space:nowrap; text-overflow:ellipsis;
    }
    #viewerCloseBtn {
      background:none; border:none; cursor:pointer;
      color:#fff; font-size:1.3rem; line-height:1;
      padding:.1rem .25rem; opacity:.75;
      transition:opacity .15s;
    }
    #viewerCloseBtn:hover { opacity:1; }
    #viewerFrame {
      flex:1; border:none; width:100%; display:block;
    }
  </style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
  <div class="top-bar-inner">
    <div class="top-bar-left">
      <h1 onclick="deployOnly()"><span class="pb-pitch">Pitch</span><span class="pb-board">Board</span></h1>
      <p class="sub" id="subTitle">Loading…</p>
      <p class="sub version-tag" id="versionTag" style="display:none"></p>
    </div>
    <div class="view-toggle">
      <button id="btnDashboard"              onclick="setView('dashboard')">Dashboard</button>
      <button id="btnGame"      class="active" onclick="setView('game')">Games</button>
      <button id="btnPublisher"               onclick="setView('publisher')">Publishers</button>
    </div>
    <div class="account-menu-wrap">
      <button class="sync-btn" id="accountBtn" onclick="toggleAccountMenu()" title="Menu">
        <span class="sync-icon">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <circle cx="12" cy="7.5" r="4.5"/>
            <path d="M3.5 21c0-4.14 3.81-7.5 8.5-7.5s8.5 3.36 8.5 7.5"/>
          </svg>
        </span>
      </button>
      <div class="account-menu" id="accountMenu">
        <button class="account-menu-item" onclick="accountMenuProfile()">Profile</button>
        <button class="account-menu-item" onclick="accountMenuFetch()">Fetch</button>
        <button class="account-menu-item" onclick="accountMenuImport()">Import</button>
        <button class="account-menu-item" onclick="accountMenuHelp()">Help</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit-entry dialog -->
<div class="notes-overlay" id="notesOverlay" onclick="if(event.target===this)closeNotesDialog()">
  <div class="notes-dialog">
    <div class="notes-dialog-meta" id="notesDialogMeta"></div>
    <div class="notes-field-row">
      <label class="notes-field-label">Date
        <input type="date" id="editDate" class="notes-field-input" />
      </label>
      <label class="notes-field-label">Status
        <select id="editStatus" class="notes-field-input">
          <option value="">—</option>
          <option value="Pitched">Pitched</option>
          <option value="Interested">Interested</option>
          <option value="Passed">Passed</option>
          <option value="Gone Cold">Gone Cold</option>
          <option value="Signed">Signed</option>
          <option value="Published">Published</option>
          <option value="Returned">Returned</option>
        </select>
      </label>
      <label class="notes-field-label">Contact
        <select id="editContact" class="notes-field-input">
          <option value="">— unknown —</option>
        </select>
      </label>
    </div>
    <label class="notes-field-label" style="margin-top:.45rem">Event
      <input type="text" id="editEvent" class="notes-field-input" placeholder="e.g. GenCon 2025" />
    </label>
    <label class="notes-field-label" style="margin-top:.45rem">Notes
      <textarea id="notesEditArea" class="notes-edit-area"></textarea>
    </label>
    <div class="notes-dialog-actions" id="notesActions">
      <button class="notes-delete-btn" id="notesDeleteBtn" onclick="confirmDeleteEntry()">Delete</button>
      <button class="notes-close" onclick="closeNotesDialog()">Close</button>
      <button class="notes-update-btn" id="notesUpdateBtn" onclick="submitNotesUpdate()">Update</button>
    </div>
    <div class="notes-dialog-actions notes-confirm-actions" id="notesConfirmActions" style="display:none">
      <span class="notes-confirm-msg">Delete this pitch? This cannot be undone.</span>
      <button class="notes-close" onclick="cancelDeleteEntry()">Cancel</button>
      <button class="notes-delete-btn notes-delete-confirm" id="notesDeleteConfirmBtn" onclick="submitDeleteEntry()">Delete</button>
    </div>
  </div>
</div>

<!-- Designer info dialog -->
<div class="di-overlay" id="diOverlay" onclick="if(event.target===this)closeDiDialog()">
  <div class="di-dialog">
    <p class="di-not-found" id="diNotFound" style="display:none">Not in People list — fields will be created on save.</p>
    <div class="notes-field-row">
      <label class="notes-field-label" style="grid-column:1/-1">Name
        <input type="text" id="diTitle" class="notes-field-input" placeholder="Full name" />
      </label>
    </div>
    <div class="notes-field-row" style="grid-template-columns:1fr 1fr">
      <label class="notes-field-label">Email
        <input type="email" id="diEmail" class="notes-field-input" placeholder="email@example.com" />
      </label>
      <label class="notes-field-label">Company
        <div class="combo-wrap">
          <input type="text" id="diCompany" placeholder="Publisher / Studio" autocomplete="off" />
          <div class="combo-drop" id="diCompanyDrop"></div>
        </div>
      </label>
    </div>
    <label class="notes-field-label">Role
      <input type="text" id="diRole" class="notes-field-input" placeholder="e.g. Designer, Inventor Relations " />
    </label>
    <label class="notes-field-label">Notes
      <textarea id="diNotes" class="notes-edit-area" style="min-height:4rem"></textarea>
    </label>
    <div class="notes-dialog-actions">
      <span id="diStatus" style="flex:1;font-family:'DINRegular',sans-serif;font-size:.78rem;color:#e57"></span>
      <button class="notes-close" onclick="closeDiDialog()">Close</button>
      <button class="notes-update-btn" id="diUpdateBtn" onclick="submitDiUpdate()">Update</button>
    </div>
  </div>
</div>

<!-- Copyable error dialog -->
<div class="err-overlay" id="errOverlay" onclick="if(event.target===this)closeErrDialog()">
  <div class="err-dialog">
    <div class="err-heading">Error</div>
    <div class="err-body" id="errBody" tabindex="0"></div>
    <div class="err-actions">
      <button class="err-copy-btn" onclick="copyErrText()">Copy</button>
      <button class="ge-cancel-btn" onclick="closeErrDialog()">Close</button>
    </div>
  </div>
</div>

<!-- In-app view page overlay -->
<div id="viewerOverlay">
  <div id="viewerBar">
    <span id="viewerTitle">View Page</span>
    <button id="viewerCloseBtn" onclick="closeViewer()" title="Back to dashboard">&#x2715;</button>
  </div>
  <iframe id="viewerFrame" src="about:blank" allowfullscreen></iframe>
</div>

<!-- View Page dialog -->
<div class="sync-overlay" id="vpOverlay">
  <div class="sync-dialog">
    <h2 id="vpDialogTitle">View Page</h2>
    <!-- Summary panel — shown first -->
    <div id="vpSummaryPanel">
      <div class="vp-summary" id="vpSummary"></div>
    </div>
    <!-- Log panel — shown during/after sync -->
    <div id="vpLogPanel" style="display:none">
      <div class="sync-log" id="vpLog"></div>
    </div>
    <div class="sync-dialog-actions">
      <button class="sync-update-btn" id="vpSyncBtn"   style="margin-right:auto" onclick="vpDoSync()">Fetch</button>
      <!-- vpAddSheetBtn kept hidden in DOM; referenced by vpAddSheet() for state management -->
      <button id="vpAddSheetBtn" style="display:none" onclick="vpAddSheet()"></button>
      <button class="notes-close"   id="vpDoneBtn"     onclick="closeVpDialog()">Close</button>
      <button class="sync-done-btn" id="vpViewPageBtn" onclick="vpOpenViewPage()">View Page</button>
    </div>
  </div>
</div>

<!-- Game edit dialog -->
<div class="game-edit-overlay" id="gameEditOverlay" onclick="if(event.target===this)closeGameEditDialog()">
  <div class="game-edit-dialog">
    <div class="game-edit-heading" id="gameEditHeading">Edit Game</div>
    <label class="ge-label">Game Name
      <input type="text" id="geGameName" class="ge-input" />
    </label>
    <label class="ge-label">Tagline
      <input type="text" id="geTagline" class="ge-input" placeholder="Short subtitle or tagline…" />
    </label>
    <label class="ge-label">Description
      <textarea id="geDescription" class="ge-input" rows="3" placeholder="Short game description…" style="resize:vertical;min-height:4rem;line-height:1.4"></textarea>
    </label>
    <div class="ge-section">Designers</div>
    <div class="ge-row">
      <label class="ge-label">Designer 1
        <div class="combo-wrap">
          <input type="text" id="geDesigner1" placeholder="Search or enter name…" autocomplete="off" />
          <div class="combo-drop" id="geDesigner1Drop"></div>
        </div>
      </label>
      <label class="ge-label">Designer 2
        <div class="combo-wrap">
          <input type="text" id="geDesigner2" placeholder="Search or enter name…" autocomplete="off" />
          <div class="combo-drop" id="geDesigner2Drop"></div>
        </div>
      </label>
    </div>
    <div class="ge-row">
      <label class="ge-label">Designer 3
        <div class="combo-wrap">
          <input type="text" id="geDesigner3" placeholder="Search or enter name…" autocomplete="off" />
          <div class="combo-drop" id="geDesigner3Drop"></div>
        </div>
      </label>
      <label class="ge-label">Designer 4
        <div class="combo-wrap">
          <input type="text" id="geDesigner4" placeholder="Search or enter name…" autocomplete="off" />
          <div class="combo-drop" id="geDesigner4Drop"></div>
        </div>
      </label>
    </div>
    <div class="ge-section">Details</div>
    <div class="ge-row">
      <label class="ge-label">Status
        <div class="combo-wrap">
          <input type="text" id="geStatus" placeholder="e.g. Pitching, Signed…" autocomplete="off" />
          <div class="combo-drop" id="geStatusDrop"></div>
        </div>
      </label>
      <label class="ge-label">Date Started<input type="date" id="geDateStarted" class="ge-input" /></label>
    </div>
    <div class="ge-row">
      <label class="ge-label">Date Signed<input type="date" id="geDateSigned" class="ge-input" /></label>
      <label class="ge-label">Date Published<input type="date" id="geDatePublished" class="ge-input" /></label>
    </div>
    <div class="ge-section">Links</div>
    <div class="ge-row">
      <label class="ge-label">Rules URL<input type="url" id="geRules" class="ge-input" placeholder="https://…" /></label>
      <label class="ge-label">Play URL<input type="url" id="gePlay" class="ge-input" placeholder="https://…" /></label>
    </div>
    <div class="ge-row">
      <label class="ge-label">Print URL<input type="url" id="gePrint" class="ge-input" placeholder="https://…" /></label>
      <label class="ge-label">Sellsheet URL<input type="url" id="geSellsheet" class="ge-input" placeholder="https://…" /></label>
    </div>
    <div class="ge-row">
      <label class="ge-label">BGG / View URL<input type="url" id="geView" class="ge-input" placeholder="https://…" /></label>
      <label class="ge-label">Video URL<input type="url" id="geVideo" class="ge-input" placeholder="https://…" /></label>
    </div>
    <div class="ge-actions">
      <button class="ge-cancel-btn" onclick="closeGameEditDialog()">Cancel</button>
      <button class="ge-save-btn" id="geSaveBtn" onclick="submitGameEdit()">Save</button>
    </div>
  </div>
</div>

<!-- Add Entry dialog -->
<div class="add-entry-overlay" id="addEntryOverlay" onclick="if(event.target===this)closeAddDialog()">
  <div class="add-entry-dialog">
    <h2 class="add-entry-title" id="addEntryTitle">Add Entry</h2>
    <div class="add-game-label" id="addGameLabel"></div>
    <div class="add-entry-fields">
      <!-- Game picker — shown when no game is pre-set (e.g. New Pitch from publisher) -->
      <div id="addGameSection" style="display:none">
        <label>Game
          <div class="combo-wrap">
            <input type="text" id="addGameInput" placeholder="Search or choose game…" autocomplete="off" />
            <div class="combo-drop" id="gameComboDrop"></div>
          </div>
        </label>
      </div>
      <!-- Dropdowns shown when launched from game header -->
      <div id="addPubContactSection">
        <label>Publisher
          <div class="combo-wrap">
            <input type="text" id="addPublisherInput" placeholder="Search or enter publisher…" autocomplete="off" />
            <div class="combo-drop" id="pubComboDrop"></div>
          </div>
        </label>
        <label style="margin-top:.5rem">Contact
          <div class="combo-wrap">
            <input type="text" id="addContactInput" placeholder="Optional contact…" autocomplete="off" />
            <div class="combo-drop" id="contactComboDrop"></div>
          </div>
        </label>
      </div>
      <!-- Read-only display when launched from a contact's Add button -->
      <div id="addLockedSection" class="add-locked-ctx" style="display:none"></div>
      <div class="add-entry-row" style="margin-top:.5rem">
        <label>Date
          <input type="date" id="addDate" />
        </label>
        <label>Status
          <select id="addStatus">
            <option value="Pitched">Pitched</option>
            <option value="Interested">Interested</option>
            <option value="Passed">Passed</option>
            <option value="Gone Cold">Gone Cold</option>
            <option value="Returned">Returned</option>
            <option value="Signed">Signed</option>
            <option value="Published">Published</option>
          </select>
        </label>
      </div>
      <label>Event
        <input type="text" id="addEvent" placeholder="e.g. Gen Con, PaxU, ..." />
      </label>
      <label>Notes
        <textarea id="addNotes" placeholder="Optional notes…"></textarea>
      </label>
    </div>
    <div class="add-entry-actions">
      <button class="add-email-btn" onclick="sendEmailFromAddDialog()" title="Open email with game info">&#9993; Send Email</button>
      <button class="add-cancel-btn" onclick="closeAddDialog()">Cancel</button>
      <button class="add-submit-btn" id="addSubmitBtn" onclick="submitAddEntry()">Add</button>
    </div>
  </div>
</div>

<!-- New Publisher / New Contact sub-dialog -->
<div class="add-new-overlay" id="addNewOverlay" onclick="if(event.target===this)closeAddNew()">
  <div class="add-new-dialog">
    <h3 class="add-new-title" id="addNewTitle">New Publisher</h3>
    <div class="add-entry-fields" id="addNewFields"></div>
    <div class="add-entry-actions">
      <button class="add-cancel-btn" onclick="closeAddNew()">Cancel</button>
      <button class="add-submit-btn" id="addNewSubmitBtn" onclick="submitAddNew()">Add</button>
    </div>
  </div>
</div>


<!-- Profile dialog -->
<div class="sync-overlay" id="profileOverlay">
  <div class="sync-dialog" style="width:min(400px,94vw)">
    <h2>Profile</h2>
    <div style="display:flex;flex-direction:column;gap:.65rem;margin:.25rem 0 .5rem">
      <label class="ge-label">Name<input type="text"  id="profileName"  class="ge-input" /></label>
      <label class="ge-label">Email<input type="email" id="profileEmail" class="ge-input" /></label>
      <label class="ge-label">Phone<input type="tel"   id="profilePhone" class="ge-input" /></label>
    </div>
    <div class="sync-log" id="profileLog" style="display:none"></div>
    <div class="sync-dialog-actions">
      <button class="notes-close" id="profileCancelBtn" onclick="closeProfileDialog()">Cancel</button>
      <button class="sync-update-btn" id="profileSaveBtn" onclick="submitProfile()">Save</button>
    </div>
  </div>
</div>

<!-- Import dialog -->
<div class="sync-overlay" id="importOverlay">
  <div class="sync-dialog import-dialog">
    <h2>Import Pitches</h2>
    <div class="import-body" id="importDialogBody"></div>
    <div class="sync-log" id="importLog" style="display:none"></div>
    <div class="sync-dialog-actions">
      <button class="notes-close" id="importCancelBtn" onclick="closeImportDialog()">Cancel</button>
      <button class="sync-update-btn" id="importConfirmBtn" onclick="confirmImport()">Import</button>
    </div>
  </div>
</div>

<!-- Share URL dialog -->
<div class="sync-overlay" id="shareUrlOverlay">
  <div class="sync-dialog" style="width:min(480px,94vw)">
    <h2>Share</h2>

    <p style="color:#888;font-size:.78rem;margin:.1rem 0 .35rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Share Pitches with Collaborators</p>
    <p style="color:#888;font-size:.78rem;margin:.1rem 0 .5rem">Send this link so collaborators can import your pitch data into their PitchBoard.</p>
    <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:1rem">
      <input type="text" id="shareUrlInput" class="ge-input" readonly style="flex:1;font-size:.72rem;font-family:monospace" />
      <button class="sync-update-btn" id="shareUrlCopyBtn" onclick="copyShareUrl()">Copy</button>
    </div>

    <p style="color:#888;font-size:.78rem;margin:.1rem 0 .35rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Share Game Page</p>
    <p style="color:#888;font-size:.78rem;margin:.1rem 0 .5rem">Public link to this game's view page.</p>
    <div style="display:flex;gap:.5rem;align-items:center">
      <input type="text" id="shareGamePageInput" class="ge-input" readonly style="flex:1;font-size:.72rem;font-family:monospace" />
      <button class="sync-update-btn" id="shareGamePageCopyBtn" onclick="copyGamePageUrl()">Copy</button>
    </div>

    <div class="sync-dialog-actions" style="margin-top:.75rem">
      <button class="notes-close" onclick="closeShareUrlDialog()">Close</button>
    </div>
  </div>
</div>

<!-- Import URL dialog -->
<div class="sync-overlay" id="importUrlOverlay">
  <div class="sync-dialog" style="width:min(480px,94vw)">
    <h2>Import from Link</h2>
    <label class="ge-label" style="display:block;margin:.1rem 0 .65rem">Paste a share link
      <input type="url" id="importUrlInput" class="ge-input" placeholder="https://…" />
    </label>
    <div class="sync-log" id="importUrlLog" style="display:none;margin-bottom:.5rem"></div>
    <div class="sync-dialog-actions">
      <button class="notes-close" onclick="closeImportUrlDialog()">Cancel</button>
      <button class="sync-update-btn" id="importUrlLoadBtn" onclick="loadImportUrl()">Load</button>
    </div>
  </div>
</div>

<!-- Fetch dialog -->
<div class="sync-overlay" id="syncOverlay">
  <div class="sync-dialog">
    <h2 id="syncDialogTitle">Fetching…</h2>
    <p id="syncDialogSub" style="color:#888;font-size:.78rem;margin:.1rem 0 .4rem">Fetch data from your spreadsheet</p>
    <div class="sync-log" id="syncLog"></div>
    <div class="sync-dialog-actions">
      <button class="notes-close" id="syncDoneBtn" disabled onclick="closeSyncDialog()">Close</button>
    </div>
  </div>
</div>

<div class="summary-bar" id="summaryBar"></div>
<div class="search-bar" id="searchBar">
  <div class="search-wrap" id="searchWrap">
    <input type="text" id="searchInput" placeholder="Search games, publishers, contacts…" oninput="applySearch()" />
    <button class="search-clear" id="searchClear" onclick="clearSearch()" title="Clear">✕</button>
  </div>
  <div class="sort-toggle">
    <button id="btnSortDate"  class="active" onclick="setSort('date')">Date ↓</button>
    <button id="btnSortAlpha"            onclick="setSort('alpha')">A–Z</button>
  </div>
  <button class="new-game-btn" onclick="openNewGameDialog()">+ New Game</button>
</div>
<div class="content" id="content"><div class="empty">Loading…</div></div>

<script>
// ── Routing ───────────────────────────────────────────
function getSheetId() {
  var parts = window.location.pathname.split('/').filter(Boolean);
  var idx = parts.indexOf('sheets');
  if (idx >= 0 && parts[idx+1]) return parts[idx+1];
  var di = parts.lastIndexOf('dashboard');
  if (di > 0) return parts[di-1];
  var m = window.location.search.match(/[?&]id=([^&]+)/);
  return m ? m[1] : '';
}
var sheet_Id = getSheetId();
var APP_BASE = document.querySelector('base').getAttribute('href');
var BASE     = APP_BASE + 'sheets/' + sheet_Id + '/';

// ── State ─────────────────────────────────────────────
var currentView     = 'game';
var currentSort     = 'date';   // 'date' | 'alpha'
var sortDir         = { date: -1, alpha: 1 };  // -1=desc(newest/Z-A), 1=asc(oldest/A-Z)
var allPitches      = [];
var filteredPitches = [];
var searchQuery     = '';
var activeFilters   = {};   // keys: 'signed', 'published'
var peopleIndex     = {};   // "Name|Company" → email
var peopleData      = {};   // Name → full person record {Name, Email, Company, Role, Notes}
var gamesIndex      = {};   // Game name → {Designers, …}
var totalGameCount  = 0;
var totalPubCount   = 0;
var myName  = '';
var myPhone = '';
var myEmail = '';

// ── Helpers ───────────────────────────────────────────
function statusClass(s) {
  s = (s||'').toLowerCase();
  if (s==='signed')     return 'signed';
  if (s==='interested') return 'interested';
  if (s==='passed')     return 'passed';
  if (s==='gone cold')  return 'gone-cold';
  return 'pitched';
}

// A game is signed if games.json has a Date Signed or Status=Signed, or any pitch entry has Status=Signed —
// UNLESS any publisher's latest status is Returned (game came back to the designer).
function isGameSigned(gameName, allEntries) {
  var entries = allEntries || [];
  // Per-publisher check: if any publisher's most recent status-bearing entry is Returned, not signed
  var byPub = {};
  entries.forEach(function(e) {
    var pub = (e.Publisher || '(Unknown)').trim();
    if (!byPub[pub]) byPub[pub] = [];
    byPub[pub].push(e);
  });
  var anyReturned = Object.keys(byPub).some(function(pub) {
    var pe = byPub[pub].filter(function(e){ return (e.Status||'').trim(); });
    if (!pe.length) return false;
    var latest = pe.slice().sort(function(a,b){ return new Date(b.Date)-new Date(a.Date); })[0];
    return (latest.Status||'').toLowerCase() === 'returned';
  });
  if (anyReturned) return false;
  var info = gamesIndex[gameName] || {};
  if ((info['Date Signed'] || '').trim()) return true;
  if ((info['Status'] || '').toLowerCase() === 'signed') return true;
  return entries.some(function(e) {
    return (e.Status || '').toLowerCase() === 'signed' ||
           (e['Date Signed'] || '').trim();
  });
}

// A game is published if games.json has a Date Published or Status=Published, or any pitch entry has Status=Published
function isGamePublished(gameName, allEntries) {
  var info = gamesIndex[gameName] || {};
  if ((info['Date Published'] || '').trim()) return true;
  if ((info['Status'] || '').toLowerCase() === 'published') return true;
  return (allEntries || []).some(function(e) {
    return (e.Status || '').toLowerCase() === 'published';
  });
}

function latestEntry(entries) {
  return entries.slice().sort(function(a,b){ return new Date(b.Date)-new Date(a.Date); })[0] || {};
}

function ageTag(entries) {
  // If the most recent communication ended in anything other than Pitched/Interested,
  // there is nothing to follow up on — suppress the age pill.
  var overall = latestEntry(entries);
  var ls = (overall.Status||'').toLowerCase();
  if (ls !== 'pitched' && ls !== 'interested') return '';
  // Find most recent Interested entry for age calculation
  var ints = entries.filter(function(e){ return (e.Status||'').toLowerCase()==='interested'; });
  if (!ints.length) return '';
  var latest = latestEntry(ints);
  if (!latest.Date) return '';
  var d = new Date(latest.Date), now = new Date();
  var months = (now.getFullYear()-d.getFullYear())*12 + (now.getMonth()-d.getMonth());
  if (months >= 6) return '<span class="badge badge-age-6mo">6mo+</span>';
  if (months >= 3) return '<span class="badge badge-age-3mo">3mo+</span>';
  return '';
}

// Age tag for game header: checks each non-passed publisher independently;
// shows 3mo+ and/or 6mo+ if any publisher falls into that bracket
function gameAgeTag(pubMap) {
  var now = new Date();
  var has3mo = false, has6mo = false;
  Object.keys(pubMap).forEach(function(p) {
    var pubEntries = [];
    Object.keys(pubMap[p]).forEach(function(c){
      pubMap[p][c].forEach(function(e){ pubEntries.push(e); });
    });
    if (!pubEntries.length) return;
    var latest = latestEntry(pubEntries);
    var ls = (latest.Status||'').toLowerCase();
    if (ls !== 'pitched' && ls !== 'interested') return;
    if (!latest.Date) return;
    var months = (now.getFullYear() - new Date(latest.Date).getFullYear()) * 12
               + (now.getMonth()    - new Date(latest.Date).getMonth());
    if (months >= 6) has6mo = true;
    else if (months >= 3) has3mo = true;
  });
  if (has6mo) return '<span class="badge badge-age-6mo">6mo+</span>';
  if (has3mo) return '<span class="badge badge-age-3mo">3mo+</span>';
  return '';
}

function escHtml(s) {
  return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Convert [title, url], [label](url), and bare https:// links to <a> tags.
// stopProp=true adds onclick="event.stopPropagation()" so clicks on links inside
// a clickable parent (e.g. the notes span) don't also trigger the parent handler.
function renderLinks(text, stopProp) {
  if (!text) return '';
  var extra = stopProp ? ' onclick="event.stopPropagation()"' : '';
  var pattern = /\[([^\],]+),\s*(https?:\/\/[^\]]+)\]|\[([^\]]*)\]\((https?:\/\/[^)]+)\)|https?:\/\/\S+/g;
  var out = '', last = 0, m;
  while ((m = pattern.exec(text)) !== null) {
    out += escHtml(text.slice(last, m.index));
    var label, url;
    if (m[1] !== undefined) {
      label = m[1].trim();
      url   = m[2].trim();
    } else if (m[3] !== undefined) {
      label = m[3];
      url   = m[4];
    } else {
      var raw = m[0].replace(/[.,;:!?)'\"]+$/, '');
      label = raw; url = raw;
    }
    out += '<a href="' + escHtml(url) + '" target="_blank" rel="noopener"' + extra + '>'
         + escHtml(label) + '</a>';
    last = m.index + m[0].length;
  }
  out += escHtml(text.slice(last));
  return out;
}

function fmtMonYr(d) {
  if (!d || isNaN(d.getTime())) return '';
  return d.toLocaleDateString('en-US', { month:'short', year:'numeric' });
}

function gameStatusDateStr(gameName, isPublished, isSigned) {
  var info = gamesIndex[gameName] || {};
  var raw = isPublished ? (info['Date Published'] || '').trim()
          : isSigned    ? (info['Date Signed']    || '').trim()
          : '';
  if (!raw) return '';
  var dt = new Date(raw);
  return isNaN(dt.getTime()) ? '' : fmtMonYr(dt);
}

// ── Build summary bar ─────────────────────────────────
function buildSummary(pitches) {
  // Build game→publisher→entries map for pair-level counting
  var gameEntryMap = {}, gamePubMap = {}, pubSet = {};
  pitches.forEach(function(r) {
    var g = r.Game || '(Unknown)';
    var p = r.Publisher || '(Unknown)';
    if (r.Publisher) pubSet[r.Publisher] = 1;
    if (!gameEntryMap[g]) gameEntryMap[g] = [];
    gameEntryMap[g].push(r);
    if (!gamePubMap[g]) gamePubMap[g] = {};
    if (!gamePubMap[g][p]) gamePubMap[g][p] = [];
    gamePubMap[g][p].push(r);
  });

  // Also include games in gamesIndex that may not have pitches yet
  Object.keys(gamesIndex).forEach(function(n){ if (!gameEntryMap[n]) gameEntryMap[n] = []; });

  // Count per game-publisher pair by latest status
  var pairCounts = {pitched:0, interested:0, passed:0, gonecold:0};
  Object.keys(gamePubMap).forEach(function(g) {
    Object.keys(gamePubMap[g]).forEach(function(p) {
      var latest = latestEntry(gamePubMap[g][p]);
      var s = (latest.Status||'').toLowerCase();
      if (s === 'interested') pairCounts.interested++;
      else if (s === 'passed') pairCounts.passed++;
      else if (s === 'gone cold') pairCounts.gonecold++;
      else if (s) pairCounts.pitched++;
    });
  });

  // Count signed and published at the game level
  var signedGames = 0, publishedGames = 0;
  Object.keys(gameEntryMap).forEach(function(name) {
    if (isGamePublished(name, gameEntryMap[name])) publishedGames++;
    else if (isGameSigned(name, gameEntryMap[name])) signedGames++;
  });

  // Store for view-toggle button labels
  totalGameCount = Object.keys(gameEntryMap).length;
  totalPubCount  = Object.keys(pubSet).length;
  document.getElementById('btnGame').textContent      = totalGameCount + ' Games';
  document.getElementById('btnPublisher').textContent = totalPubCount  + ' Publishers';

  function filterBtn(cls, key, label, count) {
    if (!count) return '';
    var active = activeFilters[key] ? ' filter-active' : '';
    return '<button class="pill ' + cls + active + '" onclick="toggleFilter(\'' + key + '\')">' + count + ' ' + label + '</button>';
  }

  var html =
    filterBtn('pill-pitched', 'pitched',   'pitched',    pairCounts.pitched) +
    filterBtn('pill-int',     'interested','interested', pairCounts.interested) +
    filterBtn('pill-passed',  'passed',    'passed',     pairCounts.passed) +
    filterBtn('pill-cold',    'gonecold',  'gone cold',  pairCounts.gonecold) +
    filterBtn('pill-signed',  'signed',    'signed',     signedGames) +
    filterBtn('pill-published','published','published',  publishedGames);
  document.getElementById('summaryBar').innerHTML = html;
}

// ── Filter toggle ─────────────────────────────────────
function toggleFilter(key) {
  if (activeFilters[key]) {
    delete activeFilters[key];
  } else {
    activeFilters[key] = true;
  }
  buildSummary(allPitches);
  buildView();
}

// ── Search ────────────────────────────────────────────
function applySearch() {
  var val = document.getElementById('searchInput').value;
  document.getElementById('searchWrap').classList.toggle('has-text', val.length > 0);
  searchQuery = val.toLowerCase().trim();
  filteredPitches = searchQuery
    ? allPitches.filter(function(r) {
        return (r.Game||'').toLowerCase().includes(searchQuery)
            || (r.Publisher||'').toLowerCase().includes(searchQuery)
            || (r.Contact||'').toLowerCase().includes(searchQuery)
            || (r.Notes||'').toLowerCase().includes(searchQuery);
      })
    : allPitches;
  buildView();
}
function clearSearch() {
  var inp = document.getElementById('searchInput');
  inp.value = '';
  applySearch();
  inp.focus();
}

// ── View switcher ─────────────────────────────────────
function setView(v) {
  currentView = v;
  document.getElementById('btnDashboard').classList.toggle('active', v==='dashboard');
  document.getElementById('btnGame').classList.toggle('active',      v==='game');
  document.getElementById('btnPublisher').classList.toggle('active', v==='publisher');
  var isDash = v === 'dashboard';
  document.getElementById('summaryBar').style.display = isDash ? 'none' : '';
  document.getElementById('searchBar').style.display  = isDash ? 'none' : '';
  buildView();
}

// ── Sort switcher ─────────────────────────────────────
function setSort(s) {
  if (s === currentSort) {
    sortDir[s] *= -1;  // toggle direction
  } else {
    currentSort = s;
  }
  var dArr  = sortDir.date  === -1 ? ' ↓' : ' ↑';
  var aArr  = sortDir.alpha ===  1 ? 'A–Z' : 'Z–A';
  document.getElementById('btnSortDate').textContent  = 'Date' + dArr;
  document.getElementById('btnSortAlpha').textContent = aArr;
  document.getElementById('btnSortDate').classList.toggle('active',  currentSort==='date');
  document.getElementById('btnSortAlpha').classList.toggle('active', currentSort==='alpha');
  buildView();
}

// ── Entry row ─────────────────────────────────────────
function entryRow(e) {
  var sc    = statusClass(e.Status);
  var notes = e.Notes || '';
  var contact = (e.Contact && e.Contact !== '(Unknown)') ? e.Contact : '';
  return '<div class="entry-row"' +
    ' data-notes="'     + escHtml(notes)            + '"' +
    ' data-game="'      + escHtml(e.Game      || '') + '"' +
    ' data-publisher="' + escHtml(e.Publisher  || '') + '"' +
    ' data-contact="'   + escHtml(e.Contact    || '') + '"' +
    ' data-date="'      + escHtml(e.Date       || '') + '"' +
    ' data-event="'     + escHtml(e.Event      || '') + '"' +
    ' data-status="'    + escHtml(e.Status     || '') + '"' +
    ' onclick="rowClick(this)">' +
    '<span class="entry-date">'    + escHtml(e.Date)  + '</span>' +
    '<span class="entry-contact">' + escHtml(contact) + '</span>' +
    '<span class="entry-event">'   + escHtml(e.Event) + '</span>' +
    '<span class="entry-status badge badge-' + sc + '">' + escHtml(e.Status||'—') + '</span>' +
    '<span class="entry-notes">'   + renderLinks(notes, true) + '</span>' +
    '</div>';
}

// ── Sub-group (contact or game label) ─────────────────
function subGroup(label, email, gameTitle, entries) {
  var sorted = entries.slice().sort(function(a,b){ return new Date(a.Date)-new Date(b.Date); });
  var mailHref = email ? mailtoHref(email, gameTitle) : '';
  var emailBtn = mailHref
    ? '<a class="contact-email-btn" href="' + mailHref + '">&#9993; Email</a>'
    : '';
  return '<div class="sub-group">' +
    '<div class="sub-label"><span>' + escHtml(label) + '</span>' + emailBtn + '</div>' +
    sorted.map(entryRow).join('') +
    '</div>';
}

// ── GAME VIEW ─────────────────────────────────────────
function buildGameView(pitches) {
  // Group by Game → Publisher → Contact
  var games = {};
  pitches.forEach(function(r) {
    var g = r.Game || '(Unknown)';
    var p = r.Publisher || '(Unknown)';
    var c = r.Contact   || '(Unknown)';
    if (!games[g]) games[g] = {};
    if (!games[g][p]) games[g][p] = {};
    if (!games[g][p][c]) games[g][p][c] = [];
    games[g][p][c].push(r);
  });

  // Add games from games.json that have no pitch entries yet
  // When searching, only include unpitched games whose name or designers match the query
  Object.keys(gamesIndex).forEach(function(name) {
    if (games[name]) return; // already has pitches
    if (searchQuery) {
      var info = gamesIndex[name];
      var designers = ['Designer1','Designer2','Designer3','Designer4']
        .map(function(f){ return (info[f]||'').toLowerCase(); }).join(' ');
      var matchesSearch = name.toLowerCase().includes(searchQuery) ||
                          designers.includes(searchQuery);
      if (!matchesSearch) return;
    }
    games[name] = {};
  });

  // Apply active filters (any combination, OR logic)
  var hasFilter = activeFilters.signed || activeFilters.published ||
                  activeFilters.interested || activeFilters.passed || activeFilters.pitched || activeFilters.gonecold;
  if (hasFilter) {
    Object.keys(games).forEach(function(name) {
      var entries = [];
      Object.keys(games[name]).forEach(function(p) {
        Object.keys(games[name][p]).forEach(function(c) {
          games[name][p][c].forEach(function(e){ entries.push(e); });
        });
      });
      var pub = isGamePublished(name, entries);
      var sig = !pub && isGameSigned(name, entries);
      var keep = false;
      if (activeFilters.published && pub) keep = true;
      if (activeFilters.signed    && sig) keep = true;
      if (!keep && (activeFilters.interested || activeFilters.passed || activeFilters.pitched || activeFilters.gonecold)) {
        // Check if any publisher's latest status matches an active status filter
        keep = Object.keys(games[name]).some(function(p) {
          var pe = [];
          Object.keys(games[name][p]).forEach(function(c){
            games[name][p][c].forEach(function(e){ pe.push(e); });
          });
          var s = (latestEntry(pe).Status||'').toLowerCase();
          return (activeFilters.interested && s === 'interested') ||
                 (activeFilters.passed     && s === 'passed') ||
                 (activeFilters.pitched    && s === 'pitched') ||
                 (activeFilters.gonecold   && s === 'gone cold');
        });
      }
      if (!keep) delete games[name];
    });
  }

  // Sort games
  var gameNames = Object.keys(games).sort(function(a,b) {
    if (currentSort === 'alpha') return sortDir.alpha * a.localeCompare(b);
    // Date sort; unpitched (no dates) fall to end, then alpha
    function maxDate(g) {
      var dates = [];
      Object.keys(games[g]).forEach(function(p) {
        Object.keys(games[g][p]).forEach(function(c) {
          games[g][p][c].forEach(function(e){ dates.push(new Date(e.Date)); });
        });
      });
      return dates.length ? Math.max.apply(null, dates) : 0;
    }
    var da = maxDate(a), db = maxDate(b);
    if (db !== da) return sortDir.date * (da - db);
    return a.localeCompare(b);
  });

  var html = '';
  gameNames.forEach(function(g) {
    var allEntries = [];
    Object.keys(games[g]).forEach(function(p) {
      Object.keys(games[g][p]).forEach(function(c) {
        games[g][p][c].forEach(function(e){ allEntries.push(e); });
      });
    });
    var at = gameAgeTag(games[g]);

    // Determine game-level status badge (never show PASSED; INTERESTED if any pub is currently interested)
    var published = isGamePublished(g, allEntries);
    var signed    = !published && isGameSigned(g, allEntries);
    var gameStatusBadge = '';
    if (published) {
      gameStatusBadge = '<span class="badge badge-published">Published</span>';
    } else if (signed) {
      gameStatusBadge = '<span class="badge badge-signed">Signed</span>';
    } else {
      // Check if any publisher's most-recent status is Interested
      var anyInterested = Object.keys(games[g]).some(function(pub) {
        var pe = [];
        Object.keys(games[g][pub]).forEach(function(c){ games[g][pub][c].forEach(function(e){ pe.push(e); }); });
        return (latestEntry(pe).Status||'').toLowerCase() === 'interested';
      });
      if (anyInterested) {
        gameStatusBadge = '<span class="badge badge-interested">Interested</span>';
      } else {
        // Show Pitched only (suppress Passed)
        var anyPitched = allEntries.some(function(e){ return (e.Status||'').toLowerCase() === 'pitched'; });
        if (anyPitched) gameStatusBadge = '<span class="badge badge-pitched">Pitched</span>';
      }
    }

    html += '<div class="card">';
    var gameInfo  = gamesIndex[g] || {};
    var designerNames = ['Designer1','Designer2','Designer3','Designer4']
      .map(function(f){ return (gameInfo[f]||'').trim(); })
      .filter(function(v){ return v; });
    var designers = designerNames.join(', ');
    var gameDateHtml = '';
    html += '<div class="card-header" onclick="toggleCard(this)">';
    html += '<span class="card-title">' + escHtml(g) + '</span>';
    html += '<span class="card-badges">' + (published || signed ? '' : at) + gameStatusBadge + gameDateHtml + '</span>';
    html += '<span class="card-chevron">▼</span>';
    html += '</div>';

    // ── Game link pills (Rules / Play / Print / View) ──
    var gameLinkPills = (function() {
      function gfield(keys) {
        for (var i = 0; i < keys.length; i++) {
          var v = (gameInfo[keys[i]] || '').trim();
          if (v) return v;
        }
        return '';
      }
      // Normalize a raw sheet value into an absolute URL.
      // Handles Markdown [label](url) syntax and bare domains (no protocol).
      function absUrl(raw) {
        if (!raw) return '';
        var s = String(raw).trim();
        var md = s.match(/^\[.*?\]\((.+)\)\s*$/);
        if (md) s = md[1].trim();
        var br = s.match(/^\[(.+)\]\s*$/);
        if (br) s = br[1].trim();
        if (!s) return '';
        return /^https?:\/\//i.test(s) ? s : 'https://' + s;
      }
      var playbookId = gfield(['Playbook Sheet ID','Playbook ID','Sheet ID']);
      var linkDefs = [
        { label:'Rules',     url: absUrl(gfield(['Rules','Rules URL','Rules Link','Link Rules'])) },
        { label:'Play',      url: absUrl(gfield(['Play','Play URL','Play Link','Link Play'])) },
        { label:'Print',     url: absUrl(gfield(['Print','Print URL','Print Link','Link Print'])) },
        { label:'Sellsheet', url: absUrl(gfield(['Sellsheet URL','Sellsheet','Sell Sheet URL','Sell Sheet','Link Sellsheet'])) },
        { label:'View',      url: absUrl(gfield(['View','View URL','Link View','Website','BGG','BGG URL','BGG Link'])) },
        { label:'Video',     url: absUrl(gfield(['Video','Video URL','Video Link','Link Video','YouTube','YouTube URL'])) },
        { label:'Info',      url: playbookId ? window.location.origin + APP_BASE + playbookId + '/view' : '' }
      ];
      var out = '';
      linkDefs.forEach(function(lp) {
        if (lp.url) {
          out += '<a class="game-link-pill" href="' + escHtml(lp.url) +
                 '" target="_blank" rel="noopener noreferrer">' + escHtml(lp.label) + '</a>';
        }
      });
      return out;
    })();

    html += '<div class="card-body-wrap"><div class="card-body">';
    html += '<div class="game-sub-bar">';
    html += '<div class="game-links">';
    if (designerNames.length) {
      html += '<span class="game-links-designers">';
      designerNames.forEach(function(dn, i) {
        if (i > 0) html += '<span style="opacity:.5">, </span>';
        html += '<button class="designer-chip" data-designer="' + escHtml(dn) + '"' +
                ' onclick="event.stopPropagation();openDiDialog(this.getAttribute(\'data-designer\'))">' +
                escHtml(dn) + '</button>';
      });
      html += '</span>';
    }
    if (gameLinkPills) html += '<div class="game-link-pills">' + gameLinkPills + '</div>';
    html += '</div>'; // .game-links
    html += '<div class="game-actions">';
    html += '<button class="game-action-btn" data-game="' + escHtml(g) + '" onclick="event.stopPropagation();addBtnClick(this)">New Pitch</button>';
    html += '<button class="game-action-btn" data-game="' + escHtml(g) + '" onclick="event.stopPropagation();editGameClick(this)">Edit Game</button>';
    html += '<button class="game-action-btn" data-game="' + escHtml(g) + '" onclick="event.stopPropagation();viewPageClick(this)">View Page</button>';
    html += '<button class="game-action-btn" data-game="' + escHtml(g) + '" onclick="event.stopPropagation();shareGame(this.getAttribute(\'data-game\'))">Share</button>';
    html += '</div>'; // .game-actions
    html += '</div>'; // .game-sub-bar

    // Sort publishers alphabetically
    var pubNames = Object.keys(games[g]).sort(function(a,b){ return a.localeCompare(b); });

    if (pubNames.length === 0) {
      html += '<div style="padding:.75rem 1rem;color:#aaa;font-size:.8rem;font-style:italic">No pitches yet</div>';
    }

    pubNames.forEach(function(p, pubIdx) {
      // Flatten all entries across contacts for this publisher
      var contacts = Object.keys(games[g][p]);
      var pubEntries = [];
      contacts.forEach(function(c){ games[g][p][c].forEach(function(e){ pubEntries.push(e); }); });
      var pubLatest  = latestEntry(pubEntries);
      var pubStatus  = (pubLatest.Status||'').toLowerCase();
      var pubAgeTag  = ageTag(pubEntries);

      var isPassed   = pubStatus === 'passed';
      var isGoneCold = pubStatus === 'gone cold';
      var isSigned   = pubStatus === 'signed';

      // Publisher status badge
      var pubBadge = '';
      if (isPassed) {
        pubBadge = '<span class="badge badge-passed" style="margin-right:.75rem">Passed</span>';
      } else if (isGoneCold) {
        pubBadge = '<span class="badge badge-gone-cold" style="margin-right:.75rem">Gone Cold</span>';
      } else if (isSigned) {
        pubBadge = '<span class="badge badge-signed" style="margin-right:.75rem">Signed</span>';
      } else if (pubStatus === 'interested') {
        pubBadge = '<span class="badge badge-interested" style="margin-right:.75rem">Interested</span>';
      } else if (pubStatus === 'returned') {
        pubBadge = '<span class="badge badge-returned" style="margin-right:.75rem">Returned</span>';
      } else if (pubStatus === 'pitched') {
        pubBadge = '<span class="badge badge-pitched" style="margin-right:.75rem">Pitched</span>';
      }

      var headerColor = (isPassed || isGoneCold) ? 'color:#aaa;' : 'color:#333;';
      var altClass    = pubIdx % 2 === 1 ? ' pub-alt' : '';
      html += '<div class="sub-group' + altClass + '">';
      var pubLastContact = pubLatest.Contact || '';
      var pubAddBtn = '<button class="add-entry-btn"' +
        ' data-game="'      + escHtml(g) + '"' +
        ' data-publisher="' + escHtml(p) + '"' +
        ' data-contact="'   + escHtml(pubLastContact) + '"' +
        ' data-pub-locked="1"' +
        ' onclick="event.stopPropagation();addBtnClick(this)">+ Pitch</button>';
      html += '<div class="sub-label pub-passed-header" onclick="togglePubPassed(this)" style="' + headerColor + 'font-size:.75rem">' +
              '<span class="pub-title-group"><span>' + escHtml(p) + '</span>' + pubAddBtn + '</span>' +
              (isPassed || isGoneCold || isSigned ? '' : pubAgeTag) + pubBadge +
              '<span class="pub-expand-chevron">▶</span>' +
              '</div>';
      html += '<div class="pub-body-wrap"><div class="pub-passed-body">';

      // Sort all entries newest-first and render inline (contact shown in each row)
      pubEntries.sort(function(a,b){ return new Date(b.Date) - new Date(a.Date); });
      pubEntries.forEach(function(e){ html += entryRow(e); });

      html += '</div></div>'; // pub-passed-body, pub-body-wrap
      html += '</div>'; // sub-group
    });

    html += '</div></div>'; // card-body, card-body-wrap
    html += '</div>'; // card
  });

  if (!html) {
    var emptyMsg = totalGameCount === 0
      ? 'No games yet — tap <b>+ New Game</b> to add your first game.'
      : 'No results matching your current filters.';
    return '<div class="empty">' + emptyMsg + '</div>';
  }
  return html;
}

// ── PUBLISHER VIEW ────────────────────────────────────
function buildPublisherView(pitches) {
  // Group by Publisher → Game → Contact
  var pubs = {};
  pitches.forEach(function(r) {
    var p = r.Publisher || '(Unknown)';
    var g = r.Game      || '(Unknown)';
    var c = r.Contact   || '(Unknown)';
    if (!pubs[p]) pubs[p] = {};
    if (!pubs[p][g]) pubs[p][g] = {};
    if (!pubs[p][g][c]) pubs[p][g][c] = [];
    pubs[p][g][c].push(r);
  });

  // Apply active filters — remove games from each publisher that don't match,
  // then remove publishers left with no games
  var hasFilter = activeFilters.signed || activeFilters.published ||
                  activeFilters.interested || activeFilters.passed || activeFilters.pitched || activeFilters.gonecold;
  if (hasFilter) {
    Object.keys(pubs).forEach(function(p) {
      Object.keys(pubs[p]).forEach(function(g) {
        var entries = [];
        Object.keys(pubs[p][g]).forEach(function(c){ pubs[p][g][c].forEach(function(e){ entries.push(e); }); });
        var pub = isGamePublished(g, entries);
        var sig = !pub && isGameSigned(g, entries);
        var keep = false;
        if (activeFilters.published && pub) keep = true;
        if (activeFilters.signed    && sig) keep = true;
        if (!keep && (activeFilters.interested || activeFilters.passed || activeFilters.pitched || activeFilters.gonecold)) {
          var s = (latestEntry(entries).Status||'').toLowerCase();
          keep = (activeFilters.interested && s === 'interested') ||
                 (activeFilters.passed     && s === 'passed') ||
                 (activeFilters.pitched    && s === 'pitched') ||
                 (activeFilters.gonecold   && s === 'gone cold');
        }
        if (!keep) delete pubs[p][g];
      });
      if (Object.keys(pubs[p]).length === 0) delete pubs[p];
    });
  }

  // Sort publishers
  var pubNames = Object.keys(pubs).sort(function(a,b) {
    if (currentSort === 'alpha') return sortDir.alpha * a.localeCompare(b);
    function maxDate(p) {
      var dates = [];
      Object.keys(pubs[p]).forEach(function(g) {
        Object.keys(pubs[p][g]).forEach(function(c) {
          pubs[p][g][c].forEach(function(e){ dates.push(new Date(e.Date)); });
        });
      });
      return Math.max.apply(null, dates);
    }
    return sortDir.date * (maxDate(a) - maxDate(b));
  });

  var html = '';
  pubNames.forEach(function(p) {
    var allEntries = [];
    Object.keys(pubs[p]).forEach(function(g) {
      Object.keys(pubs[p][g]).forEach(function(c) {
        pubs[p][g][c].forEach(function(e){ allEntries.push(e); });
      });
    });
    var at = ageTag(allEntries);

    // Publisher header: INTERESTED if any game's current status with this publisher is Interested
    var anyPubInterested = Object.keys(pubs[p]).some(function(g) {
      var ge = [];
      Object.keys(pubs[p][g]).forEach(function(c){ pubs[p][g][c].forEach(function(e){ ge.push(e); }); });
      return (latestEntry(ge).Status||'').toLowerCase() === 'interested';
    });
    var pubHeaderBadge = anyPubInterested
      ? '<span class="badge badge-interested">Interested</span>'
      : (allEntries.some(function(e){ return (e.Status||'').toLowerCase() === 'pitched'; })
          ? '<span class="badge badge-pitched">Pitched</span>'
          : '');

    html += '<div class="card">';
    html += '<div class="card-header" onclick="toggleCard(this)">';
    html += '<span class="card-title">' + escHtml(p) + '</span>';
    html += '<span class="card-badges">' + at + pubHeaderBadge + '</span>';
    html += '<span class="card-chevron">▼</span>';
    html += '</div>';
    html += '<div class="card-body-wrap"><div class="card-body">';

    // Publisher subtitle: one row per contact, each with Edit Contact + New Pitch
    var pubContacts  = getPubInfo(p);
    if (pubContacts.length === 0) {
      // No contacts — single row with an unaddressed New Pitch
      html += '<div class="pub-subtitle-row">'
           +  '<button class="game-action-btn" style="margin-left:auto"'
           +  ' data-publisher="' + escHtml(p) + '"'
           +  ' onclick="event.stopPropagation();openNewPitchDialog(this.getAttribute(\'data-publisher\'))">New Pitch</button>'
           +  '</div>';
    } else {
      pubContacts.forEach(function(c) {
        html += '<div class="pub-subtitle-row">';
        html += '<span class="pub-subtitle-info">' + escHtml(c.name) + '</span>';
        html += '<button class="game-action-btn"'
             +  ' data-person="' + escHtml(c.name) + '"'
             +  ' onclick="event.stopPropagation();openDiDialog(this.getAttribute(\'data-person\'))">Edit Contact</button>';
        html += '<button class="game-action-btn"'
             +  ' data-publisher="' + escHtml(p) + '"'
             +  ' data-contact="'   + escHtml(c.name) + '"'
             +  ' onclick="event.stopPropagation();openNewPitchDialog(this.getAttribute(\'data-publisher\'),this.getAttribute(\'data-contact\'))">New Pitch</button>';
        html += '</div>';
      });
    }

    // Sort games alphabetically
    var gameNames = Object.keys(pubs[p]).sort(function(a,b){ return a.localeCompare(b); });
    gameNames.forEach(function(g) {
      var contacts = Object.keys(pubs[p][g]).sort(function(a,b){
        if (a==='(Unknown)') return 1; if (b==='(Unknown)') return -1;
        return a.localeCompare(b);
      });
      var gameEntries = [];
      contacts.forEach(function(c){ pubs[p][g][c].forEach(function(e){ gameEntries.push(e); }); });
      var gLatest  = latestEntry(gameEntries);
      var gStatus  = (gLatest.Status||'').toLowerCase();
      var gAgeTag  = ageTag(gameEntries);

      var gamePublished = isGamePublished(g, gameEntries);
      var gameSigned    = !gamePublished && isGameSigned(g, gameEntries);
      var gIsPassed     = !gamePublished && !gameSigned && gStatus === 'passed';
      var gIsGoneCold   = !gamePublished && !gameSigned && gStatus === 'gone cold';
      var gBadge;
      if (gamePublished) {
        gBadge = '<span class="badge badge-published" style="margin-right:.75rem">Published</span>';
      } else if (gameSigned) {
        gBadge = '<span class="badge badge-signed" style="margin-right:.75rem">Signed</span>';
      } else if (gStatus === 'interested') {
        gBadge = '<span class="badge badge-interested" style="margin-right:.75rem">Interested</span>';
      } else if (gStatus === 'returned') {
        gBadge = '<span class="badge badge-returned" style="margin-right:.75rem">Returned</span>';
      } else if (gStatus === 'passed') {
        gBadge = '<span class="badge badge-passed" style="margin-right:.75rem">Passed</span>';
      } else if (gStatus === 'gone cold') {
        gBadge = '<span class="badge badge-gone-cold" style="margin-right:.75rem">Gone Cold</span>';
      } else if (gStatus === 'pitched') {
        gBadge = '<span class="badge badge-pitched" style="margin-right:.75rem">Pitched</span>';
      } else {
        gBadge = '';
      }

      var gStatusDate = (gamePublished || gameSigned) ? gameStatusDateStr(g, gamePublished, gameSigned) : '';
      var gStatusDateHtml = gStatusDate ? '<span class="status-date">' + escHtml(gStatusDate) + '</span>' : '';
      var gHeaderColor = (gIsPassed || gIsGoneCold) ? 'color:#aaa;' : 'color:#333;';
      var gAltClass = gameNames.indexOf(g) % 2 === 1 ? ' pub-alt' : '';
      html += '<div class="sub-group' + gAltClass + '">';
      var gLastContact = gLatest.Contact || '';
      var gAddBtn = '<button class="add-entry-btn"' +
        ' data-game="'      + escHtml(g) + '"' +
        ' data-publisher="' + escHtml(p) + '"' +
        ' data-contact="'   + escHtml(gLastContact) + '"' +
        ' data-pub-locked="1"' +
        ' onclick="event.stopPropagation();addBtnClick(this)">+ Pitch</button>';
      html += '<div class="sub-label pub-passed-header" onclick="togglePubPassed(this)" style="' + gHeaderColor + 'font-size:.75rem">' +
              '<span class="pub-title-group"><span>' + escHtml(g) + '</span>' + gAddBtn + '</span>' +
              (gamePublished || gameSigned || gIsPassed || gIsGoneCold ? '' : gAgeTag) + gBadge + gStatusDateHtml +
              '<span class="pub-expand-chevron">▶</span>' +
              '</div>';
      html += '<div class="pub-body-wrap"><div class="pub-passed-body">';

      // Flatten contacts into a single sorted list; contact shown inline in each row
      var allGameEntries = [];
      contacts.forEach(function(c){ pubs[p][g][c].forEach(function(e){ allGameEntries.push(e); }); });
      allGameEntries.sort(function(a,b){ return new Date(b.Date) - new Date(a.Date); });
      allGameEntries.forEach(function(e){ html += entryRow(e); });

      html += '</div></div>'; // pub-passed-body, pub-body-wrap
      html += '</div>'; // sub-group
    });

    html += '</div></div>'; // card-body, card-body-wrap
    html += '</div>'; // card
  });

  if (!html) {
    var emptyMsg = totalPubCount === 0
      ? 'No pitches yet — tap <b>+ New Game</b> to add your first game.'
      : 'No results matching your current filters.';
    return '<div class="empty">' + emptyMsg + '</div>';
  }
  return html;
}

// ── Chart.js lazy loader ──────────────────────────────
var _chartJsReady = false, _chartJsQueue = [];
function loadChartJS(cb) {
  if (_chartJsReady) { cb(); return; }
  _chartJsQueue.push(cb);
  if (_chartJsQueue.length > 1) return;
  var s = document.createElement('script');
  s.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
  s.onload = function() {
    _chartJsReady = true;
    _chartJsQueue.forEach(function(fn){ fn(); });
    _chartJsQueue = [];
  };
  document.head.appendChild(s);
}

// ── Dashboard view ─────────────────────────────────────
var _activeCharts = {};
function buildDashboardView() {
  // ── Compute stats ──────────────────────────────────────
  var allGameNames = Object.keys(gamesIndex).slice();
  allPitches.forEach(function(r){
    if (r.Game && allGameNames.indexOf(r.Game) < 0) allGameNames.push(r.Game);
  });

  var counts = { notStarted:0, pitching:0, signed:0, published:0 };
  var timeToSign = [], timeToPublish = [];

  allGameNames.forEach(function(name) {
    var info    = gamesIndex[name] || {};
    var entries = allPitches.filter(function(r){ return r.Game === name; });
    var pub     = isGamePublished(name, entries);
    var sig     = !pub && isGameSigned(name, entries);
    if (pub)              counts.published++;
    else if (sig)         counts.signed++;
    else if (entries.length) counts.pitching++;
    else                  counts.notStarted++;

    var ds  = (info['Date Started']   || '').trim();
    var dsg = (info['Date Signed']    || '').trim();
    var dp  = (info['Date Published'] || '').trim();
    if (ds && dsg) {
      var mo = (new Date(dsg).getFullYear()-new Date(ds).getFullYear())*12
             + (new Date(dsg).getMonth()  -new Date(ds).getMonth());
      if (mo >= 0) timeToSign.push(mo);
    }
    if (ds && dp) {
      var mo2 = (new Date(dp).getFullYear()-new Date(ds).getFullYear())*12
              + (new Date(dp).getMonth()  -new Date(ds).getMonth());
      if (mo2 >= 0) timeToPublish.push(mo2);
    }
  });

  function avg(arr) {
    return arr.length ? (arr.reduce(function(a,b){return a+b;},0)/arr.length).toFixed(1) : null;
  }
  var avgSign = avg(timeToSign), avgPub = avg(timeToPublish);

  // Publisher → unique games map
  var pubGames = {};
  allPitches.forEach(function(r){
    if (!r.Publisher || !r.Game) return;
    if (!pubGames[r.Publisher]) pubGames[r.Publisher] = {};
    pubGames[r.Publisher][r.Game] = 1;
  });

  // Timeline: pitches per month
  var monthMap = {};
  allPitches.forEach(function(r){
    if (!r.Date) return;
    var d = new Date(r.Date);
    if (isNaN(d)) return;
    var key = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0');
    monthMap[key] = (monthMap[key]||0) + 1;
  });

  // ── Build HTML ─────────────────────────────────────────
  function statCard(value, label, color) {
    return '<div class="db-stat" style="border-top:3px solid '+color+'">' +
           '<div class="db-stat-value">' + escHtml(String(value)) + '</div>' +
           '<div class="db-stat-label">' + escHtml(label) + '</div>' +
           '</div>';
  }

  var html = '<div class="db-stats">';
  html += statCard(allGameNames.length,       'Total Games',      '#1a1a2e');
  html += statCard(counts.published,          'Published',        '#0369a1');
  html += statCard(counts.signed,             'Signed',           '#7c3aed');
  html += statCard(counts.pitching,           'In Pitching',      '#166534');
  html += statCard(counts.notStarted,         'Not Pitched',      '#94a3b8');
  html += statCard(Object.keys(pubGames).length, 'Publishers',    '#334155');
  if (avgSign !== null) html += statCard(avgSign + ' mo', 'Avg to Sign',    '#7c3aed');
  if (avgPub  !== null) html += statCard(avgPub  + ' mo', 'Avg to Publish', '#0369a1');
  html += '</div>';

  html +=
    '<div class="db-charts">' +
      '<div class="db-chart-card"><h3>Games by Status</h3><canvas id="chartStatus"></canvas></div>' +
      '<div class="db-chart-card"><h3>Top Publishers by Games Pitched</h3><canvas id="chartPublishers"></canvas></div>' +
    '</div>' +
    '<div class="db-chart-card db-chart-wide"><h3>Pitches Over Time</h3><canvas id="chartTimeline"></canvas></div>';

  // ── Game Timelines ─────────────────────────────────────
  // Sort: published > signed > pitching > not started, then alpha
  var sortedGames = allGameNames.slice().sort(function(a, b) {
    var ea = allPitches.filter(function(r){ return r.Game === a; });
    var eb = allPitches.filter(function(r){ return r.Game === b; });
    var puba = isGamePublished(a, ea), pubb = isGamePublished(b, eb);
    var siga = !puba && isGameSigned(a, ea), sigb = !pubb && isGameSigned(b, eb);
    function rank(pub, sig, e) { return pub ? 0 : sig ? 1 : e.length ? 2 : 3; }
    var ra = rank(puba, siga, ea), rb = rank(pubb, sigb, eb);
    if (ra !== rb) return ra - rb;
    return a.localeCompare(b);
  });

  var tlHtml = '<div class="db-chart-card db-chart-wide"><h3>Game Timelines</h3><div class="tl-list">';

  sortedGames.forEach(function(name) {
    var info    = gamesIndex[name] || {};
    var entries = allPitches.filter(function(r){ return r.Game === name; });
    var pub     = isGamePublished(name, entries);
    var sig     = !pub && isGameSigned(name, entries);

    // Date strings — prefer gamesIndex, fall back to entries
    var dsStr  = (info['Date Started']   || '').trim();
    var dsgStr = (info['Date Signed']    || '').trim();
    var dpStr  = (info['Date Published'] || '').trim();
    if (!dsgStr) entries.forEach(function(e){
      if (!dsgStr && (e.Status||'').toLowerCase() === 'signed' && e.Date) dsgStr = e.Date;
    });
    if (!dpStr) entries.forEach(function(e){
      if (!dpStr && (e.Status||'').toLowerCase() === 'published' && e.Date) dpStr = e.Date;
    });

    // Earliest pitch date
    var pitchDates = entries.filter(function(e){ return e.Date; })
                            .map(function(e){ return new Date(e.Date); })
                            .filter(function(d){ return !isNaN(d); });
    var firstPitch = pitchDates.length ? new Date(Math.min.apply(null, pitchDates)) : null;

    // Earliest interested date
    var intDates = entries.filter(function(e){
      return (e.Status||'').toLowerCase() === 'interested' && e.Date;
    }).map(function(e){ return new Date(e.Date); }).filter(function(d){ return !isNaN(d); });
    var firstInt = intDates.length ? new Date(Math.min.apply(null, intDates)) : null;

    var dsDate  = dsStr  ? new Date(dsStr)  : null;
    var dsgDate = dsgStr ? new Date(dsgStr) : null;
    var dpDate  = dpStr  ? new Date(dpStr)  : null;

    // Total unique publishers pitched for this game
    var totalPubsPitched = (function() {
      var pubMap = {};
      entries.forEach(function(e){
        var pub = e.Publisher || '(Unknown)';
        pubMap[pub] = 1;
      });
      return Object.keys(pubMap).length;
    })();

    // 4 milestone stages
    var stages = [
      { key:'started',   label:'Started',  date: dsDate,    reached: !!(dsDate || firstPitch), count: 0 },
      { key:'pitching',  label:'Pitching', date: firstPitch,reached: !!firstPitch,             count: totalPubsPitched },
      { key:'signed',    label:'Signed',   date: dsgDate,   reached: sig || pub,               count: 0 },
      { key:'published', label:'Published',date: dpDate,    reached: pub,                      count: 0 }
    ];

    // Designers
    var designers = ['Designer1','Designer2','Designer3','Designer4']
      .map(function(f){ return (info[f]||'').trim(); })
      .filter(function(v){ return v; }).join(', ');

    tlHtml += '<div class="tl-game">';
    tlHtml += '<div class="tl-game-name">' + escHtml(name);
    if (designers) tlHtml += ' <span class="tl-designers">(' + escHtml(designers) + ')</span>';
    tlHtml += '</div>';
    tlHtml += '<div class="tl-row">';

    for (var si = 0; si < stages.length; si++) {
      var st = stages[si];
      var msClass = 'tl-ms-wrap stage-' + st.key + (st.reached ? ' reached' : '');
      tlHtml += '<div class="' + msClass + '">';
      if (st.key === 'pitching' && st.reached && st.count > 0) {
        var pillW = Math.max(24, Math.min(st.count, 30) * 5);
        tlHtml += '<div class="tl-dot tl-pitching-pill" style="width:' + pillW + 'px">' + st.count + '</div>';
      } else {
        tlHtml += '<div class="tl-dot">' + (st.count > 0 ? st.count : '') + '</div>';
      }
      tlHtml += '<div class="tl-ms-label">' + escHtml(st.label) + '</div>';
      var dateStr = fmtMonYr(st.date);
      if (dateStr) tlHtml += '<div class="tl-ms-date">' + escHtml(dateStr) + '</div>';
      tlHtml += '</div>';
      if (si < stages.length - 1) {
        var connClass = 'tl-connector' + (stages[si].reached && stages[si+1].reached ? ' filled' : '');
        tlHtml += '<div class="' + connClass + '"></div>';
      }
    }

    tlHtml += '</div></div>'; // tl-row, tl-game
  });

  tlHtml += '</div></div>'; // tl-list, db-chart-card
  html += tlHtml;

  document.getElementById('content').innerHTML = html;

  // ── Charts ─────────────────────────────────────────────
  loadChartJS(function() {
    // Destroy old chart instances to avoid canvas reuse errors
    Object.keys(_activeCharts).forEach(function(k){
      try { _activeCharts[k].destroy(); } catch(e){}
    });
    _activeCharts = {};

    // Status doughnut
    _activeCharts.status = new Chart(
      document.getElementById('chartStatus').getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: ['Not Pitched', 'Pitching', 'Signed', 'Published'],
          datasets: [{ data: [counts.notStarted, counts.pitching, counts.signed, counts.published],
            backgroundColor: ['#e2e8f0','#bbf7d0','#7c3aed','#0369a1'],
            borderWidth: 2, borderColor: '#fff' }]
        },
        options: { responsive:true, plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } } } }
      }
    );

    // Top publishers horizontal bar
    var topPubs = Object.keys(pubGames)
      .map(function(p){ return { name:p, count:Object.keys(pubGames[p]).length }; })
      .sort(function(a,b){ return b.count-a.count; }).slice(0,12);

    _activeCharts.publishers = new Chart(
      document.getElementById('chartPublishers').getContext('2d'), {
        type: 'bar',
        data: {
          labels: topPubs.map(function(p){ return p.name; }),
          datasets: [{ label:'Games', data: topPubs.map(function(p){ return p.count; }),
            backgroundColor:'#1a1a2e', borderRadius:3 }]
        },
        options: { indexAxis:'y', responsive:true,
          plugins:{ legend:{ display:false } },
          scales:{ x:{ ticks:{ stepSize:1 }, grid:{ display:false } },
                   y:{ ticks:{ font:{ size:11 } } } } }
      }
    );

    // Timeline bar chart
    var monthKeys = Object.keys(monthMap).sort();
    _activeCharts.timeline = new Chart(
      document.getElementById('chartTimeline').getContext('2d'), {
        type: 'bar',
        data: {
          labels: monthKeys,
          datasets: [{ label:'Pitches', data: monthKeys.map(function(k){ return monthMap[k]; }),
            backgroundColor:'#1a1a2e', borderRadius:3 }]
        },
        options: { responsive:true,
          plugins:{ legend:{ display:false } },
          scales:{ x:{ ticks:{ font:{ size:10 }, maxRotation:45 } },
                   y:{ ticks:{ stepSize:1 }, grid:{ color:'#f0f0f0' } } } }
      }
    );
  });
}

// ── Render ────────────────────────────────────────────
function buildView() {
  if (currentView === 'dashboard') { buildDashboardView(); return; }
  document.getElementById('content').innerHTML =
    currentView === 'game'
      ? buildGameView(filteredPitches)
      : buildPublisherView(filteredPitches);
}

function render(pitches, settings, people, games) {
  // ── Parse settings.json (format: [{My Name: label, COL: value}, …]) ──
  // The value column is whatever key isn't "My Name"
  myName = ''; myPhone = ''; myEmail = '';
  if (settings && settings.length) {
    var valCol = '';
    var keys = Object.keys(settings[0] || {});
    for (var ki = 0; ki < keys.length; ki++) {
      if (keys[ki] !== 'My Name') { valCol = keys[ki]; break; }
    }
    if (!myName && valCol) myName = valCol; // e.g. "TAM"
    settings.forEach(function(r) {
      var label = (r['My Name']||'').trim();
      var val   = valCol ? (r[valCol]||'').trim() : '';
      if (label === 'My Email') myEmail = val;
      if (label === 'My Phone') myPhone = val;
    });
  }

  // ── Build subtitle ───────────────────────────────────
  var parts = [];
  if (myName)  parts.push(myName);
  if (myEmail) parts.push(myEmail);
  if (myPhone) parts.push(myPhone);
  document.getElementById('subTitle').textContent = parts.join('  ·  ') || ('Sheet ' + sheet_Id.slice(0,8) + '…');

  // ── Build people index: "Name|Company" → email ───────
  peopleIndex = {};
  peopleData  = {};
  (people||[]).forEach(function(p) {
    var key  = (p.Name||'').trim() + '|' + (p.Company||'').trim();
    var name = (p.Name||'').trim();
    if (p.Email) peopleIndex[key] = p.Email;
    if (name)    peopleData[name] = p;
  });

  // ── Build games index: game name → {Designers, …} ────
  gamesIndex = {};
  (games||[]).forEach(function(g) {
    var name = (g.Name||'').trim();
    if (name) gamesIndex[name] = g;
  });

  allPitches = (pitches||[]).filter(function(r){ return r.Date || r.Publisher || r.Game; });
  filteredPitches = allPitches;
  buildSummary(allPitches);
  buildView();
}

// ── Collect collaborator emails for a game (all designers except current user) ──
function getCollaboratorEmails(gameName) {
  var info = gamesIndex[gameName] || {};
  var emails = [];
  ['Designer1','Designer2','Designer3','Designer4'].forEach(function(f) {
    var name = (info[f] || '').trim();
    if (!name) return;
    if (myName && name.toLowerCase() === myName.toLowerCase()) return; // skip self
    var person = peopleData[name];
    if (person && person.Email) emails.push(person.Email);
  });
  return emails;
}

// ── Resolve email for a contact+publisher ─────────────
function resolveEmail(contact, publisher, fallbackEmail) {
  if (fallbackEmail) return fallbackEmail;
  var key = (contact||'').trim() + '|' + (publisher||'').trim();
  return peopleIndex[key] || '';
}

// ── Shared game field lookup ──────────────────────────
function _gameFields(gameName) {
  var info = gamesIndex[gameName] || {};
  function field(keys) {
    for (var i = 0; i < keys.length; i++) {
      var v = (info[keys[i]] || '').trim();
      if (v) return v;
    }
    return '';
  }
  return {
    desc:      field(['Description', 'Tagline']),
    designers: ['Designer1','Designer2','Designer3','Designer4']
                 .map(function(f){ return (info[f]||'').trim(); }).filter(Boolean).join(', '),
    sellsheet: field(['Sellsheet', 'Sellsheet URL', 'Link Sellsheet']),
    video:     field(['Video', 'Video URL', 'Link Video']),
    rules:     field(['Rules', 'Rules URL', 'Rules Link', 'Link Rules']),
    play:      field(['Play',  'Play URL',  'Play Link',  'Link Play']),
  };
}

// ── Build plain-text email body ───────────────────────
function buildEmailBody(gameName) {
  var f = _gameFields(gameName);
  var lines = ['\n\n\n'];          // a few blank lines at the top
  lines.push(gameName);
  if (f.desc)      lines.push(f.desc);
  if (f.designers) { lines.push(''); lines.push('Designers: ' + f.designers); }
  var urls = [];
  var viewUrl = window.location.origin + APP_BASE + sheet_Id + '/view/?game=' + encodeURIComponent(gameName);
  urls.push('Game Info: ' + viewUrl);
  if (f.sellsheet) urls.push('Sellsheet: ' + f.sellsheet);
  if (f.video)     urls.push('Video: '     + f.video);
  if (f.rules)     urls.push('Rules: '     + f.rules);
  if (f.play)      urls.push('Play: '      + f.play);
  if (urls.length) { lines.push(''); lines = lines.concat(urls); }
  return lines.join('\n');
}

// ── Build mailto href ─────────────────────────────────
function mailtoHref(email, gameName) {
  if (!email) return '';
  var body = buildEmailBody(gameName);
  var ccEmails = getCollaboratorEmails(gameName);
  var href = 'mailto:' + encodeURIComponent(email)
           + '?subject=' + encodeURIComponent('Game info - ' + gameName);
  if (myEmail)        href += '&from=' + encodeURIComponent(myEmail);
  if (ccEmails.length) href += '&cc='  + encodeURIComponent(ccEmails.join(','));
  if (body)           href += '&body=' + encodeURIComponent(body);
  return href;
}

// ── Send email from Add Entry dialog ─────────────────
function sendEmailFromAddDialog() {
  var gameName = _addCtx.game || (document.getElementById('addGameInput').value || '').trim();
  if (!gameName) return;

  var contact, publisher;
  if (_addCtx.locked) {
    contact   = _addCtx.contact;
    publisher = _addCtx.publisher;
  } else if (_addCtx.pubLocked) {
    contact   = (document.getElementById('addContactInput').value || '').trim();
    publisher = _addCtx.publisher;
  } else {
    contact   = (document.getElementById('addContactInput').value || '').trim();
    publisher = (document.getElementById('addPublisherInput').value || '').trim();
  }

  var email    = resolveEmail(contact, publisher);
  var body     = buildEmailBody(gameName);
  var ccEmails = getCollaboratorEmails(gameName);
  var href  = 'mailto:' + encodeURIComponent(email)
            + '?subject=' + encodeURIComponent('Game info - ' + gameName);
  if (myEmail)         href += '&from=' + encodeURIComponent(myEmail);
  if (ccEmails.length) href += '&cc='   + encodeURIComponent(ccEmails.join(','));
  if (body)            href += '&body=' + encodeURIComponent(body);

  var a = document.createElement('a');
  a.href = href;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

// ── Collapse / expand ────────────────────────────────
function toggleCard(header) {
  header.parentElement.classList.toggle('open');
}

function togglePubPassed(header) {
  var wrap    = header.nextElementSibling;
  var chevron = header.querySelector(".pub-expand-chevron");
  var isOpen  = wrap.classList.toggle("open");
  if (chevron) chevron.style.transform = isOpen ? "rotate(90deg)" : "rotate(0deg)";
}

// ── View-state save / restore (used after adding a row) ──
function saveViewState() {
  var openCards = {};   // card-title text → true
  var openSubs  = {};   // "card-title|||sub-name" → true

  document.querySelectorAll('.card').forEach(function(card) {
    var titleEl = card.querySelector('.card-title');
    if (!titleEl) return;
    var cardKey = titleEl.textContent.trim();
    if (card.classList.contains('open')) openCards[cardKey] = true;

    card.querySelectorAll('.pub-body-wrap.open').forEach(function(wrap) {
      var hdr = wrap.previousElementSibling;
      if (!hdr) return;
      var nameSpan = hdr.querySelector('span');
      if (!nameSpan) return;
      openSubs[cardKey + '|||' + nameSpan.textContent.trim()] = true;
    });
  });

  return { scroll: window.scrollY, openCards: openCards, openSubs: openSubs };
}

function restoreViewState(state) {
  document.querySelectorAll('.card').forEach(function(card) {
    var titleEl = card.querySelector('.card-title');
    if (!titleEl) return;
    var cardKey = titleEl.textContent.trim();
    if (state.openCards[cardKey]) card.classList.add('open');

    card.querySelectorAll('.pub-body-wrap').forEach(function(wrap) {
      var hdr = wrap.previousElementSibling;
      if (!hdr) return;
      var nameSpan = hdr.querySelector('span');
      if (!nameSpan) return;
      var subKey = cardKey + '|||' + nameSpan.textContent.trim();
      if (state.openSubs[subKey]) {
        wrap.classList.add('open');
        var chevron = hdr.querySelector('.pub-expand-chevron');
        if (chevron) chevron.style.transform = 'rotate(90deg)';
      }
    });
  });

  requestAnimationFrame(function() { window.scrollTo(0, state.scroll); });
}







// ── Edit-entry dialog ─────────────────────────────────
var _notesCtx = {};

// Convert "M/D/YYYY" (sheet) → "YYYY-MM-DD" (date input)
function sheetDateToInput(d) {
  if (!d) return '';
  var p = d.split('/');
  if (p.length !== 3) return '';
  return p[2] + '-' + p[0].padStart(2,'0') + '-' + p[1].padStart(2,'0');
}

function rowClick(el) {
  openNotesDialog({
    notes:     el.getAttribute('data-notes')     || '',
    game:      el.getAttribute('data-game')      || '',
    publisher: el.getAttribute('data-publisher') || '',
    contact:   el.getAttribute('data-contact')   || '',
    date:      el.getAttribute('data-date')      || '',
    event:     el.getAttribute('data-event')     || '',
    status:    el.getAttribute('data-status')    || ''
  });
}

function openNotesDialog(entry) {
  _notesCtx = entry;
  document.getElementById('notesDialogMeta').textContent =
    [entry.game, entry.publisher].filter(Boolean).join('  ·  ');
  document.getElementById('editDate').value   = sheetDateToInput(entry.date);
  document.getElementById('editStatus').value = entry.status;
  document.getElementById('editEvent').value  = entry.event;
  document.getElementById('notesEditArea').value = entry.notes;

  // Populate contact dropdown for this publisher
  var contactSel = document.getElementById('editContact');
  contactSel.innerHTML = '<option value="">— unknown —</option>';
  var contacts = getContactsForPublisher(entry.publisher);
  // Ensure the current contact appears even if not in people index
  var currentContact = (entry.contact && entry.contact !== '(Unknown)') ? entry.contact : '';
  if (currentContact && contacts.indexOf(currentContact) === -1) {
    contacts = [currentContact].concat(contacts);
  }
  contacts.forEach(function(c) {
    var o = document.createElement('option');
    o.value = c; o.textContent = c;
    if (c === currentContact) o.selected = true;
    contactSel.appendChild(o);
  });

  var btn = document.getElementById('notesUpdateBtn');
  btn.disabled = false;
  btn.textContent = 'Update';
  document.getElementById('notesOverlay').classList.add('open');
  setTimeout(function() { document.getElementById('notesEditArea').focus(); }, 60);
}

function closeNotesDialog() {
  cancelDeleteEntry();
  document.getElementById('notesOverlay').classList.remove('open');
}

function submitNotesUpdate() {
  var btn = document.getElementById('notesUpdateBtn');
  btn.disabled = true;
  btn.textContent = 'Updating…';

  // Convert date input "YYYY-MM-DD" back to sheet format "M/D/YYYY"
  var rawDate   = document.getElementById('editDate').value;
  var sheetDate = '';
  if (rawDate) {
    var dp = rawDate.split('-');
    sheetDate = parseInt(dp[1]) + '/' + parseInt(dp[2]) + '/' + dp[0];
  }
  var newEvent  = document.getElementById('editEvent').value.trim();
  var newStatus = document.getElementById('editStatus').value;
  var newNotes  = document.getElementById('notesEditArea').value;

  var newContact = document.getElementById('editContact').value;

  var body =
    'id='             + encodeURIComponent(sheet_Id)             +
    '&game='          + encodeURIComponent(_notesCtx.game)       +
    '&publisher='     + encodeURIComponent(_notesCtx.publisher)  +
    '&orig_contact='  + encodeURIComponent(_notesCtx.contact)    +
    '&orig_date='     + encodeURIComponent(_notesCtx.date)       +
    '&orig_event='    + encodeURIComponent(_notesCtx.event)      +
    '&contact='       + encodeURIComponent(newContact)           +
    '&date='          + encodeURIComponent(sheetDate)            +
    '&event='         + encodeURIComponent(newEvent)             +
    '&status='        + encodeURIComponent(newStatus)            +
    '&notes='         + encodeURIComponent(newNotes);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_BASE + 'push/updateRow.php');
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onload = function() {
    btn.disabled = false;
    btn.textContent = 'Update';
    var result;
    try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }
    if (result && result.ok) {
      // Update in-memory allPitches
      allPitches.forEach(function(r) {
        if (r.Game      === _notesCtx.game      &&
            r.Publisher === _notesCtx.publisher  &&
            r.Contact   === _notesCtx.contact    &&
            r.Date      === _notesCtx.date       &&
            r.Event     === _notesCtx.event) {
          r.Date    = sheetDate || r.Date;
          r.Contact = newContact || r.Contact;
          r.Event   = newEvent;
          r.Status  = newStatus;
          r.Notes   = newNotes;
        }
      });
      var _vs = saveViewState();
      buildSummary(allPitches);
      buildView();
      restoreViewState(_vs);
      closeNotesDialog();
    } else {
      showError('Error: ' + ((result && result.error) || 'Could not update row.'));
    }
  };
  xhr.onerror = function() {
    btn.disabled = false;
    btn.textContent = 'Update';
    showError('Network error — could not update.');
  };
  xhr.send(body);
}

function confirmDeleteEntry() {
  document.getElementById('notesActions').style.display        = 'none';
  document.getElementById('notesConfirmActions').style.display = '';
}

function cancelDeleteEntry() {
  document.getElementById('notesConfirmActions').style.display = 'none';
  document.getElementById('notesActions').style.display        = '';
}

function submitDeleteEntry() {
  var btn    = document.getElementById('notesDeleteConfirmBtn');
  var updBtn = document.getElementById('notesUpdateBtn');
  btn.disabled = true; btn.textContent = 'Deleting…';
  updBtn.disabled = true;

  var body =
    'id='        + encodeURIComponent(sheet_Id)           +
    '&game='     + encodeURIComponent(_notesCtx.game)     +
    '&publisher='+ encodeURIComponent(_notesCtx.publisher)+
    '&contact='  + encodeURIComponent(_notesCtx.contact)  +
    '&date='     + encodeURIComponent(_notesCtx.date)     +
    '&event='    + encodeURIComponent(_notesCtx.event);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_BASE + 'push/deleteRow.php');
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onload = function() {
    btn.disabled = false; btn.textContent = 'Delete';
    updBtn.disabled = false;
    cancelDeleteEntry();
    var result;
    try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }
    if (result && result.ok) {
      // Remove from in-memory allPitches
      allPitches = allPitches.filter(function(r) {
        return !(r.Game      === _notesCtx.game      &&
                 r.Publisher === _notesCtx.publisher  &&
                 r.Contact   === _notesCtx.contact    &&
                 r.Date      === _notesCtx.date       &&
                 r.Event     === _notesCtx.event);
      });
      filteredPitches = searchQuery
        ? allPitches.filter(function(r) {
            return (r.Game||'').toLowerCase().includes(searchQuery)
                || (r.Publisher||'').toLowerCase().includes(searchQuery)
                || (r.Contact||'').toLowerCase().includes(searchQuery)
                || (r.Notes||'').toLowerCase().includes(searchQuery);
          })
        : allPitches;
      var _vs = saveViewState();
      buildSummary(allPitches);
      buildView();
      restoreViewState(_vs);
      closeNotesDialog();
    } else {
      showError('Error: ' + ((result && result.error) || 'Could not delete entry.'));
    }
  };
  xhr.onerror = function() {
    btn.disabled = false; btn.textContent = 'Delete';
    updBtn.disabled = false;
    showError('Network error — could not delete.');
  };
  xhr.send(body);
}

// ── Copyable error dialog ─────────────────────────────
function showError(msg) {
  document.getElementById('errBody').textContent = msg;
  document.getElementById('errOverlay').classList.add('open');
}
function closeErrDialog() {
  document.getElementById('errOverlay').classList.remove('open');
}
function copyErrText() {
  var text = document.getElementById('errBody').textContent;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(function() {
      var btn = document.querySelector('.err-copy-btn');
      btn.textContent = 'Copied!';
      setTimeout(function() { btn.textContent = 'Copy'; }, 1800);
    });
  } else {
    // Fallback for older browsers
    var sel = window.getSelection();
    var range = document.createRange();
    range.selectNodeContents(document.getElementById('errBody'));
    sel.removeAllRanges();
    sel.addRange(range);
  }
}

// ── View Page dialog ───────────────────────────────────────

function closeVpDialog() {
  document.getElementById('vpOverlay').classList.remove('open');
}
function vpLog(msg, type) {
  var log  = document.getElementById('vpLog');
  var line = document.createElement('span');
  line.className = 'sync-log-line ' + (type || 'info');
  line.textContent = msg;
  log.appendChild(line);
  log.scrollTop = log.scrollHeight;
}
function vpDone() {
  document.getElementById('vpDoneBtn').disabled              = false;
  document.getElementById('vpSyncBtn').disabled              = false;
  document.getElementById('vpSyncBtn').style.display         = '';
  document.getElementById('vpSyncBtn').className             = 'sync-update-btn';
  document.getElementById('vpAddSheetBtn').style.display     = 'none';
  document.getElementById('vpAddSheetBtn').className         = 'sync-done-btn';
  document.getElementById('vpAddSheetBtn').style.marginRight = '';
  document.getElementById('vpViewPageBtn').disabled          = false;
}
function vpOpenViewPage() {
  var url = APP_BASE + sheet_Id + '/view/?game=' + encodeURIComponent(_vpCurrentGame);
  closeVpDialog();
  window.location.href = url;
}
function closeViewer() {
  document.getElementById('viewerOverlay').classList.remove('open');
  document.getElementById('viewerFrame').src = 'about:blank';
}

// Listen for navigation requests from pages inside the iframe.
window.addEventListener('message', function(e) {
  if (e.origin !== window.location.origin) return;
  if (!e.data) return;
  // openViewer: load a same-domain URL inside the viewer overlay
  if (e.data.type === 'openViewer') {
    document.getElementById('viewerTitle').textContent = e.data.title || '';
    document.getElementById('viewerFrame').src = e.data.url || 'about:blank';
    return;
  }
  // openExternal: fallback when window.open() inside the iframe was blocked
  if (e.data.type === 'openExternal' && e.data.url) {
    window.open(e.data.url, '_blank', 'noopener,noreferrer');
  }
});

// Build the summary HTML from local gamesIndex data + optional parsed JSON records.
// records = null  → still fetching (show placeholder)
// records = []    → fetched but empty
// records = [...] → full data
function _vpBuildSummary(gameName, records) {
  var g = gamesIndex[gameName] || {};
  var html = '';

  // Helper: first non-empty value from gamesIndex keys
  function gf() {
    for (var i = 0; i < arguments.length; i++) {
      var v = (g[arguments[i]] || '').trim(); if (v) return v;
    }
    return '';
  }
  // Helper: first matching record value from game JSON
  function recVal(pattern) {
    if (!records) return '';
    for (var i = 0; i < records.length; i++) {
      if (pattern.test(records[i].Name || '')) return (records[i].Value || '').trim();
    }
    return '';
  }
  // Emit a labelled text row (wrapping)
  function row(label, text) {
    if (!text) return '';
    return '<div class="vp-sum-row vp-sum-row--wrap">'
         + '<span class="vp-sum-label">' + label + '</span>'
         + '<span class="vp-sum-desc">' + escHtml(text) + '</span>'
         + '</div>';
  }

  // Game name header
  html += '<div class="vp-sum-name">' + escHtml(gameName) + '</div>';

  if (records === null) {
    html += '<div class="vp-sum-note">Checking for game data…</div>';
  }

  // 1. SUMMARY
  var summary = recVal(/^summary$/i) || gf('Summary');
  html += row('Summary', summary);

  // 2. DESIGNERS
  var designers = ['Designer1','Designer2','Designer3','Designer4']
    .map(function(f){ return (g[f]||'').trim(); }).filter(Boolean).join(', ');
  html += row('Designers', designers);

  // 3. DESCRIPTION
  var desc = recVal(/^description$/i) || gf('Description', 'Tagline');
  html += row('Description', desc);

  // 4. STARTED
  var started = gf('Date Started','DateStarted','Start Date','StartDate')
             || recVal(/^date.?started$|^start.?date$/i);
  html += row('Started', started);

  // 5. PITCHES
  var gameEntries = allPitches.filter(function(e){ return (e.Game||'') === gameName; });
  if (gameEntries.length) {
    var byPub = {};
    gameEntries.forEach(function(e) {
      var p = e.Publisher || '(Unknown)';
      if (!byPub[p]) byPub[p] = [];
      byPub[p].push(e);
    });
    var cnt = { interested:0, pitched:0, passed:0 };
    Object.keys(byPub).forEach(function(p) {
      var s = (latestEntry(byPub[p]).Status||'').toLowerCase();
      if (s === 'interested')  cnt.interested++;
      else if (s === 'passed') cnt.passed++;
      else if (s)              cnt.pitched++;
    });
    var total = Object.keys(byPub).length;
    var parts = [];
    if (cnt.interested) parts.push(cnt.interested + ' interested');
    if (cnt.pitched)    parts.push(cnt.pitched    + ' pitched');
    if (cnt.passed)     parts.push(cnt.passed     + ' passed');
    html += '<div class="vp-sum-row"><span class="vp-sum-label">Pitches</span>'
          + '<span>' + total + ' publisher' + (total===1?'':'s')
          + (parts.length ? ' — ' + parts.join(', ') : '') + '</span></div>';
  }

  document.getElementById('vpSummary').innerHTML = html;
}

// Set dialog to summary mode and open/update it.
// enabled = true  → both SYNC and VIEW PAGE are clickable
// enabled = false → both are disabled (e.g. still fetching server data)
function _vpOpenSummary(enabled) {
  document.getElementById('vpSummaryPanel').style.display = '';
  document.getElementById('vpLogPanel').style.display    = 'none';
  document.getElementById('vpLog').innerHTML             = '';
  document.getElementById('vpDialogTitle').textContent   = 'View Page';
  document.getElementById('vpAddSheetBtn').style.display = 'none';
  document.getElementById('vpSyncBtn').disabled          = !enabled;
  document.getElementById('vpViewPageBtn').disabled      = !enabled;
  document.getElementById('vpDoneBtn').disabled          = false;
  document.getElementById('vpOverlay').classList.add('open');
}

// Open directly in log mode — used by vpAddSheet after sheet creation.
function openVpDialog() {
  document.getElementById('vpLog').innerHTML = '';
  document.getElementById('vpDoneBtn').disabled     = true;
  document.getElementById('vpSyncBtn').disabled     = true;
  document.getElementById('vpViewPageBtn').disabled = true;
  document.getElementById('vpAddSheetBtn').style.display = 'none';
  document.getElementById('vpSummaryPanel').style.display = 'none';
  document.getElementById('vpLogPanel').style.display = '';
  document.getElementById('vpDialogTitle').textContent = 'Updating Game Info Page';
  document.getElementById('vpOverlay').classList.add('open');
}
// Classify a cachemedia.py output line into a sync colour type.
function _vpMediaType(line) {
  if (/^OK\s/i.test(line))     return 'ok';
  if (/^CACHED\s/i.test(line)) return 'skip';
  if (/^FAIL\s/i.test(line))   return 'error';
  return 'info';
}

// Deploy source/view/index.php → sheets/{id}/view/index.php.
// Called as the final step of every VIEW PAGE publish flow.
function _vpDeployAndOpen() {
  vpLog('Deploying view page…', 'info');
  var xhr3 = new XMLHttpRequest();
  xhr3.open('POST', APP_BASE + 'push/deployViewSource.php');
  xhr3.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  var _finish = function() {
    vpLog('✓  Page updated.', 'ok');
    // Re-fetch the game JSON to update the summary with fresh data
    var safeName = _vpCurrentGame.replace(/\//g, '-').replace(/\\/g, '-');
    var fileUrl  = APP_BASE + 'sheets/' + sheet_Id + '/game-' + safeName + '-en.json';
    var xhrJ = new XMLHttpRequest();
    xhrJ.open('GET', fileUrl + '?v=' + Date.now());
    xhrJ.onload = function() {
      if (xhrJ.status >= 200 && xhrJ.status < 300) {
        var data; try { data = JSON.parse(xhrJ.responseText); } catch(e) { data = []; }
        _vpBuildSummary(_vpCurrentGame, data);
        document.getElementById('vpSummaryPanel').style.display = '';
        document.getElementById('vpDialogTitle').textContent = 'View Page';
      }
      vpDone();
    };
    xhrJ.onerror = function() { vpDone(); };
    xhrJ.send();
  };
  xhr3.onload = function() {
    var r3; try { r3 = JSON.parse(xhr3.responseText); } catch(e) { r3 = null; }
    if (r3 && r3.error) vpLog('⚠  Deploy: ' + r3.error, 'skip');
    _finish();
  };
  xhr3.onerror = function() {
    vpLog('⚠  Deploy skipped (network error)', 'skip');
    _finish();
  };
  xhr3.send('id=' + encodeURIComponent(sheet_Id));
}

// ── View Page ─────────────────────────────────────────
var _vpCurrentGame = '';   // game name kept for vpAddSheet()
var _vpCheckXhr    = null; // in-flight JSON-existence check (aborted on new click)

// Inner publish pipeline: export → cache media → deploy → open.
// Called both by viewPageClick and by vpAddSheet after tab creation.
function _vpRunPublish(gameName) {
  vpLog('Exporting game data…', 'info');

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_BASE + 'push/viewGame.php');
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onload = function() {
    var result;
    try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }

    if (!result || result.error) {
      if (result && result.error === 'tab_not_found') {
        // Sheet tab missing — create it automatically and re-run the pipeline.
        vpLog('ℹ  Sheet tab not found — creating it…', 'info');
        vpAddSheet();
      } else {
        vpLog('✕  ' + ((result && result.error) || xhr.responseText || 'Unknown error'), 'error');
        vpDone();
      }
      return;
    }

    vpLog('✓  Game data exported (' + (result.records || 0) + ' fields)', 'ok');
    vpLog('Caching media…', 'info');

    var xhr2 = new XMLHttpRequest();
    xhr2.open('POST', APP_BASE + 'push/viewGameMedia.php');
    xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr2.onload = function() {
      var r2;
      try { r2 = JSON.parse(xhr2.responseText); } catch(e) { r2 = null; }
      var lines = (r2 && Array.isArray(r2.lines)) ? r2.lines : [];
      if (lines.length === 0) {
        vpLog('nothing to cache', 'skip');
      } else {
        lines.forEach(function(line) { vpLog(line, _vpMediaType(line)); });
      }
      _vpDeployAndOpen();
    };
    xhr2.onerror = function() {
      vpLog('⚠  Media cache skipped (network error)', 'skip');
      _vpDeployAndOpen();
    };
    xhr2.send('id=' + encodeURIComponent(sheet_Id) + '&game=' + encodeURIComponent(gameName));
  };
  xhr.onerror = function() {
    vpLog('✕  Network error — could not reach the server.', 'error');
    vpDone();
  };
  xhr.send('id=' + encodeURIComponent(sheet_Id) + '&game=' + encodeURIComponent(gameName));
}

function viewPageClick(btn) {
  var gameName = btn.getAttribute('data-game') || '';
  if (!gameName) return;
  _vpCurrentGame = gameName;

  // Abort any previous in-flight JSON check so its callback can't fire late
  // and incorrectly enable/disable buttons for the wrong game.
  if (_vpCheckXhr) { try { _vpCheckXhr.abort(); } catch(e) {} _vpCheckXhr = null; }

  // Open dialog immediately with local data; buttons disabled while we contact server.
  _vpBuildSummary(gameName, null);
  _vpOpenSummary(false);

  // Ask the server directly whether the game JSON file exists.
  // Using a PHP endpoint avoids all browser-caching ambiguity that comes
  // from fetching the static file through the router.
  var checkUrl = APP_BASE + 'push/checkGameJson.php'
               + '?id='   + encodeURIComponent(sheet_Id)
               + '&game=' + encodeURIComponent(gameName)
               + '&v='    + Date.now();
  var xhr = new XMLHttpRequest();
  _vpCheckXhr = xhr;
  xhr.open('GET', checkUrl);
  xhr.onload = function() {
    if (xhr !== _vpCheckXhr) return;   // stale callback — a newer click took over
    _vpCheckXhr = null;
    var result; try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }
    if (result && result.exists) {
      // File confirmed on server — fetch it to populate the summary, then enable buttons.
      var safeName = gameName.replace(/\//g, '-').replace(/\\/g, '-');
      var fileUrl  = APP_BASE + 'sheets/' + sheet_Id + '/game-' + safeName + '-en.json';
      var xhrJ = new XMLHttpRequest();
      xhrJ.open('GET', fileUrl + '?v=' + Date.now());
      xhrJ.onload = function() {
        var data = [];
        if (xhrJ.status >= 200 && xhrJ.status < 300) {
          try { data = JSON.parse(xhrJ.responseText); } catch(e) {}
        }
        _vpBuildSummary(gameName, data);
        _vpOpenSummary(true);
      };
      xhrJ.onerror = function() { _vpBuildSummary(gameName, []); _vpOpenSummary(true); };
      xhrJ.send();
    } else {
      // No game JSON — automatically create the sheet tab and publish.
      // No need for the user to click "Add Sheet"; just get on with it.
      document.getElementById('vpSummaryPanel').style.display = 'none';
      document.getElementById('vpLogPanel').style.display     = '';
      document.getElementById('vpDialogTitle').textContent    = 'Setting Up View Page';
      vpAddSheet();
    }
  };
  xhr.onerror = function() {
    if (xhr !== _vpCheckXhr) return;
    _vpCheckXhr = null;
    // Network error — can't reach server, can't sync. Leave buttons disabled.
    document.getElementById('vpSummary').innerHTML +=
      '<div class="vp-sum-note" style="color:#FF8A80;margin-top:.5rem">Could not reach server.</div>';
  };
  xhr.send();
}

// User clicked SYNC — keep summary visible, show log below it, and run the pipeline.
function vpDoSync() {
  document.getElementById('vpLogPanel').style.display = '';
  document.getElementById('vpLog').innerHTML = '';
  document.getElementById('vpSyncBtn').disabled     = true;
  document.getElementById('vpViewPageBtn').disabled = true;
  document.getElementById('vpDoneBtn').disabled     = true;
  document.getElementById('vpDialogTitle').textContent = 'Updating Game Info Page';
  _vpRunPublish(_vpCurrentGame);
}

// Called when the user clicks Add Sheet in the VP dialog.
function vpAddSheet() {
  var gameName = _vpCurrentGame;
  if (!gameName) return;
  document.getElementById('vpAddSheetBtn').style.display = 'none';
  document.getElementById('vpDoneBtn').disabled          = true;
  document.getElementById('vpSyncBtn').disabled          = true;
  document.getElementById('vpViewPageBtn').disabled      = true;
  document.getElementById('vpLogPanel').style.display    = '';
  document.getElementById('vpLog').innerHTML             = '';
  vpLog('Creating sheet "' + gameName + '"…', 'info');

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_BASE + 'push/createGameTab.php');
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onload = function() {
    var result;
    try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }
    if (!result || result.error) {
      if (result && result.error === 'tab_exists') {
        vpLog('ℹ  Sheet "' + (result.name || gameName) + '" already exists — syncing…', 'info');
        _vpRunPublish(gameName);
      } else {
        vpLog('✕  ' + ((result && result.error) || xhr.responseText || 'Unknown error'), 'error');
        vpDone();
      }
      return;
    }
    vpLog('✓  Sheet "' + (result.tab || gameName) + '" created', 'ok');
    _vpRunPublish(gameName);
  };
  xhr.onerror = function() {
    vpLog('✕  Network error — could not create sheet.', 'error');
    vpDone();
  };
  xhr.send('id=' + encodeURIComponent(sheet_Id) + '&game=' + encodeURIComponent(gameName));
}

// ── Game Edit dialog ──────────────────────────────────
var _gameEditCtx = {};   // { origName, isNew }

function editGameClick(btn) {
  var gameName = btn.getAttribute('data-game') || '';
  openGameEditDialog(gameName, false);
}

function openNewGameDialog() {
  openGameEditDialog('', true);
}

function openGameEditDialog(gameName, isNew) {
  _gameEditCtx = { origName: gameName, isNew: !!isNew };
  var g = isNew ? {} : (gamesIndex[gameName] || {});

  document.getElementById('gameEditHeading').textContent = isNew ? 'New Game' : 'Edit Game';
  _setupDesignerCombos();
  _setupStatusCombo();

  // Helper: try several field name variants in g
  function gfield() {
    for (var i = 0; i < arguments.length; i++) {
      var v = g[arguments[i]]; if (v) return v;
    }
    return '';
  }

  document.getElementById('geGameName').value  = isNew ? '' : (g.Name || gameName);
  document.getElementById('geTagline').value      = isNew ? '' : gfield('Tagline', 'Tag Line', 'SubTitle', 'Subtitle');
  document.getElementById('geDescription').value  = isNew ? '' : gfield('Description');
  document.getElementById('geDesigner1').value = isNew ? '' : gfield('Designer1', 'Designer 1');
  document.getElementById('geDesigner2').value = isNew ? '' : gfield('Designer2', 'Designer 2');
  document.getElementById('geDesigner3').value = isNew ? '' : gfield('Designer3', 'Designer 3');
  document.getElementById('geDesigner4').value = isNew ? '' : gfield('Designer4', 'Designer 4');
  document.getElementById('geStatus').value         = isNew ? '' : gfield('Status');
  document.getElementById('geDateStarted').value   = isNew ? '' : _toDateInput(gfield('Date Started',  'DateStarted',  'Start Date',     'StartDate'));
  document.getElementById('geDateSigned').value    = isNew ? '' : _toDateInput(gfield('Date Signed',   'DateSigned',   'Signed Date',    'SignedDate'));
  document.getElementById('geDatePublished').value = isNew ? '' : _toDateInput(gfield('Date Published','DatePublished','Published Date', 'PublishedDate'));
  document.getElementById('geRules').value     = isNew ? '' : gfield('Rules',     'Rules URL',    'RulesURL');
  document.getElementById('gePlay').value      = isNew ? '' : gfield('Play',      'Play URL',     'PlayURL');
  document.getElementById('gePrint').value     = isNew ? '' : gfield('Print',     'Print URL',    'PrintURL');
  document.getElementById('geSellsheet').value = isNew ? '' : gfield('Sellsheet', 'Sellsheet URL','SellsheetURL');
  document.getElementById('geView').value      = isNew ? '' : gfield('BGG',       'View URL',     'BGG / View URL', 'ViewURL', 'View');
  document.getElementById('geVideo').value     = isNew ? '' : gfield('Video',     'Video URL',    'VideoURL');

  document.getElementById('geSaveBtn').disabled    = false;
  document.getElementById('geSaveBtn').textContent = isNew ? 'Add Game' : 'Save';
  document.getElementById('gameEditOverlay').classList.add('open');
  setTimeout(function() { document.getElementById('geGameName').focus(); }, 60);
}

function closeGameEditDialog() {
  document.getElementById('gameEditOverlay').classList.remove('open');
}

function submitGameEdit() {
  var btn    = document.getElementById('geSaveBtn');
  var isNew  = _gameEditCtx.isNew;
  btn.disabled = true;
  btn.textContent = isNew ? 'Adding…' : 'Saving…';

  var payload = {
    orig_name:  _gameEditCtx.origName,
    name:       document.getElementById('geGameName').value.trim(),
    tagline:         document.getElementById('geTagline').value.trim(),
    description:     document.getElementById('geDescription').value.trim(),
    status:          document.getElementById('geStatus').value.trim(),
    date_started:    document.getElementById('geDateStarted').value.trim(),
    date_signed:     document.getElementById('geDateSigned').value.trim(),
    date_published:  document.getElementById('geDatePublished').value.trim(),
    designer1:       document.getElementById('geDesigner1').value.trim(),
    designer2:  document.getElementById('geDesigner2').value.trim(),
    designer3:  document.getElementById('geDesigner3').value.trim(),
    designer4:  document.getElementById('geDesigner4').value.trim(),
    rules:      document.getElementById('geRules').value.trim(),
    play:       document.getElementById('gePlay').value.trim(),
    print:      document.getElementById('gePrint').value.trim(),
    sellsheet:  document.getElementById('geSellsheet').value.trim(),
    view:       document.getElementById('geView').value.trim(),
    video:      document.getElementById('geVideo').value.trim()
  };

  if (!payload.name) {
    showError('Game name is required.');
    btn.disabled = false;
    btn.textContent = isNew ? 'Add Game' : 'Save';
    return;
  }

  function doSave() {
    btn.textContent = isNew ? 'Adding…' : 'Saving…';
    var endpoint = isNew ? 'push/addGame.php' : 'push/updateGame.php';
    var body = 'id=' + encodeURIComponent(sheet_Id);
    for (var k in payload) body += '&' + k + '=' + encodeURIComponent(payload[k]);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + endpoint);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      btn.disabled = false;
      btn.textContent = isNew ? 'Add Game' : 'Save';
      var result;
      try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }
      if (result && result.ok) {
        // Diagnostic: warn if gadd.py found headers but wrote nothing (column name mismatch)
        if (typeof result.non_empty_fields === 'number' && result.non_empty_fields === 0) {
          console.warn('[addGame] gadd.py ok but 0 non-empty fields written.',
            'Tab:', result.sheet, 'Headers:', result.headers, 'Row:', result.written);
          showError('Warning: the game row was appended but all fields were blank.\n\n' +
                'Column headers found in the sheet:\n' +
                JSON.stringify(result.headers) + '\n\n' +
                'This usually means the "games" tab column names do not match.\n' +
                '(See browser console for full details.)');
          closeGameEditDialog();
          return;
        }
        console.log('[addGame] ok — tab:', result.sheet,
                    'fields written:', result.non_empty_fields,
                    'headers:', result.headers, 'row:', result.written);
        // Update gamesIndex in memory
        var oldName = _gameEditCtx.origName;
        var newName = payload.name;
        if (!gamesIndex[newName]) gamesIndex[newName] = {};
        var entry = gamesIndex[newName];
        entry.Name                = newName;
        entry.Tagline             = payload.tagline;
        entry.Description         = payload.description;
        entry.Status              = payload.status;
        entry['Date Started']     = payload.date_started;
        entry['Date Signed']      = payload.date_signed;
        entry['Date Published']   = payload.date_published;
        entry.Designer1           = payload.designer1;
        entry.Designer2  = payload.designer2;
        entry.Designer3  = payload.designer3;
        entry.Designer4  = payload.designer4;
        entry.Rules      = payload.rules;
        entry.Play       = payload.play;
        entry.Print      = payload.print;
        entry.Sellsheet  = payload.sellsheet;
        entry.View       = payload.view;
        entry.Video      = payload.video;
        if (!isNew && newName !== oldName) {
          delete gamesIndex[oldName];
          allPitches.forEach(function(r) { if (r.Game === oldName) r.Game = newName; });
        }
        var _vs = saveViewState();
        buildSummary(allPitches);
        buildView();
        restoreViewState(_vs);
        closeGameEditDialog();
      } else {
        showError('Error: ' + ((result && result.error) || (isNew ? 'Could not add game.' : 'Could not update game.')));
      }
    };
    xhr.onerror = function() {
      btn.disabled = false;
      btn.textContent = isNew ? 'Add Game' : 'Save';
      showError('Network error — could not save.');
    };
    xhr.send(body);
  }

  // Save any new designer names to the people sheet first, then save the game
  var newDesigners = [payload.designer1, payload.designer2, payload.designer3, payload.designer4]
    .filter(function(d, i, arr) { return d && !isKnownPerson(d) && arr.indexOf(d) === i; });

  function saveNextDesigner(queue) {
    if (!queue.length) { doSave(); return; }
    var name = queue[0]; var rest = queue.slice(1);
    btn.textContent = 'Saving "' + name + '"…';
    var pxhr = new XMLHttpRequest();
    pxhr.open('POST', APP_BASE + 'push/addPerson.php');
    pxhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    pxhr.onload = function() {
      var res; try { res = JSON.parse(pxhr.responseText); } catch(e) { res = null; }
      if (res && res.ok) {
        peopleIndex[name + '|'] = '';   // mark as known so next check passes
        if (!peopleData[name]) peopleData[name] = { Name: name, Email: '', Company: '', Role: '', Notes: '' };
        saveNextDesigner(rest);
      } else {
        btn.disabled = false; btn.textContent = isNew ? 'Add Game' : 'Save';
        showError('Error saving designer "' + name + '": ' + ((res && res.error) || 'Unknown error'));
      }
    };
    pxhr.onerror = function() {
      btn.disabled = false; btn.textContent = isNew ? 'Add Game' : 'Save';
      showError('Network error — could not save designer.');
    };
    pxhr.send(
      'id='      + encodeURIComponent(sheet_Id) +
      '&name='   + encodeURIComponent(name) +
      '&company=&email='
    );
  }

  saveNextDesigner(newDesigners);
}

// Close dialogs on Escape (publish dialog first, then error, then sub-dialogs, then main)
document.addEventListener('keydown', function(ev) {
  if (ev.key !== 'Escape') return;
  if (document.getElementById('vpOverlay').classList.contains('open'))       { closeVpDialog();       return; }
  if (document.getElementById('errOverlay').classList.contains('open'))      { closeErrDialog();      return; }
  if (document.getElementById('gameEditOverlay').classList.contains('open')) { closeGameEditDialog(); return; }
  if (document.getElementById('addNewOverlay').classList.contains('open'))   { closeAddNew();         return; }
  if (document.getElementById('addEntryOverlay').classList.contains('open')) { closeAddDialog();      return; }
  closeNotesDialog();
});

// ── Add Entry ─────────────────────────────────────────
var _addCtx = {};       // { game, publisher, contact, locked }
var _addNewMode = '';   // 'publisher' | 'contact'

// ── Publisher / Contact helpers ───────────────────────
function getPublisherList() {
  var pubs = {};
  allPitches.forEach(function(r) { if (r.Publisher) pubs[r.Publisher] = 1; });
  Object.keys(peopleIndex).forEach(function(key) {
    var co = key.split('|')[1]; if (co) pubs[co] = 1;
  });
  return Object.keys(pubs).sort(function(a,b){ return a.localeCompare(b); });
}

function getContactsForPublisher(publisher) {
  var contacts = {};
  allPitches.forEach(function(r) {
    if (r.Publisher === publisher && r.Contact && r.Contact !== '(Unknown)') contacts[r.Contact] = 1;
  });
  Object.keys(peopleIndex).forEach(function(key) {
    var parts = key.split('|');
    if (parts[1] === publisher && parts[0]) contacts[parts[0]] = 1;
  });
  return Object.keys(contacts).sort(function(a,b){ return a.localeCompare(b); });
}

// ── Combobox implementation ───────────────────────────
var _combosReady = false;

function _comboInit(inputId, dropId, getItems, onSelect) {
  var inp  = document.getElementById(inputId);
  var drop = document.getElementById(dropId);
  if (!inp || !drop) return;
  var _ai = -1;   // active dropdown index

  function renderDrop() {
    if (inp.disabled) return;
    var q     = inp.value.trim().toLowerCase();
    var items = getItems().filter(function(s) {
      return !q || s.toLowerCase().indexOf(q) !== -1;
    });
    if (!items.length) { closeDrop(); return; }
    _ai = -1;
    drop.innerHTML = '';
    items.forEach(function(item) {
      var div = document.createElement('div');
      div.className = 'combo-opt';
      div.textContent = item;
      div.addEventListener('mousedown', function(e) {
        e.preventDefault();   // keep focus on input
        inp.value = item;
        closeDrop();
        if (onSelect) onSelect(item);
      });
      drop.appendChild(div);
    });
    drop.classList.add('open');
  }
  function closeDrop() { drop.classList.remove('open'); _ai = -1; }
  function moveActive(dir) {
    var opts = drop.querySelectorAll('.combo-opt');
    if (!opts.length) return;
    _ai = Math.max(0, Math.min(opts.length - 1, _ai + dir));
    opts.forEach(function(o, i) {
      o.classList.toggle('active', i === _ai);
      if (i === _ai) o.scrollIntoView({ block: 'nearest' });
    });
  }
  inp.addEventListener('input',  renderDrop);
  inp.addEventListener('focus',  renderDrop);
  inp.addEventListener('blur',   function() { setTimeout(closeDrop, 150); });
  inp.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (!drop.classList.contains('open')) renderDrop(); else moveActive(1);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault(); moveActive(-1);
    } else if (e.key === 'Enter') {
      var opts = drop.querySelectorAll('.combo-opt');
      if (_ai >= 0 && opts[_ai]) {
        e.preventDefault();
        inp.value = opts[_ai].textContent;
        closeDrop();
        if (onSelect) onSelect(inp.value);
      }
    } else if (e.key === 'Escape') { closeDrop(); }
  });
}

function _setupCombos() {
  if (_combosReady) return;
  _combosReady = true;
  _comboInit('addPublisherInput', 'pubComboDrop',
    function() { return getPublisherList(); },
    function() {
      // Publisher chosen → clear contact field
      document.getElementById('addContactInput').value = '';
    });
  _comboInit('addContactInput', 'contactComboDrop',
    function() {
      var pub = (document.getElementById('addPublisherInput') || {}).value || '';
      return getContactsForPublisher(pub.trim());
    }, null);
  // Typing in publisher field also clears contact
  var pubInp = document.getElementById('addPublisherInput');
  if (pubInp) {
    pubInp.addEventListener('input', function() {
      document.getElementById('addContactInput').value = '';
    });
  }
}

function populatePublishers(selected) {
  var inp = document.getElementById('addPublisherInput');
  if (inp) inp.value = selected || '';
}

function populateContacts(publisher, selected) {
  var inp = document.getElementById('addContactInput');
  if (inp) inp.value = selected || '';
}

function onPublisherChange() {
  // kept for compatibility
  document.getElementById('addContactInput').value = '';
}

// ── Publisher info helper ─────────────────────────────
// Returns all people whose Company matches the publisher name.
// Uses peopleData (all named people) rather than peopleIndex (only people
// with an Email set) so contacts without an email are not silently dropped.
function getPubInfo(publisher) {
  var contacts = [];
  var seen     = {};
  Object.keys(peopleData).forEach(function(name) {
    var person  = peopleData[name];
    var company = (person.Company || '').trim();
    if (company === publisher && name && !seen[name]) {
      seen[name] = true;
      contacts.push({ name: name, email: person.Email || '' });
    }
  });
  return contacts;
}

// ── Game combobox (New Pitch from Publisher view) ─────
var _gameComboReady = false;
function _setupGameCombo() {
  if (_gameComboReady) return;
  _gameComboReady = true;
  _comboInit('addGameInput', 'gameComboDrop', function() {
    return Object.keys(gamesIndex).sort(function(a,b){ return a.localeCompare(b); });
  }, null);
}

function openNewPitchDialog(publisher, contact) {
  _setupGameCombo();
  openAddDialog('', publisher, contact || '', true);
}

// ── Designer combobox helpers ─────────────────────────
function getAllPersonNames() {
  var names = {};
  // From people sheet (peopleIndex keys are "Name|Company")
  Object.keys(peopleIndex).forEach(function(key) {
    var n = key.split('|')[0]; if (n) names[n] = 1;
  });
  // Also include designers already in gamesIndex
  Object.keys(gamesIndex).forEach(function(game) {
    var g = gamesIndex[game];
    ['Designer1','Designer2','Designer3','Designer4'].forEach(function(f) {
      var d = (g[f] || '').trim(); if (d) names[d] = 1;
    });
  });
  return Object.keys(names).sort(function(a,b){ return a.localeCompare(b); });
}

function isKnownPerson(name) {
  if (!name) return true;
  var lc = name.toLowerCase();
  var keys = Object.keys(peopleIndex);
  for (var i = 0; i < keys.length; i++) {
    if (keys[i].split('|')[0].toLowerCase() === lc) return true;
  }
  var gnames = Object.keys(gamesIndex);
  for (var j = 0; j < gnames.length; j++) {
    var g = gamesIndex[gnames[j]];
    if (['Designer1','Designer2','Designer3','Designer4'].some(function(f){
      return (g[f]||'').trim().toLowerCase() === lc;
    })) return true;
  }
  return false;
}

var _designerCombosReady = false;
function _setupDesignerCombos() {
  if (_designerCombosReady) return;
  _designerCombosReady = true;
  ['geDesigner1','geDesigner2','geDesigner3','geDesigner4'].forEach(function(id) {
    _comboInit(id, id + 'Drop', getAllPersonNames, null);
  });
}

var _STATUS_OPTIONS = [
  'Pitching','Interested','Contract Sent','Signed',
  'In Development','In Production','Published','Shelved','Cancelled'
];
var _statusComboReady = false;
function _setupStatusCombo() {
  if (_statusComboReady) return;
  _statusComboReady = true;
  _comboInit('geStatus', 'geStatusDrop', function() { return _STATUS_OPTIONS; }, null);
}

// Convert a stored date string (any common format) to YYYY-MM-DD for <input type="date">.
// Returns '' if the value can't be parsed.
function _toDateInput(val) {
  if (!val) return '';
  var d = new Date(val);
  if (isNaN(d.getTime())) return '';
  var y  = d.getFullYear();
  var mo = String(d.getMonth() + 1).padStart(2, '0');
  var dy = String(d.getDate()).padStart(2, '0');
  return y + '-' + mo + '-' + dy;
}

// ── Open Add Entry dialog ─────────────────────────────
function addBtnClick(btn) {
  openAddDialog(
    btn.getAttribute('data-game')      || '',
    btn.getAttribute('data-publisher') || '',
    btn.getAttribute('data-contact')   || '',
    !!btn.getAttribute('data-pub-locked')
  );
}

function openAddDialog(game, publisher, contact, pubLocked) {
  var bothLocked = !!(publisher && contact && !pubLocked);
  var hasGame    = !!game;
  _addCtx = { game: game, publisher: publisher, contact: contact, locked: bothLocked, pubLocked: !!pubLocked };

  // Game label vs game combobox
  var gameLabelEl   = document.getElementById('addGameLabel');
  var gameSectionEl = document.getElementById('addGameSection');
  gameLabelEl.textContent        = game;
  gameLabelEl.style.display      = hasGame ? '' : 'none';
  gameSectionEl.style.display    = hasGame ? 'none' : '';
  if (!hasGame) {
    _setupGameCombo();
    document.getElementById('addGameInput').value = '';
  }

  // Dialog title
  document.getElementById('addEntryTitle').textContent =
    publisher && !hasGame ? 'New Pitch — ' + publisher : 'Add Entry';

  if (bothLocked) {
    document.getElementById('addPubContactSection').style.display = 'none';
    document.getElementById('addLockedSection').style.display     = '';
    document.getElementById('addLockedSection').textContent       = publisher + '  ·  ' + contact;
  } else {
    document.getElementById('addPubContactSection').style.display = '';
    document.getElementById('addLockedSection').style.display     = 'none';
    _setupCombos();
    var pubInp = document.getElementById('addPublisherInput');
    populatePublishers(publisher);
    populateContacts(publisher, contact);
    if (pubInp) pubInp.disabled = !!pubLocked;
  }

  var t = new Date();
  document.getElementById('addDate').value =
    t.getFullYear() + '-' + String(t.getMonth()+1).padStart(2,'0') + '-' + String(t.getDate()).padStart(2,'0');
  document.getElementById('addEvent').value  = '';
  document.getElementById('addStatus').value = 'Pitched';
  document.getElementById('addNotes').value  = '';

  var btn = document.getElementById('addSubmitBtn');
  btn.disabled = false; btn.textContent = 'Add';

  document.getElementById('addEntryOverlay').classList.add('open');
  setTimeout(function() {
    // Equalize date input height to the select (iOS ignores CSS height on date inputs)
    var _sel = document.getElementById('addStatus');
    var _dat = document.getElementById('addDate');
    if (_sel && _dat) {
      var _h = _sel.getBoundingClientRect().height;
      if (_h > 0) { _dat.style.height = _h + 'px'; _dat.style.boxSizing = 'border-box'; }
    }
    var focusEl = bothLocked
      ? document.getElementById('addEvent')
      : !hasGame
        ? document.getElementById('addGameInput')
        : document.getElementById('addEvent');
    if (focusEl) focusEl.focus();
  }, 60);
}

function closeAddDialog() {
  document.getElementById('addEntryOverlay').classList.remove('open');
}

function submitAddEntry() {
  var dateVal = document.getElementById('addDate').value;
  if (!dateVal) { document.getElementById('addDate').focus(); return; }

  // Resolve game — may come from a combobox if no game was pre-set
  if (!_addCtx.game) {
    var gameInputVal = (document.getElementById('addGameInput').value || '').trim();
    if (!gameInputVal) { document.getElementById('addGameInput').focus(); return; }
    _addCtx.game = gameInputVal;
  }

  var publisher, contact;
  if (_addCtx.locked) {
    publisher = _addCtx.publisher;
    contact   = _addCtx.contact;
  } else if (_addCtx.pubLocked) {
    publisher = _addCtx.publisher;
    contact   = (document.getElementById('addContactInput').value || '').trim();
  } else {
    publisher = (document.getElementById('addPublisherInput').value || '').trim();
    contact   = (document.getElementById('addContactInput').value  || '').trim();
    if (!publisher) { document.getElementById('addPublisherInput').focus(); return; }
  }

  var dp = dateVal.split('-');
  var sheetDate = parseInt(dp[1]) + '/' + parseInt(dp[2]) + '/' + dp[0];
  var eventVal  = document.getElementById('addEvent').value.trim();
  var statusVal = document.getElementById('addStatus').value;
  var notesVal  = document.getElementById('addNotes').value.trim();

  var btn = document.getElementById('addSubmitBtn');

  function doSubmitPitch() {
    btn.disabled = true; btn.textContent = 'Adding…';
    var body =
      'id='         + encodeURIComponent(sheet_Id) +
      '&game='      + encodeURIComponent(_addCtx.game) +
      '&publisher=' + encodeURIComponent(publisher) +
      '&contact='   + encodeURIComponent(contact) +
      '&date='      + encodeURIComponent(sheetDate) +
      '&event='     + encodeURIComponent(eventVal) +
      '&status='    + encodeURIComponent(statusVal) +
      '&notes='     + encodeURIComponent(notesVal);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/addRow.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      btn.disabled = false; btn.textContent = 'Add';
      var result;
      try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }
      if (result && result.ok) {
        allPitches.push({ Game: _addCtx.game, Publisher: publisher, Contact: contact,
          Date: sheetDate, Event: eventVal, Status: statusVal, Notes: notesVal, Email: '' });
        filteredPitches = searchQuery
          ? allPitches.filter(function(r) {
              return (r.Game||'').toLowerCase().includes(searchQuery)
                  || (r.Publisher||'').toLowerCase().includes(searchQuery)
                  || (r.Contact||'').toLowerCase().includes(searchQuery)
                  || (r.Notes||'').toLowerCase().includes(searchQuery);
            })
          : allPitches;
        var _vs = saveViewState();
        buildSummary(allPitches);
        buildView();
        restoreViewState(_vs);
        closeAddDialog();
      } else {
        showError('Error: ' + ((result && result.error) || xhr.responseText || 'Unknown error'));
      }
    };
    xhr.onerror = function() {
      btn.disabled = false; btn.textContent = 'Add';
      showError('Network error — could not add entry.');
    };
    xhr.send(body);
  }

  // If contact is a new name not yet known, save it to people sheet first
  var contactIsNew = contact && !_addCtx.locked
    && getContactsForPublisher(publisher).indexOf(contact) === -1
    && !((contact + '|' + publisher) in peopleIndex);

  if (contactIsNew) {
    btn.disabled = true; btn.textContent = 'Saving contact…';
    var cxhr = new XMLHttpRequest();
    cxhr.open('POST', APP_BASE + 'push/addPerson.php');
    cxhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    cxhr.onload = function() {
      var res;
      try { res = JSON.parse(cxhr.responseText); } catch(e) { res = null; }
      if (res && res.ok) {
        peopleIndex[contact + '|' + publisher] = '';
        if (!peopleData[contact]) peopleData[contact] = { Name: contact, Email: '', Company: publisher, Role: '', Notes: '' };
        doSubmitPitch();
      } else {
        btn.disabled = false; btn.textContent = 'Add';
        showError('Error saving contact: ' + ((res && res.error) || 'Unknown error'));
      }
    };
    cxhr.onerror = function() {
      btn.disabled = false; btn.textContent = 'Add';
      showError('Network error — could not save contact.');
    };
    cxhr.send(
      'id='       + encodeURIComponent(sheet_Id) +
      '&name='    + encodeURIComponent(contact) +
      '&company=' + encodeURIComponent(publisher) +
      '&email='   + encodeURIComponent('')
    );
  } else {
    doSubmitPitch();
  }
}

// ── New Publisher / New Contact sub-dialog ────────────
function openAddNew(mode) {
  _addNewMode = mode;
  var isContact = mode === 'contact';
  document.getElementById('addNewTitle').textContent = isContact ? 'New Contact' : 'New Publisher';

  var fields = document.getElementById('addNewFields');
  if (isContact) {
    var pub = ((document.getElementById('addPublisherInput') || {}).value || '').trim();
    fields.innerHTML =
      (pub ? '<div class="add-locked-ctx" style="margin-bottom:.5rem">Publisher: ' + escHtml(pub) + '</div>' : '') +
      '<label>Name<input type="text" id="addNewName" placeholder="Full name" autocomplete="off" /></label>' +
      '<label style="margin-top:.45rem">Email<input type="email" id="addNewEmail" placeholder="email@example.com (optional)" autocomplete="off" /></label>';
  } else {
    fields.innerHTML =
      '<label>Publisher Name<input type="text" id="addNewName" placeholder="Company name" autocomplete="off" /></label>';
  }

  var btn = document.getElementById('addNewSubmitBtn');
  btn.disabled = false; btn.textContent = 'Add';
  document.getElementById('addNewOverlay').classList.add('open');
  setTimeout(function() { var el = document.getElementById('addNewName'); if (el) el.focus(); }, 60);
}

function closeAddNew() {
  document.getElementById('addNewOverlay').classList.remove('open');
}

function submitAddNew() {
  var nameEl = document.getElementById('addNewName');
  var name = nameEl ? nameEl.value.trim() : '';
  if (!name) { if (nameEl) nameEl.focus(); return; }

  var btn = document.getElementById('addNewSubmitBtn');
  btn.disabled = true; btn.textContent = 'Adding…';

  if (_addNewMode === 'publisher') {
    // No sheet write needed — publisher name is stored in the pitches row
    var pubInp = document.getElementById('addPublisherInput');
    if (pubInp) pubInp.value = name;
    document.getElementById('addContactInput').value = '';
    closeAddNew();

  } else {
    // Contact — write to people sheet
    var emailEl = document.getElementById('addNewEmail');
    var email     = emailEl ? emailEl.value.trim() : '';
    var publisher = ((document.getElementById('addPublisherInput') || {}).value || '').trim();

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/addPerson.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      btn.disabled = false; btn.textContent = 'Add';
      var result;
      try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }
      if (result && result.ok) {
        // Update local people index and data
        var key = name + '|' + publisher;
        if (email) peopleIndex[key] = email;
        if (!peopleData[name]) peopleData[name] = { Name: name, Email: email, Company: publisher, Role: '', Notes: '' };
        // Refresh contact dropdown
        populateContacts(publisher, name);
        closeAddNew();
      } else {
        showError('Error: ' + ((result && result.error) || 'Could not save contact'));
      }
    };
    xhr.onerror = function() {
      btn.disabled = false; btn.textContent = 'Add';
      showError('Network error — could not save contact.');
    };
    xhr.send(
      'id='      + encodeURIComponent(sheet_Id) +
      '&name='   + encodeURIComponent(name) +
      '&company='+ encodeURIComponent(publisher) +
      '&email='  + encodeURIComponent(email)
    );
  }
}



// ── Designer info dialog ──────────────────────────────
var _diOrigName  = '';
var _diIsNew     = false;   // true when person was not in peopleData at dialog open
var _diCompanyComboReady = false;

function _getDiCompanyList() {
  var cos = {};
  Object.keys(peopleData).forEach(function(n) {
    var c = (peopleData[n].Company || '').trim();
    if (c) cos[c] = 1;
  });
  // Also pull from peopleIndex for anyone whose company is stored there
  Object.keys(peopleIndex).forEach(function(key) {
    var c = key.split('|')[1];
    if (c) cos[c] = 1;
  });
  return Object.keys(cos).sort(function(a, b) { return a.localeCompare(b); });
}

function openDiDialog(name) {
  _diOrigName = name;
  _diIsNew    = !peopleData[name];
  var person  = peopleData[name] || {};
  var found   = !_diIsNew;

  document.getElementById('diTitle').value          = name;
  document.getElementById('diEmail').value          = person.Email   || '';
  document.getElementById('diCompany').value        = person.Company || '';
  document.getElementById('diRole').value           = person.Role    || '';
  document.getElementById('diNotes').value          = person.Notes   || '';
  document.getElementById('diStatus').textContent   = '';
  document.getElementById('diStatus').style.color   = '#e57';
  document.getElementById('diNotFound').style.display = found ? 'none' : '';
  var btn = document.getElementById('diUpdateBtn');
  btn.disabled    = false;
  btn.textContent = found ? 'Update' : 'Save';

  // Initialise company combobox once
  if (!_diCompanyComboReady) {
    _diCompanyComboReady = true;
    _comboInit('diCompany', 'diCompanyDrop', _getDiCompanyList, null);
  }

  document.getElementById('diOverlay').classList.add('open');
  setTimeout(function() { document.getElementById('diTitle').focus(); }, 60);
}

function closeDiDialog() {
  document.getElementById('diOverlay').classList.remove('open');
}

function submitDiUpdate() {
  var btn  = document.getElementById('diUpdateBtn');
  var stat = document.getElementById('diStatus');
  btn.disabled = true;
  btn.textContent = 'Saving…';
  stat.textContent = '';

  var name    = document.getElementById('diTitle').value.trim();
  var email   = document.getElementById('diEmail').value.trim();
  var company = document.getElementById('diCompany').value.trim();
  var role    = document.getElementById('diRole').value.trim();
  var notes   = document.getElementById('diNotes').value;
  var nameChanged = (name !== _diOrigName);

  var _btnLabel = _diIsNew ? 'Save' : 'Update';

  // Step 1 — upsert the People sheet (create if not found, update if found)
  function doPersonSave(onOk) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/updatePerson.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      var res; try { res = JSON.parse(xhr.responseText); } catch(e) { res = null; }
      if (res && res.ok) { onOk(!!res.created); }
      else {
        btn.disabled = false; btn.textContent = _btnLabel;
        stat.textContent = (res && res.error) ? res.error : 'Save failed';
      }
    };
    xhr.onerror = function() {
      btn.disabled = false; btn.textContent = _btnLabel;
      stat.textContent = 'Network error';
    };
    xhr.send('id='        + encodeURIComponent(sheet_Id) +
             '&orig_name='+ encodeURIComponent(_diOrigName) +
             '&name='     + encodeURIComponent(name) +
             '&email='    + encodeURIComponent(email) +
             '&company='  + encodeURIComponent(company) +
             '&role='     + encodeURIComponent(role) +
             '&notes='    + encodeURIComponent(notes));
  }

  // Step 2 — rename designer in all Games rows (only if name changed)
  function doGameRename(onOk) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/renameDesigner.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      var res; try { res = JSON.parse(xhr.responseText); } catch(e) { res = null; }
      if (res && res.ok) { onOk(res.updated || 0); }
      else {
        btn.disabled = false; btn.textContent = 'Update';
        stat.textContent = (res && res.error) ? ('Games: ' + res.error) : 'Games update failed';
      }
    };
    xhr.onerror = function() {
      btn.disabled = false; btn.textContent = 'Update';
      stat.textContent = 'Network error (games)';
    };
    xhr.send('id='       + encodeURIComponent(sheet_Id) +
             '&old_name='+ encodeURIComponent(_diOrigName) +
             '&new_name='+ encodeURIComponent(name));
  }

  // Update local in-memory caches
  function updateLocalCaches() {
    // People data
    var oldRecord = peopleData[_diOrigName] || { Name: _diOrigName };
    oldRecord.Name    = name;
    oldRecord.Email   = email;
    oldRecord.Company = company;
    oldRecord.Role    = role;
    oldRecord.Notes   = notes;
    // Always write back — if the person is new, oldRecord is a fresh object
    // that has no reference in peopleData yet, so it must be explicitly stored.
    if (nameChanged) delete peopleData[_diOrigName];
    peopleData[name] = oldRecord;
    // People index
    var oldKey = _diOrigName + '|' + (company || '');
    var newKey = name + '|' + company;
    if (email) peopleIndex[newKey] = email;
    if (oldKey !== newKey) delete peopleIndex[oldKey];

    if (nameChanged) {
      // Games index — rename designer in every game record
      Object.keys(gamesIndex).forEach(function(gameName) {
        var info = gamesIndex[gameName];
        ['Designer1','Designer2','Designer3','Designer4'].forEach(function(f) {
          if ((info[f] || '').trim() === _diOrigName) info[f] = name;
        });
      });
      // DOM — update chip labels and data-designer attributes
      document.querySelectorAll('.designer-chip[data-designer="' + _diOrigName.replace(/"/g, '\\"') + '"]')
        .forEach(function(chip) {
          chip.setAttribute('data-designer', name);
          chip.setAttribute('onclick', 'event.stopPropagation();openDiDialog(this.getAttribute(\'data-designer\'))');
          chip.textContent = name;
        });
      // Update the tracked original name so a second save works correctly
      _diOrigName = name;
    }
  }

  // Orchestrate: person → (if name changed) games → done
  doPersonSave(function(created) {
    var successLabel = created ? 'Created' : 'Saved';
    document.getElementById('diNotFound').style.display = 'none';
    if (nameChanged) {
      doGameRename(function(gamesUpdated) {
        updateLocalCaches();
        var _vs = saveViewState(); buildSummary(allPitches); buildView(); restoreViewState(_vs);
        btn.disabled = false; btn.textContent = _btnLabel;
        stat.style.color = '#16a34a';
        stat.textContent = successLabel + (gamesUpdated ? ' · ' + gamesUpdated + ' game' + (gamesUpdated !== 1 ? 's' : '') + ' updated' : '');
        setTimeout(function() { stat.textContent = ''; stat.style.color = '#e57'; }, 3000);
      });
    } else {
      updateLocalCaches();
      var _vs = saveViewState(); buildSummary(allPitches); buildView(); restoreViewState(_vs);
      btn.disabled = false; btn.textContent = _btnLabel;
      stat.style.color = '#16a34a';
      stat.textContent = successLabel;
      setTimeout(function() { stat.textContent = ''; stat.style.color = '#e57'; }, 2000);
    }
  });
}

// ── Load ──────────────────────────────────────────────
function loadJSON(url, key, fallbackUrl, onDone) {
  var cacheKey = 'pb_' + sheet_Id + '_' + key;
  var xhr = new XMLHttpRequest();
  xhr.open('GET', url + '?v=' + Date.now());
  xhr.onload = function() {
    if (xhr.status === 200) {
      var data; try { data = JSON.parse(xhr.responseText); } catch(e) { data = []; }
      try { localStorage.setItem(cacheKey, JSON.stringify(data)); } catch(e) {}
      onDone(key, data);
    } else if (fallbackUrl) {
      loadJSON(fallbackUrl, key, null, onDone);
    } else {
      // Network data unavailable — serve from local cache if present
      var cached = null;
      try { cached = JSON.parse(localStorage.getItem(cacheKey)); } catch(e) {}
      onDone(key, cached || []);
    }
  };
  xhr.onerror = function() {
    if (fallbackUrl) { loadJSON(fallbackUrl, key, null, onDone); }
    else {
      var cached = null;
      try { cached = JSON.parse(localStorage.getItem(cacheKey)); } catch(e) {}
      onDone(key, cached || []);
    }
  };
  xhr.send();
}

function loadAll(onComplete) {
  var loaded = {}, needed = 4;
  function done(key, data) {
    loaded[key] = data;
    if (--needed === 0) {
      render(loaded.pitches, loaded.settings, loaded.people, loaded.games);
      if (onComplete) onComplete();
    }
  }
  loadJSON(BASE + 'pitches.json',  'pitches',  null,                      done);
  loadJSON(BASE + 'settings.json', 'settings', null,                      done);
  loadJSON(BASE + 'people.json',   'people',   null,                      done);
  loadJSON(BASE + 'games.json',    'games',    null,                      done);
  // Load version info (best-effort — silently ignored if missing)
  (function() {
    var vx = new XMLHttpRequest();
    vx.open('GET', APP_BASE + 'version.json?v=' + Date.now());
    vx.onload = function() {
      if (vx.status !== 200) return;
      try {
        var v = JSON.parse(vx.responseText);
        var el = document.getElementById('versionTag');
        if (el && v.Version) {
          el.textContent = 'v' + v.Version + (v.PublishedOn ? ' · ' + v.PublishedOn : '');
          el.style.display = '';
        }
      } catch(e) {}
    };
    vx.onerror = function() {};
    vx.send();
  })();
}

// ── Fetch dialog helpers ──────────────────────────────
function openSyncDialog() {
  document.getElementById('syncLog').innerHTML = '';
  document.getElementById('syncDialogTitle').textContent = 'Fetching…';
  document.getElementById('syncDialogSub').style.display = '';
  document.getElementById('syncDoneBtn').disabled = true;
  document.getElementById('syncOverlay').classList.add('open');
}
function closeSyncDialog() {
  document.getElementById('syncOverlay').classList.remove('open');
}
function syncLog(msg, type) {
  var log  = document.getElementById('syncLog');
  var line = document.createElement('span');
  line.className = 'sync-log-line ' + (type||'info');
  line.textContent = msg;
  log.appendChild(line);
  log.scrollTop = log.scrollHeight;
}

// ── Account menu ─────────────────────────────────────
function toggleAccountMenu() {
  var menu = document.getElementById('accountMenu');
  var open = menu.classList.contains('open');
  if (open) { menu.classList.remove('open'); }
  else       { menu.classList.add('open'); }
}
function closeAccountMenu() {
  document.getElementById('accountMenu').classList.remove('open');
}
function accountMenuFetch()  { closeAccountMenu(); syncData(); }
function accountMenuImport() { closeAccountMenu(); importClick(); }
function accountMenuProfile(){ closeAccountMenu(); openProfileDialog(); }
function accountMenuHelp()   { closeAccountMenu(); window.open(APP_BASE + 'pitchboard/help', '_blank'); }

document.addEventListener('click', function(e) {
  var wrap = document.querySelector('.account-menu-wrap');
  if (wrap && !wrap.contains(e.target)) closeAccountMenu();
});

// ── Profile dialog ───────────────────────────────────
function openProfileDialog() {
  document.getElementById('profileName').value  = myName  || '';
  document.getElementById('profileEmail').value = myEmail || '';
  document.getElementById('profilePhone').value = myPhone || '';
  document.getElementById('profileLog').innerHTML = '';
  document.getElementById('profileLog').style.display = 'none';
  document.getElementById('profileSaveBtn').disabled   = false;
  document.getElementById('profileCancelBtn').disabled = false;
  document.getElementById('profileCancelBtn').textContent = 'Cancel';
  document.getElementById('profileOverlay').classList.add('open');
}
function closeProfileDialog() {
  document.getElementById('profileOverlay').classList.remove('open');
}
function _profileLog(msg, type) {
  var log  = document.getElementById('profileLog');
  log.style.display = '';
  var span = document.createElement('span');
  span.className   = 'sync-log-line ' + (type || 'info');
  span.textContent = msg;
  log.appendChild(span);
  log.scrollTop = log.scrollHeight;
}
function submitProfile() {
  if (!sheet_Id) return;
  var name  = document.getElementById('profileName').value.trim();
  var email = document.getElementById('profileEmail').value.trim();
  var phone = document.getElementById('profilePhone').value.trim();
  if (!name) { _profileLog('Name is required.', 'error'); return; }

  document.getElementById('profileSaveBtn').disabled   = true;
  document.getElementById('profileCancelBtn').disabled = true;
  _profileLog('Saving…', 'info');

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_BASE + 'push/updateProfile.php');
  var fd = new FormData();
  fd.append('id',    sheet_Id);
  fd.append('name',  name);
  fd.append('email', email);
  fd.append('phone', phone);
  xhr.onload = function() {
    var result;
    try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }
    if (!result || result.error) {
      _profileLog('✕  ' + ((result && result.error) || 'Unknown error'), 'error');
      document.getElementById('profileSaveBtn').disabled   = false;
      document.getElementById('profileCancelBtn').disabled = false;
      return;
    }
    // Update in-memory values immediately
    myName  = name;
    myEmail = email;
    myPhone = phone;
    var parts = [myName, myEmail, myPhone].filter(Boolean);
    document.getElementById('subTitle').textContent = parts.join('  ·  ');
    _profileLog('✓  Saved', 'ok');
    document.getElementById('profileCancelBtn').disabled  = false;
    document.getElementById('profileCancelBtn').textContent = 'Close';
  };
  xhr.onerror = function() {
    _profileLog('✕  Network error', 'error');
    document.getElementById('profileSaveBtn').disabled   = false;
    document.getElementById('profileCancelBtn').disabled = false;
  };
  xhr.send(fd);
}

// ── Share (save pitches JSON to server, show link) ────
function shareGame(gameName) {
  var pitches = allPitches.filter(function(r) { return r.Game === gameName; });

  // Collect unique contacts and their person records
  var seen = {};
  var people = [];
  pitches.forEach(function(r) {
    var name = (r.Contact || '').trim();
    if (name && !seen[name]) {
      seen[name] = 1;
      var p = peopleData[name];
      people.push(p ? p : { Name: name, Company: r.Publisher || '' });
    }
  });

  var exportData = {
    game:     Object.assign({ Name: gameName }, gamesIndex[gameName] || {}),
    exported: new Date().toISOString().slice(0, 10),
    pitches:  pitches,
    people:   people
  };

  var fd = new FormData();
  fd.append('id',   sheet_Id);
  fd.append('data', JSON.stringify(exportData));

  fetch(APP_BASE + 'push/createShare.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(result) {
      if (result.error) { alert('Share failed: ' + result.error); return; }
      openShareUrlDialog(result.url, gameName);
    })
    .catch(function(e) { alert('Share failed: ' + e.message); });
}

function openShareUrlDialog(url, gameName) {
  document.getElementById('shareUrlInput').value = url;
  document.getElementById('shareUrlCopyBtn').textContent = 'Copy';
  var gamePageUrl = window.location.origin + APP_BASE + sheet_Id + '/view/?game=' + encodeURIComponent(gameName || '');
  document.getElementById('shareGamePageInput').value = gamePageUrl;
  document.getElementById('shareGamePageCopyBtn').textContent = 'Copy';
  document.getElementById('shareUrlOverlay').classList.add('open');
}
function closeShareUrlDialog() {
  document.getElementById('shareUrlOverlay').classList.remove('open');
}
function copyGamePageUrl() {
  var val = document.getElementById('shareGamePageInput').value;
  var btn = document.getElementById('shareGamePageCopyBtn');
  navigator.clipboard.writeText(val).then(function() {
    btn.textContent = 'Copied!';
    setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
  }).catch(function() {
    var inp = document.getElementById('shareGamePageInput');
    inp.select();
    try { document.execCommand('copy'); } catch(e) {}
    btn.textContent = 'Copied!';
    setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
  });
}
function copyShareUrl() {
  var val = document.getElementById('shareUrlInput').value;
  var btn = document.getElementById('shareUrlCopyBtn');
  navigator.clipboard.writeText(val).then(function() {
    btn.textContent = 'Copied!';
    setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
  }).catch(function() {
    // iOS fallback
    var inp = document.getElementById('shareUrlInput');
    inp.select();
    try { document.execCommand('copy'); } catch(e) {}
    btn.textContent = 'Copied!';
    setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
  });
}

// ── Import (load pitches JSON from a share link) ─────
function importClick() { openImportUrlDialog(); }

function openImportUrlDialog() {
  document.getElementById('importUrlInput').value = '';
  var log = document.getElementById('importUrlLog');
  log.style.display = 'none';
  log.innerHTML = '';
  document.getElementById('importUrlLoadBtn').disabled = false;
  document.getElementById('importUrlOverlay').classList.add('open');
  setTimeout(function() { document.getElementById('importUrlInput').focus(); }, 50);
}
function closeImportUrlDialog() {
  document.getElementById('importUrlOverlay').classList.remove('open');
}
function loadImportUrl() {
  var url = (document.getElementById('importUrlInput').value || '').trim();
  if (!url) return;
  var log = document.getElementById('importUrlLog');
  log.style.display = 'block';
  log.innerHTML = '<span class="sync-log-line info">Loading…</span>';
  document.getElementById('importUrlLoadBtn').disabled = true;

  fetch(url)
    .then(function(r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function(data) {
      if (!data.pitches && !data.people) throw new Error('Not a valid share file');
      closeImportUrlDialog();
      openImportDialog(data);
    })
    .catch(function(e) {
      log.innerHTML = '<span class="sync-log-line error">Error: ' + escHtml(e.message) + '</span>';
      document.getElementById('importUrlLoadBtn').disabled = false;
    });
}

var _importData = null;

function _isDuplicatePitch(r) {
  var g = (r.Game      || '').trim().toLowerCase();
  var p = (r.Publisher || '').trim().toLowerCase();
  var c = (r.Contact   || '').trim().toLowerCase();
  var d = (r.Date      || '').trim();
  return allPitches.some(function(e) {
    return (e.Game      || '').trim().toLowerCase() === g
        && (e.Publisher || '').trim().toLowerCase() === p
        && (e.Contact   || '').trim().toLowerCase() === c
        && (e.Date      || '').trim()               === d;
  });
}

function _isNewPerson(p) {
  var name    = (p.Name    || '').trim();
  var company = (p.Company || '').trim().toLowerCase();
  if (!name) return false;
  // peopleIndex is keyed "Name|Company" but only for people who have an email.
  // Use `in` (not truthiness) so empty-string emails don't slip through.
  if ((name + '|' + company) in peopleIndex) return false;
  // Also check peopleIndex with original-case company (built from raw sheet data)
  if ((name + '|' + (p.Company || '').trim()) in peopleIndex) return false;
  // Fallback: scan peopleData for same Name + Company (covers email-less records)
  var existing = peopleData[name];
  if (existing && (existing.Company || '').trim().toLowerCase() === company) return false;
  return true;
}

function openImportDialog(data) {
  _importData = data;
  var pitches = data.pitches || [];
  var people  = data.people  || [];

  // data.game is now a full record object; support old string format too
  var gameObj  = (data.game && typeof data.game === 'object') ? data.game : { Name: data.game || '' };
  var gameName = (gameObj.Name || '').trim();
  var gameNameLc = gameName.toLowerCase();
  var isNewGame  = !!(gameName && !Object.keys(gamesIndex).some(function(k) {
    return k.toLowerCase() === gameNameLc;
  }));

  // Pre-compute filtered sets and cache on _importData for confirmImport()
  var newPitches = pitches.filter(function(r) { return !_isDuplicatePitch(r); });
  var dupCount   = pitches.length - newPitches.length;
  var newPeople  = people.filter(_isNewPerson);
  _importData._gameObj    = gameObj;
  _importData._gameName   = gameName;
  _importData._newPitches = newPitches;
  _importData._newPeople  = newPeople;
  _importData._isNewGame  = isNewGame;

  var html = '';
  html += '<div class="import-game-name">' + escHtml(gameName || '(Unknown game)') + '</div>';
  if (data.exported) html += '<div class="import-meta">Exported ' + escHtml(data.exported) + '</div>';

  // New game notice
  if (isNewGame) {
    html += '<div class="import-section-label">New game</div>';
    html += '<div style="font-size:.8rem;margin:.1rem 0">' + escHtml(gameName) + '</div>';
    var designers = ['Designer1','Designer2','Designer3','Designer4']
      .map(function(f){ return (gameObj[f]||'').trim(); }).filter(Boolean);
    if (designers.length)
      html += '<div style="font-size:.74rem;color:#888;margin-top:.1rem">' + escHtml(designers.join(', ')) + '</div>';
    if (gameObj.Tagline)
      html += '<div style="font-size:.74rem;color:#888;font-style:italic;margin-top:.1rem">' + escHtml(gameObj.Tagline) + '</div>';
  }

  // Pitches — show new vs skipped
  var pitchLabel = 'Pitches';
  if (newPitches.length && dupCount)
    pitchLabel += ' (' + newPitches.length + ' new, ' + dupCount + ' duplicate' + (dupCount > 1 ? 's' : '') + ' skipped)';
  else if (newPitches.length)
    pitchLabel += ' (' + newPitches.length + ')';
  else if (dupCount)
    pitchLabel += ' (all ' + dupCount + ' already imported)';
  html += '<div class="import-section-label">' + pitchLabel + '</div>';

  if (newPitches.length) {
    html += '<div class="import-table-wrap"><table class="import-table">';
    html += '<tr><th>Publisher</th><th>Contact</th><th>Date</th><th>Status</th></tr>';
    newPitches.forEach(function(r) {
      html += '<tr>'
            + '<td>' + escHtml(r.Publisher || '') + '</td>'
            + '<td>' + escHtml(r.Contact   || '') + '</td>'
            + '<td>' + escHtml(r.Date      || '') + '</td>'
            + '<td>' + escHtml(r.Status    || '') + '</td>'
            + '</tr>';
    });
    html += '</table></div>';
  } else {
    html += '<div class="import-empty">No new pitches to import</div>';
  }

  if (newPeople.length) {
    html += '<div class="import-section-label">New contacts (' + newPeople.length + ')</div>';
    html += '<ul class="import-people-list">';
    newPeople.forEach(function(p) {
      html += '<li>' + escHtml(p.Name)
            + (p.Company ? ' <span class="import-company">(' + escHtml(p.Company) + ')</span>' : '')
            + '</li>';
    });
    html += '</ul>';
  }

  document.getElementById('importDialogBody').innerHTML = html;
  document.getElementById('importLog').innerHTML = '';
  document.getElementById('importLog').style.display = 'none';
  document.getElementById('importConfirmBtn').disabled = false;
  document.getElementById('importCancelBtn').disabled  = false;
  document.getElementById('importCancelBtn').textContent = 'Cancel';
  document.getElementById('importOverlay').classList.add('open');
}

function closeImportDialog() {
  document.getElementById('importOverlay').classList.remove('open');
  _importData = null;
}

function _importLog(msg, type) {
  var log  = document.getElementById('importLog');
  var span = document.createElement('span');
  span.className   = 'sync-log-line ' + (type || 'info');
  span.textContent = msg;
  log.appendChild(span);
  log.scrollTop = log.scrollHeight;
}

function confirmImport() {
  if (!_importData || !sheet_Id) return;
  // Use pre-computed sets from openImportDialog()
  var newPitches = _importData._newPitches || [];
  var newPeople  = _importData._newPeople  || [];
  var isNewGame  = !!_importData._isNewGame;
  var newGame    = isNewGame ? _importData._gameObj : null;

  document.getElementById('importConfirmBtn').disabled = true;
  document.getElementById('importCancelBtn').disabled  = true;
  document.getElementById('importLog').style.display   = '';

  var parts = [];
  if (newPitches.length) parts.push(newPitches.length + ' pitch(es)');
  if (newPeople.length)  parts.push(newPeople.length  + ' contact(s)');
  if (isNewGame)         parts.push('game "' + _importData._gameName + '"');
  if (!parts.length) {
    _importLog('Nothing new to import.', 'info');
    document.getElementById('importCancelBtn').disabled  = false;
    document.getElementById('importCancelBtn').textContent = 'Close';
    return;
  }
  _importLog('Importing ' + parts.join(', ') + '…', 'info');

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_BASE + 'push/importPitches.php');
  var fd = new FormData();
  fd.append('id',      sheet_Id);
  fd.append('pitches', JSON.stringify(newPitches));
  fd.append('people',  JSON.stringify(newPeople));
  fd.append('game',    JSON.stringify(newGame));
  xhr.onload = function() {
    var result;
    try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }
    if (!result || result.error) {
      _importLog('✕  ' + ((result && result.error) || xhr.responseText || 'Unknown error'), 'error');
      document.getElementById('importCancelBtn').disabled = false;
      return;
    }
    if (result.game_added)       _importLog('✓  Game "' + _importData._gameName + '" added', 'ok');
    if (result.pitches_added > 0) _importLog('✓  ' + result.pitches_added + ' pitch(es) added', 'ok');
    if (result.people_added  > 0) _importLog('✓  ' + result.people_added  + ' contact(s) added', 'ok');
    _importLog('Reloading data…', 'info');
    loadAll(function() {
      _importLog('✓  Done!', 'ok');
      document.getElementById('importCancelBtn').disabled  = false;
      document.getElementById('importCancelBtn').textContent = 'Close';
    });
  };
  xhr.onerror = function() {
    _importLog('✕  Network error', 'error');
    document.getElementById('importCancelBtn').disabled = false;
  };
  xhr.send(fd);
}

// ── Fetch (pull sheet data then reload) ──────────────
function syncData() {
  var btn = document.getElementById('accountBtn');
  btn.disabled = true;

  openSyncDialog();

  var sheets   = ['pitches', 'games', 'people', 'settings'];
  var pushBase = APP_BASE + 'push/pushSheetUpdate.php';
  var idx = 0;

  function finish() {
    syncLog('Reloading data…', 'info');
    loadAll(function() {
      syncLog('Done.', 'ok');
      document.getElementById('syncDialogTitle').textContent = 'Fetch Complete';
      document.getElementById('syncDialogSub').style.display = 'none';
      document.getElementById('syncDoneBtn').disabled = false;
      btn.disabled = false;
    });
  }

  function pushNext() {
    if (idx >= sheets.length) {
      finish();
      return;
    }
    var sheetName = sheets[idx++];
    syncLog('Fetching ' + sheetName + '…', 'info');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', pushBase);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      var resp = (xhr.responseText || '').trim();
      if (resp.indexOf('ERROR:') === 0) {
        var msg = resp.replace(/^ERROR:[^:]+:/, '');
        syncLog('  ✗ ' + sheetName + ': ' + msg, 'error');
      } else if (resp.indexOf('SKIP:') === 0) {
        syncLog('  – ' + sheetName + ': skipped (not found)', 'skip');
      } else {
        syncLog('  ✓ ' + resp, 'ok');
      }
      pushNext();
    };
    xhr.onerror = function() {
      syncLog('  ✗ ' + sheetName + ': network error', 'error');
      pushNext();
    };
    xhr.send('id=' + encodeURIComponent(sheet_Id) +
             '&sheetname=' + encodeURIComponent(sheetName) +
             '&date_string=');
  }
  pushNext();
}

// ── Deploy only (called from header title click) ──────
function deployOnly() {
  document.getElementById('syncLog').innerHTML = '';
  document.getElementById('syncDialogTitle').textContent = 'Updating Pitchboard…';
  document.getElementById('syncDoneBtn').disabled = true;
  document.getElementById('syncOverlay').classList.add('open');
  updatePitchboard(function() {
    document.getElementById('syncDialogTitle').textContent = 'Update Complete';
    document.getElementById('syncDoneBtn').disabled = false;
  });
}

// ── Update Pitchboard (deploy source to this sheet) ──
function updatePitchboard(onDone) {
  syncLog('─────────────────────────────', 'info');
  syncLog('Deploying Pitchboard…', 'info');

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_BASE + 'push/deploySource.php');
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onload = function() {
    var lines = (xhr.responseText || '').trim().split('\n');
    lines.forEach(function(line) {
      line = line.trim();
      if (!line) return;
      if (line.indexOf('ERROR') === 0) {
        syncLog('  ✗ ' + line, 'error');
      } else if (line.indexOf('SKIP') === 0) {
        syncLog('  – ' + line.replace(/^SKIP:\s*/, ''), 'skip');
      } else {
        syncLog('  ✓ ' + line, 'ok');
      }
    });
    if (onDone) onDone();
  };
  xhr.onerror = function() {
    syncLog('  ✗ deploy: network error', 'error');
    if (onDone) onDone();
  };
  xhr.send('sheet_id=' + encodeURIComponent(sheet_Id));
}

// ── First-launch auto-sync (standalone / home-screen mode) ───
function _pbAutoSync() {
  // Full-screen loading overlay
  var overlay = document.createElement('div');
  overlay.style.cssText = [
    'position:fixed;inset:0;z-index:99999',
    'background:#1a1a2e',
    'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.5rem',
    'padding:2rem;text-align:center'
  ].join(';');
  overlay.innerHTML = [
    '<div style="font-family:DINBlack,sans-serif;font-size:2.4rem;line-height:1;letter-spacing:-.01em">',
      '<span style="color:#A8C8F0">Pitch</span><span style="color:#FF8A80">Board</span>',
    '</div>',
    '<p style="font-family:DINRegular,Arial,sans-serif;font-size:.95rem;color:#c0c8d8;',
       'max-width:280px;line-height:1.6;margin:0">',
      'Loading your data locally so that you can run when you are not connected to the Internet.',
    '</p>',
    '<div id="_pbLoadStatus" style="font-family:DINBlack,sans-serif;font-size:.7rem;',
         'text-transform:uppercase;letter-spacing:.08em;color:#A8C8F0;opacity:.7"></div>'
  ].join('');
  document.body.appendChild(overlay);

  var status   = overlay.querySelector('#_pbLoadStatus');
  var sheets   = ['pitches', 'games', 'people', 'settings'];
  var pushBase = APP_BASE + 'push/pushSheetUpdate.php';
  var idx = 0;

  function dismiss() {
    overlay.style.transition = 'opacity .6s';
    overlay.style.opacity = '0';
    setTimeout(function() { overlay.remove(); }, 650);
  }

  function cacheShell() {
    // Proactively store the dashboard HTML in the SW cache so it
    // loads from cache on the next offline launch.
    if ('caches' in window) {
      caches.open('pitchboard-sw-v1').then(function(cache) {
        return cache.add(location.pathname);
      }).catch(function(){});
    }
  }

  function next() {
    if (idx >= sheets.length) {
      status.textContent = 'Done — loading…';
      loadAll(function() { cacheShell(); dismiss(); });
      return;
    }
    var name = sheets[idx++];
    status.textContent = name + '…';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', pushBase);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload  = next;
    xhr.onerror = next;
    xhr.send('id=' + encodeURIComponent(sheet_Id) +
             '&sheetname=' + encodeURIComponent(name) +
             '&date_string=');
  }
  next();
}

// ── Service Worker (offline shell caching) ─────────────────
if ('serviceWorker' in navigator) {
  var _swScope = APP_BASE + sheet_Id + '/';
  navigator.serviceWorker.register(APP_BASE + 'pitchboard-sw.js', { scope: _swScope })
    .catch(function(){});
}

// ── iOS zoom-reset on input blur ──────────────────────
// iOS Safari zooms in when a focused input has font-size < 16px, but never
// zooms back out on blur.  Briefly toggling maximum-scale=1 forces it to snap
// back to the page's normal zoom level, then we restore the original viewport
// so pinch-to-zoom remains available.
(function() {
  var vp = document.querySelector('meta[name="viewport"]');
  if (!vp) return;
  var orig = vp.getAttribute('content');
  document.addEventListener('blur', function(e) {
    if (!e.target.matches('input, select, textarea')) return;
    vp.setAttribute('content', orig + ', maximum-scale=1');
    setTimeout(function() { vp.setAttribute('content', orig); }, 300);
  }, true);
})();

// Initial load — then check for first-launch standalone auto-sync
loadAll(function() {
  var standalone = navigator.standalone === true ||
                   matchMedia('(display-mode: standalone)').matches;
  var initKey = 'pb_initialized_' + sheet_Id;
  if (standalone && !localStorage.getItem(initKey)) {
    localStorage.setItem(initKey, '1');
    _pbAutoSync();
  }
});
</script>
</body>
</html>
