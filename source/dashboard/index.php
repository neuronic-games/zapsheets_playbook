<?php
$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#^(.*?)(?:sheets/)?[A-Za-z0-9_\-]+/dashboard/?$#', $_rp, $_bm);
$_base = (isset($_bm[1]) && $_bm[1] !== '') ? $_bm[1] : '/';
if (substr($_base, -1) !== '/') $_base .= '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pitchboard</title>
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
    .top-bar h1 { font-family:'DINBlack',sans-serif; font-size:1rem; margin:0; letter-spacing:.03em; }
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

    /* ── Top-bar buttons (Share / Sync) ─────────────── */
    .share-btn, .sync-btn {
      display:inline-flex; align-items:center; gap:.35rem;
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.06em;
      background:rgba(255,255,255,.15); color:#fff;
      border:1px solid rgba(255,255,255,.3); border-radius:6px;
      padding:.38rem .8rem; cursor:pointer;
      transition:background .15s; white-space:nowrap; flex-shrink:0;
    }
    .share-btn:hover, .sync-btn:hover { background:rgba(255,255,255,.25); }
    .sync-btn:disabled { opacity:.5; cursor:default; }
    .sync-btn:disabled:hover { background:rgba(255,255,255,.15); }
    @keyframes spin { to { transform:rotate(360deg); } }
    .sync-icon { display:inline-block; }
    .sync-btn.syncing .sync-icon { animation:spin .8s linear infinite; }

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
    .pill-pitched:hover, .pill-int:hover, .pill-passed:hover,
    .pill-signed:hover, .pill-published:hover { opacity:.85; }
    .pill-pitched.filter-active   { box-shadow:0 0 0 2px #fff, 0 0 0 4px #94a3b8; }
    .pill-int.filter-active       { box-shadow:0 0 0 2px #fff, 0 0 0 4px #16a34a; }
    .pill-passed.filter-active    { box-shadow:0 0 0 2px #fff, 0 0 0 4px #dc2626; }
    .pill-signed.filter-active    { box-shadow:0 0 0 2px #fff, 0 0 0 4px #7c3aed; }
    .pill-published.filter-active { box-shadow:0 0 0 2px #fff, 0 0 0 4px #0369a1; }

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
      padding:.18rem .55rem; border-radius:999px; white-space:nowrap;
      line-height:1; display:inline-flex; align-items:center;
    }
    .badge-interested  { background:#dcfce7; color:#166534; }
    .badge-passed      { background:#fee2e2; color:#991b1b; }
    .badge-pitched     { background:#e2e8f0; color:#334155; }
    .badge-signed      { background:#7c3aed; color:#fff; }
    .badge-published   { background:#0369a1; color:#fff; }
    .badge-returned    { background:#f97316; color:#fff; }
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

    /* ── Game link pills ─────────────────────────────── */
    .game-links {
      background:#1a1a2e;
      padding:.5rem 1rem .55rem;
      display:flex; gap:.35rem; flex-wrap:wrap; align-items:center;
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

    /* ── Game sub-bar action buttons (dark bg) ──────── */
    .game-action-btn {
      font-family:'DINBlack',sans-serif; font-size:.6rem;
      text-transform:uppercase; letter-spacing:.06em;
      background:rgba(255,255,255,.14); color:rgba(255,255,255,.85);
      border:1px solid rgba(255,255,255,.28); border-radius:999px;
      padding:.18rem .65rem; cursor:pointer; white-space:nowrap; flex-shrink:0;
      transition:background .15s;
    }
    .game-action-btn:hover { background:rgba(255,255,255,.28); color:#fff; }

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
    .add-entry-actions { display:flex; justify-content:flex-end; gap:.6rem; margin-top:.1rem; }
    .add-cancel-btn {
      font-family:'DINBlack',sans-serif; font-size:.72rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:none; color:#999; border:1px solid #ddd;
      border-radius:6px; padding:.45rem .9rem; cursor:pointer;
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
      outline:none; width:100%; box-sizing:border-box; background:#fff;
      transition:border-color .15s;
    }
    .notes-field-input:focus { border-color:#1a1a2e; }
    .notes-edit-area {
      width:100%; min-height:6rem; font-family:'DINRegular',sans-serif;
      font-size:.88rem; line-height:1.7; color:#222;
      border:1px solid #ddd; border-radius:6px;
      padding:.5rem .65rem; resize:vertical; outline:none;
      box-sizing:border-box; transition:border-color .15s;
    }
    .notes-edit-area:focus { border-color:#1a1a2e; }
    .notes-dialog-actions {
      display:flex; gap:.5rem; align-items:center;
      border-top:1px solid #f0f0f0; padding-top:.6rem; margin-top:.1rem;
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
      color:#999; cursor:pointer; background:none; border:none;
      margin-left:auto;
    }
    .notes-close:hover { color:#333; }

    /* ── Share dialog ────────────────────────────────── */
    .share-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.45); z-index:1000;
      align-items:center; justify-content:center;
    }
    .share-overlay.open { display:flex; }
    .share-dialog {
      background:#fff; border-radius:10px;
      padding:1.4rem; width:min(460px,92vw);
      box-shadow:0 8px 32px rgba(0,0,0,.22);
    }
    .share-dialog h2 { font-family:'DINBlack',sans-serif; font-size:.95rem; margin:0 0 .25rem; }
    .share-dialog p  { font-size:.8rem; color:#666; margin:0 0 .9rem; }
    .share-url-row   { display:flex; gap:.45rem; align-items:stretch; }
    .share-url-input {
      flex:1; font-size:.75rem; border:1px solid #ccc;
      border-radius:6px; padding:.45rem .7rem;
      color:#333; background:#f8f8f8;
      overflow:hidden; text-overflow:ellipsis;
      white-space:nowrap; outline:none;
    }
    .copy-btn {
      font-family:'DINBlack',sans-serif; font-size:.72rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:#1a1a2e; color:#fff; border:none;
      border-radius:6px; padding:.45rem .9rem;
      cursor:pointer; white-space:nowrap;
      transition:background .15s; flex-shrink:0;
    }
    .copy-btn:hover  { background:#2d2d50; }
    .copy-btn.copied { background:#16a34a; }
    .share-close {
      display:block; margin-top:.9rem; text-align:right;
      font-size:.78rem; color:#999; cursor:pointer;
      background:none; border:none; font-family:'DINRegular',sans-serif;
    }
    .share-close:hover { color:#333; }

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
    }
    .game-edit-overlay.open { display:flex; }
    .game-edit-dialog {
      background:#fff; border-radius:10px;
      padding:1.4rem 1.5rem; width:min(580px,94vw);
      max-height:90vh; overflow-y:auto;
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
    .ge-actions {
      display:flex; gap:.5rem; align-items:center;
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
      color:#999; cursor:pointer; background:none; border:none;
      margin-left:auto;
    }
    .ge-cancel-btn:hover { color:#333; }
  </style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
  <div class="top-bar-inner">
    <div class="top-bar-left">
      <h1>Pitchboard</h1>
      <p class="sub" id="subTitle">Loading…</p>
      <p class="sub version-tag" id="versionTag" style="display:none"></p>
    </div>
    <div class="view-toggle">
      <button id="btnDashboard"              onclick="setView('dashboard')">Dashboard</button>
      <button id="btnGame"      class="active" onclick="setView('game')">Games</button>
      <button id="btnPublisher"               onclick="setView('publisher')">Publishers</button>
    </div>
    <button class="sync-btn" id="syncBtn" onclick="syncData()"><span class="sync-icon">&#8635;</span> Sync</button>
    <button class="share-btn" onclick="openShare()">&#8679; Share</button>
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
    <div class="notes-dialog-actions">
      <button class="notes-update-btn" id="notesUpdateBtn" onclick="submitNotesUpdate()">Update</button>
      <button class="notes-close" onclick="closeNotesDialog()">Close</button>
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
    <div class="ge-section">Designers</div>
    <div class="ge-row">
      <label class="ge-label">Designer 1<input type="text" id="geDesigner1" class="ge-input" /></label>
      <label class="ge-label">Designer 2<input type="text" id="geDesigner2" class="ge-input" /></label>
    </div>
    <div class="ge-row">
      <label class="ge-label">Designer 3<input type="text" id="geDesigner3" class="ge-input" /></label>
      <label class="ge-label">Designer 4<input type="text" id="geDesigner4" class="ge-input" /></label>
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
      <button class="ge-save-btn" id="geSaveBtn" onclick="submitGameEdit()">Save</button>
      <button class="ge-cancel-btn" onclick="closeGameEditDialog()">Cancel</button>
    </div>
  </div>
</div>

<!-- Add Entry dialog -->
<div class="add-entry-overlay" id="addEntryOverlay" onclick="if(event.target===this)closeAddDialog()">
  <div class="add-entry-dialog">
    <h2 class="add-entry-title">Add Entry</h2>
    <div class="add-game-label" id="addGameLabel"></div>
    <div class="add-entry-fields">
      <!-- Dropdowns shown when launched from game header -->
      <div id="addPubContactSection">
        <label>Publisher
          <div class="add-select-row">
            <select id="addPublisherSel" onchange="onPublisherChange()">
              <option value="">— select publisher —</option>
            </select>
            <button class="add-new-btn" id="addNewPubBtn" type="button" onclick="openAddNew('publisher')">+ New</button>
          </div>
        </label>
        <label style="margin-top:.5rem">Contact
          <div class="add-select-row">
            <select id="addContactSel">
              <option value="">— optional —</option>
            </select>
            <button class="add-new-btn" type="button" onclick="openAddNew('contact')">+ New</button>
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

<!-- Share dialog -->
<div class="share-overlay" id="shareOverlay" onclick="if(event.target===this)closeShare()">
  <div class="share-dialog">
    <h2>Share Pitches</h2>
    <p>Send this link to share your pitch data.</p>
    <div class="share-url-row">
      <input class="share-url-input" id="shareUrl" type="text" readonly />
      <button class="copy-btn" id="copyBtn" onclick="copyUrl()">Copy</button>
    </div>
    <button class="share-close" onclick="closeShare()">Close</button>
  </div>
</div>

<!-- Sync dialog -->
<div class="sync-overlay" id="syncOverlay">
  <div class="sync-dialog">
    <h2 id="syncDialogTitle">Syncing…</h2>
    <div class="sync-log" id="syncLog"></div>
    <button class="sync-done-btn" id="syncDoneBtn" disabled onclick="closeSyncDialog()">Done</button>
  </div>
</div>

<div class="summary-bar" id="summaryBar"></div>
<div class="search-bar" id="searchBar">
  <div class="search-wrap" id="searchWrap">
    <input type="text" id="searchInput" placeholder="Search games, publishers, contacts…" oninput="applySearch()" />
    <button class="search-clear" id="searchClear" onclick="clearSearch()" title="Clear">✕</button>
  </div>
  <div class="sort-toggle">
    <button id="btnSortDate"  class="active" onclick="setSort('date')">Date</button>
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
var allPitches      = [];
var filteredPitches = [];
var searchQuery     = '';
var activeFilters   = {};   // keys: 'signed', 'published'
var peopleIndex     = {};   // "Name|Company" → email
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
  var pairCounts = {pitched:0, interested:0, passed:0};
  Object.keys(gamePubMap).forEach(function(g) {
    Object.keys(gamePubMap[g]).forEach(function(p) {
      var latest = latestEntry(gamePubMap[g][p]);
      var s = (latest.Status||'').toLowerCase();
      if (s === 'interested') pairCounts.interested++;
      else if (s === 'passed') pairCounts.passed++;
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
    filterBtn('pill-pitched', 'pitched',   'pitched',   pairCounts.pitched) +
    filterBtn('pill-int',     'interested','interested', pairCounts.interested) +
    filterBtn('pill-passed',  'passed',    'passed',     pairCounts.passed) +
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
  currentSort = s;
  document.getElementById('btnSortDate').classList.toggle('active',  s==='date');
  document.getElementById('btnSortAlpha').classList.toggle('active', s==='alpha');
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
                  activeFilters.interested || activeFilters.passed || activeFilters.pitched;
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
      if (!keep && (activeFilters.interested || activeFilters.passed || activeFilters.pitched)) {
        // Check if any publisher's latest status matches an active status filter
        keep = Object.keys(games[name]).some(function(p) {
          var pe = [];
          Object.keys(games[name][p]).forEach(function(c){
            games[name][p][c].forEach(function(e){ pe.push(e); });
          });
          var s = (latestEntry(pe).Status||'').toLowerCase();
          return (activeFilters.interested && s === 'interested') ||
                 (activeFilters.passed     && s === 'passed') ||
                 (activeFilters.pitched    && s === 'pitched');
        });
      }
      if (!keep) delete games[name];
    });
  }

  // Sort games
  var gameNames = Object.keys(games).sort(function(a,b) {
    if (currentSort === 'alpha') return a.localeCompare(b);
    // Date sort: most recent first; unpitched (no dates) fall to end, then alpha
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
    if (db !== da) return db - da;
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
    var designers = ['Designer1','Designer2','Designer3','Designer4']
      .map(function(f){ return (gameInfo[f]||'').trim(); })
      .filter(function(v){ return v; })
      .join(', ');
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
      var playbookId = gfield(['Playbook Sheet ID','Playbook ID','Sheet ID']);
      var linkDefs = [
        { label:'Rules',     url: gfield(['Rules','Rules URL','Rules Link','Link Rules']) },
        { label:'Play',      url: gfield(['Play','Play URL','Play Link','Link Play']) },
        { label:'Print',     url: gfield(['Print','Print URL','Print Link','Link Print']) },
        { label:'Sellsheet', url: gfield(['Sellsheet URL','Sellsheet','Sell Sheet URL','Sell Sheet','Link Sellsheet']) },
        { label:'View',      url: gfield(['View','View URL','Link View','Website','BGG','BGG URL','BGG Link']) },
        { label:'Video',     url: gfield(['Video','Video URL','Video Link','Link Video','YouTube','YouTube URL']) },
        { label:'Info',      url: playbookId ? APP_BASE + 'sheets/' + playbookId + '/view' : '' }
      ];
      var out = '';
      linkDefs.forEach(function(lp) {
        if (lp.url) {
          out += '<a class="game-link-pill" href="' + escHtml(lp.url) +
                 '" target="_blank" rel="noopener">' + escHtml(lp.label) + '</a>';
        }
      });
      return out;
    })();

    html += '<div class="card-body-wrap"><div class="card-body">';
    html += '<div class="game-links">';
    if (designers) html += '<span class="game-links-designers">' + escHtml(designers) + '</span>';
    html += gameLinkPills;
    html += '<span style="flex:1"></span>';
    html += '<button class="game-action-btn" data-game="' + escHtml(g) + '" onclick="event.stopPropagation();addBtnClick(this)">Pitch</button>';
    html += '<button class="game-action-btn" data-game="' + escHtml(g) + '" onclick="event.stopPropagation();editGameClick(this)">Edit</button>';
    html += '</div>';

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

      var isPassed = pubStatus === 'passed';
      var isSigned = pubStatus === 'signed';

      // Publisher status badge
      var pubBadge = '';
      if (isPassed) {
        pubBadge = '<span class="badge badge-passed" style="margin-right:.75rem">Passed</span>';
      } else if (isSigned) {
        pubBadge = '<span class="badge badge-signed" style="margin-right:.75rem">Signed</span>';
      } else if (pubStatus === 'interested') {
        pubBadge = '<span class="badge badge-interested" style="margin-right:.75rem">Interested</span>';
      } else if (pubStatus === 'returned') {
        pubBadge = '<span class="badge badge-returned" style="margin-right:.75rem">Returned</span>';
      } else if (pubStatus === 'pitched') {
        pubBadge = '<span class="badge badge-pitched" style="margin-right:.75rem">Pitched</span>';
      }

      var headerColor = isPassed ? 'color:#aaa;' : 'color:#333;';
      var altClass    = pubIdx % 2 === 1 ? ' pub-alt' : '';
      html += '<div class="sub-group' + altClass + '">';
      var pubLastContact = pubLatest.Contact || '';
      var pubAddBtn = '<button class="add-entry-btn"' +
        ' data-game="'      + escHtml(g) + '"' +
        ' data-publisher="' + escHtml(p) + '"' +
        ' data-contact="'   + escHtml(pubLastContact) + '"' +
        ' data-pub-locked="1"' +
        ' onclick="event.stopPropagation();addBtnClick(this)">+ Add</button>';
      html += '<div class="sub-label pub-passed-header" onclick="togglePubPassed(this)" style="' + headerColor + 'font-size:.75rem">' +
              '<span class="pub-title-group"><span>' + escHtml(p) + '</span>' + pubAddBtn + '</span>' +
              (isPassed || isSigned ? '' : pubAgeTag) + pubBadge +
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

  return html || '<div class="empty">No results.</div>';
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
                  activeFilters.interested || activeFilters.passed || activeFilters.pitched;
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
        if (!keep && (activeFilters.interested || activeFilters.passed || activeFilters.pitched)) {
          var s = (latestEntry(entries).Status||'').toLowerCase();
          keep = (activeFilters.interested && s === 'interested') ||
                 (activeFilters.passed     && s === 'passed') ||
                 (activeFilters.pitched    && s === 'pitched');
        }
        if (!keep) delete pubs[p][g];
      });
      if (Object.keys(pubs[p]).length === 0) delete pubs[p];
    });
  }

  // Sort publishers
  var pubNames = Object.keys(pubs).sort(function(a,b) {
    if (currentSort === 'alpha') return a.localeCompare(b);
    function maxDate(p) {
      var dates = [];
      Object.keys(pubs[p]).forEach(function(g) {
        Object.keys(pubs[p][g]).forEach(function(c) {
          pubs[p][g][c].forEach(function(e){ dates.push(new Date(e.Date)); });
        });
      });
      return Math.max.apply(null, dates);
    }
    return maxDate(b)-maxDate(a);
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
      } else if (gStatus === 'pitched') {
        gBadge = '<span class="badge badge-pitched" style="margin-right:.75rem">Pitched</span>';
      } else {
        gBadge = '';
      }

      var gStatusDate = (gamePublished || gameSigned) ? gameStatusDateStr(g, gamePublished, gameSigned) : '';
      var gStatusDateHtml = gStatusDate ? '<span class="status-date">' + escHtml(gStatusDate) + '</span>' : '';
      var gHeaderColor = gIsPassed ? 'color:#aaa;' : 'color:#333;';
      var gAltClass = gameNames.indexOf(g) % 2 === 1 ? ' pub-alt' : '';
      html += '<div class="sub-group' + gAltClass + '">';
      html += '<div class="sub-label pub-passed-header" onclick="togglePubPassed(this)" style="' + gHeaderColor + 'font-size:.75rem">' +
              '<span style="flex:1">' + escHtml(g) + '</span>' +
              (gamePublished || gameSigned || gIsPassed ? '' : gAgeTag) + gBadge + gStatusDateHtml +
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

  return html || '<div class="empty">No results.</div>';
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
  (people||[]).forEach(function(p) {
    var key = (p.Name||'').trim() + '|' + (p.Company||'').trim();
    if (p.Email) peopleIndex[key] = p.Email;
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

// ── Resolve email for a contact+publisher ─────────────
function resolveEmail(contact, publisher, fallbackEmail) {
  if (fallbackEmail) return fallbackEmail;
  var key = (contact||'').trim() + '|' + (publisher||'').trim();
  return peopleIndex[key] || '';
}

// ── Build mailto body with contact info + game links ──
function buildEmailBody(gameName) {
  var lines = [];
  if (myName)  lines.push(myName);
  if (myPhone) lines.push(myPhone);
  if (myEmail) lines.push(myEmail);

  var info = gamesIndex[gameName] || {};
  function field(keys) {
    for (var i = 0; i < keys.length; i++) {
      var v = (info[keys[i]] || '').trim();
      if (v) return v;
    }
    return '';
  }
  var rulesUrl = field(['Rules', 'Rules URL', 'Rules Link', 'Link Rules']);
  var printUrl = field(['Print', 'Print URL', 'Print Link', 'Link Print']);
  var playUrl  = field(['Play',  'Play URL',  'Play Link',  'Link Play']);

  var gameLines = [];
  if (gameName) gameLines.push('Title: ' + gameName);
  if (rulesUrl) gameLines.push('Rules: ' + rulesUrl);
  if (printUrl) gameLines.push('Print: ' + printUrl);
  if (playUrl)  gameLines.push('Play: '  + playUrl);

  if (gameLines.length) {
    lines.push('');
    lines = lines.concat(gameLines);
  }
  return lines.join('\n');
}

// ── Build mailto href ─────────────────────────────────
function mailtoHref(email, gameName) {
  if (!email) return '';
  var body = buildEmailBody(gameName);
  return 'mailto:' + email
    + '?subject=' + encodeURIComponent(gameName)
    + (body ? '&body=' + encodeURIComponent(body) : '');
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
      alert('Error: ' + ((result && result.error) || 'Could not update row.'));
    }
  };
  xhr.onerror = function() {
    btn.disabled = false;
    btn.textContent = 'Update';
    alert('Network error — could not update.');
  };
  xhr.send(body);
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

  // Helper: try several field name variants in g
  function gfield() {
    for (var i = 0; i < arguments.length; i++) {
      var v = g[arguments[i]]; if (v) return v;
    }
    return '';
  }

  document.getElementById('geGameName').value  = isNew ? '' : (g.Name || gameName);
  document.getElementById('geDesigner1').value = isNew ? '' : gfield('Designer1', 'Designer 1');
  document.getElementById('geDesigner2').value = isNew ? '' : gfield('Designer2', 'Designer 2');
  document.getElementById('geDesigner3').value = isNew ? '' : gfield('Designer3', 'Designer 3');
  document.getElementById('geDesigner4').value = isNew ? '' : gfield('Designer4', 'Designer 4');
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
    designer1:  document.getElementById('geDesigner1').value.trim(),
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
    alert('Game name is required.');
    btn.disabled = false;
    btn.textContent = isNew ? 'Add Game' : 'Save';
    return;
  }

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
      // Update gamesIndex in memory
      var oldName = _gameEditCtx.origName;
      var newName = payload.name;
      if (!gamesIndex[newName]) gamesIndex[newName] = {};
      var entry = gamesIndex[newName];
      entry.Name       = newName;
      entry.Designer1  = payload.designer1;
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
      alert('Error: ' + ((result && result.error) || (isNew ? 'Could not add game.' : 'Could not update game.')));
    }
  };
  xhr.onerror = function() {
    btn.disabled = false;
    btn.textContent = isNew ? 'Add Game' : 'Save';
    alert('Network error — could not save.');
  };
  xhr.send(body);
}

// Close dialogs on Escape (sub-dialog first, then main, then notes)
document.addEventListener('keydown', function(ev) {
  if (ev.key !== 'Escape') return;
  if (document.getElementById('gameEditOverlay').classList.contains('open')) { closeGameEditDialog(); return; }
  if (document.getElementById('addNewOverlay').classList.contains('open')) { closeAddNew(); return; }
  if (document.getElementById('addEntryOverlay').classList.contains('open')) { closeAddDialog(); return; }
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

function populatePublishers(selected) {
  var sel = document.getElementById('addPublisherSel');
  sel.innerHTML = '<option value="">— select publisher —</option>';
  getPublisherList().forEach(function(p) {
    var o = document.createElement('option');
    o.value = p; o.textContent = p;
    if (p === selected) o.selected = true;
    sel.appendChild(o);
  });
}

function populateContacts(publisher, selected) {
  var sel = document.getElementById('addContactSel');
  sel.innerHTML = '<option value="">— optional —</option>';
  getContactsForPublisher(publisher).forEach(function(c) {
    var o = document.createElement('option');
    o.value = c; o.textContent = c;
    if (c === selected) o.selected = true;
    sel.appendChild(o);
  });
}

function onPublisherChange() {
  populateContacts(document.getElementById('addPublisherSel').value, '');
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
  _addCtx = { game: game, publisher: publisher, contact: contact, locked: bothLocked, pubLocked: !!pubLocked };

  document.getElementById('addGameLabel').textContent = game;

  if (bothLocked) {
    document.getElementById('addPubContactSection').style.display = 'none';
    document.getElementById('addLockedSection').style.display     = '';
    document.getElementById('addLockedSection').textContent       = publisher + '  ·  ' + contact;
  } else {
    document.getElementById('addPubContactSection').style.display = '';
    document.getElementById('addLockedSection').style.display     = 'none';
    var pubSel    = document.getElementById('addPublisherSel');
    var newPubBtn = document.getElementById('addNewPubBtn');
    if (pubLocked) {
      populatePublishers(publisher);
      populateContacts(publisher, contact);
      pubSel.disabled            = true;
      pubSel.style.opacity       = '.5';
      pubSel.style.pointerEvents = 'none';
      if (newPubBtn) newPubBtn.style.display = 'none';
    } else {
      populatePublishers(publisher);
      populateContacts(publisher, contact);
      pubSel.disabled            = false;
      pubSel.style.opacity       = '';
      pubSel.style.pointerEvents = '';
      if (newPubBtn) newPubBtn.style.display = '';
    }
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
    (locked
      ? document.getElementById('addEvent')
      : document.getElementById('addPublisherSel')
    ).focus();
  }, 60);
}

function closeAddDialog() {
  document.getElementById('addEntryOverlay').classList.remove('open');
}

function submitAddEntry() {
  var dateVal = document.getElementById('addDate').value;
  if (!dateVal) { document.getElementById('addDate').focus(); return; }

  var publisher, contact;
  if (_addCtx.locked) {
    publisher = _addCtx.publisher;
    contact   = _addCtx.contact;
  } else if (_addCtx.pubLocked) {
    publisher = _addCtx.publisher;
    contact   = document.getElementById('addContactSel').value;
  } else {
    publisher = document.getElementById('addPublisherSel').value;
    contact   = document.getElementById('addContactSel').value;
    if (!publisher) { document.getElementById('addPublisherSel').focus(); return; }
  }

  var dp = dateVal.split('-');
  var sheetDate = parseInt(dp[1]) + '/' + parseInt(dp[2]) + '/' + dp[0];
  var eventVal  = document.getElementById('addEvent').value.trim();
  var statusVal = document.getElementById('addStatus').value;
  var notesVal  = document.getElementById('addNotes').value.trim();

  var btn = document.getElementById('addSubmitBtn');
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
      alert('Error: ' + ((result && result.error) || xhr.responseText || 'Unknown error'));
    }
  };
  xhr.onerror = function() {
    btn.disabled = false; btn.textContent = 'Add';
    alert('Network error — could not add entry.');
  };
  xhr.send(body);
}

// ── New Publisher / New Contact sub-dialog ────────────
function openAddNew(mode) {
  _addNewMode = mode;
  var isContact = mode === 'contact';
  document.getElementById('addNewTitle').textContent = isContact ? 'New Contact' : 'New Publisher';

  var fields = document.getElementById('addNewFields');
  if (isContact) {
    var pub = document.getElementById('addPublisherSel').value;
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
    var sel = document.getElementById('addPublisherSel');
    // Check not already in list
    var exists = Array.from(sel.options).some(function(o){ return o.value === name; });
    if (!exists) {
      var opt = document.createElement('option');
      opt.value = name; opt.textContent = name;
      sel.appendChild(opt);
    }
    sel.value = name;
    populateContacts(name, '');
    closeAddNew();

  } else {
    // Contact — write to people sheet
    var emailEl = document.getElementById('addNewEmail');
    var email     = emailEl ? emailEl.value.trim() : '';
    var publisher = document.getElementById('addPublisherSel').value;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/addPerson.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      btn.disabled = false; btn.textContent = 'Add';
      var result;
      try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }
      if (result && result.ok) {
        // Update local people index
        var key = name + '|' + publisher;
        if (email) peopleIndex[key] = email;
        // Refresh contact dropdown
        populateContacts(publisher, name);
        closeAddNew();
      } else {
        alert('Error: ' + ((result && result.error) || 'Could not save contact'));
      }
    };
    xhr.onerror = function() {
      btn.disabled = false; btn.textContent = 'Add';
      alert('Network error — could not save contact.');
    };
    xhr.send(
      'id='      + encodeURIComponent(sheet_Id) +
      '&name='   + encodeURIComponent(name) +
      '&company='+ encodeURIComponent(publisher) +
      '&email='  + encodeURIComponent(email)
    );
  }
}


// ── Share ─────────────────────────────────────────────
function openShare() {
  document.getElementById('shareUrl').value =
    window.location.origin + '/' + sheet_Id + '/pitches.json';
  var btn = document.getElementById('copyBtn');
  btn.textContent = 'Copy'; btn.classList.remove('copied');
  document.getElementById('shareOverlay').classList.add('open');
}
function closeShare() { document.getElementById('shareOverlay').classList.remove('open'); }
function copyUrl() {
  var input = document.getElementById('shareUrl');
  var btn   = document.getElementById('copyBtn');
  input.select();
  navigator.clipboard.writeText(input.value).then(function() {
    btn.textContent='Copied!'; btn.classList.add('copied');
    setTimeout(function(){ btn.textContent='Copy'; btn.classList.remove('copied'); }, 2000);
  }).catch(function() {
    try { document.execCommand('copy'); } catch(e){}
    btn.textContent='Copied!'; btn.classList.add('copied');
    setTimeout(function(){ btn.textContent='Copy'; btn.classList.remove('copied'); }, 2000);
  });
}

// ── Load ──────────────────────────────────────────────
function loadJSON(url, key, fallbackUrl, onDone) {
  var xhr = new XMLHttpRequest();
  xhr.open('GET', url + '?v=' + Date.now());
  xhr.onload = function() {
    if (xhr.status === 200) {
      var data; try { data = JSON.parse(xhr.responseText); } catch(e) { data = []; }
      onDone(key, data);
    } else if (fallbackUrl) {
      loadJSON(fallbackUrl, key, null, onDone);
    } else {
      onDone(key, []);
    }
  };
  xhr.onerror = function() {
    if (fallbackUrl) { loadJSON(fallbackUrl, key, null, onDone); }
    else { onDone(key, []); }
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
  loadJSON(BASE + 'pitches.json',  'pitches',  BASE + 'connections.json', done);
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

// ── Sync dialog helpers ───────────────────────────────
function openSyncDialog() {
  document.getElementById('syncLog').innerHTML = '';
  document.getElementById('syncDialogTitle').textContent = 'Syncing…';
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

// ── Sync (push then reload) ───────────────────────────
function syncData() {
  var btn = document.getElementById('syncBtn');
  btn.disabled = true;
  btn.classList.add('syncing');

  openSyncDialog();

  var sheets   = ['pitches', 'games', 'people', 'settings'];
  var pushBase = APP_BASE + 'push/pushSheetUpdate.php';
  var idx = 0;

  function finish() {
    syncLog('Reloading data…', 'info');
    loadAll(function() {
      syncLog('Done.', 'ok');
      document.getElementById('syncDialogTitle').textContent = 'Sync Complete';
      document.getElementById('syncDoneBtn').disabled = false;
      btn.disabled = false;
      btn.classList.remove('syncing');
    });
  }

  function deploySource() {
    syncLog('Deploying source files…', 'info');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/deploySource.php');
    xhr.onload = function() {
      var lines = (xhr.responseText || '').trim().split('\n');
      lines.forEach(function(line) {
        line = line.trim();
        if (!line) return;
        if (line.indexOf('ERROR') === 0) {
          syncLog('  ✗ ' + line, 'error');
        } else if (line.indexOf('SKIP') === 0) {
          syncLog('  – ' + line.replace(/^SKIP:\s*/,''), 'skip');
        } else {
          syncLog('  ✓ ' + line, 'ok');
        }
      });
      finish();
    };
    xhr.onerror = function() {
      syncLog('  ✗ deploy: network error', 'error');
      finish();
    };
    xhr.send();
  }

  function pushNext() {
    if (idx >= sheets.length) {
      syncLog('─────────────────────────────', 'info');
      deploySource();
      return;
    }
    var sheetName = sheets[idx++];
    syncLog('Pushing ' + sheetName + '…', 'info');
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

// Initial load
loadAll();
</script>
</body>
</html>
