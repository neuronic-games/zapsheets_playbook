<?php
/**
 * CardBoard home (/cardboard).
 * Signed out: start the free week or log in. Signed in: the Designs page — every sheet you've set up
 * (and the ones you've viewed), with a picture of its first card; + Create connects a new Google Sheet.
 */

$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/cardboard/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>CardBoard – Card Designer</title>
<?php require_once dirname(__DIR__, 2) . '/push/linkPreview.php'; echo gbLinkPreview('cardboard', '', ['title' => 'CardBoard']); ?>
<link rel="icon" type="image/png" sizes="32x32" href="images/cardboard_icon_32.png" />
<link rel="apple-touch-icon" sizes="180x180" href="images/cardboard_icon_180.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

body {
  background:#f0f4f8;
  font-family:'DINRegular',Arial,sans-serif;
  color:#1a1a1a;
  min-height:100vh;
  display:flex;
  align-items:flex-start;
  justify-content:center;
  padding:2.5rem 1rem 3rem;
}
.page { width:100%; max-width:520px; }

/* Branding */
.brand { text-align:center; margin-bottom:2.25rem; }
.brand-logo { width:84px; height:84px; border-radius:20px; display:block; margin:0 auto .9rem; box-shadow:0 4px 20px rgba(0,0,0,.18); }
.brand-icon {
  width:84px; height:84px; border-radius:20px;
  background:#4b3fa8; display:inline-flex;
  align-items:center; justify-content:center;
  margin-bottom:.9rem;
  box-shadow:0 4px 20px rgba(0,0,0,.18);
}
.brand-icon svg { width:44px; height:44px; }
.brand-name {
  font-family:'DINBlack',sans-serif; font-size:2.1rem;
  text-transform:uppercase; letter-spacing:.09em; color:#111; line-height:1;
}
.brand-tagline { margin-top:.4rem; font-size:.88rem; color:#999; letter-spacing:.03em; }

/* Card */
.card {
  background:#fff; border-radius:16px;
  padding:2rem 2rem 2.25rem;
  box-shadow:0 2px 24px rgba(0,0,0,.08);
  margin-bottom:1rem;
}
.card-title {
  font-family:'DINBlack',sans-serif; font-size:1.05rem;
  text-transform:uppercase; letter-spacing:.07em; color:#111; margin-bottom:.8rem;
}
.instructions { font-size:.88rem; color:#555; line-height:1.65; margin-bottom:1.5rem; }
.instructions ol { padding-left:1.35rem; margin-top:.5rem; }
.instructions li { margin-bottom:.25rem; }
.instructions strong { color:#333; }
.why { margin:.75rem 0 .2rem; border:1px solid #e3e0f5; border-radius:9px; background:#f8f7fe; padding:.45rem .8rem; }
.why summary { cursor:pointer; font-family:'DINBlack',sans-serif; font-size:.78rem; letter-spacing:.05em; text-transform:uppercase; color:#4b3fa8; }
.why[open] summary { margin-bottom:.35rem; }
.why p { margin:.35rem 0; font-size:.84rem; }
.why ul { margin:.3rem 0 .4rem; padding-left:1.15rem; font-size:.84rem; }
.why li { margin-bottom:.25rem; }
.why b { color:#333; }

.copy-email-btn {
  display:inline-flex; align-items:center; gap:.28rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.75rem;
  color:#4b3fa8; background:rgba(75,63,168,.10);
  border:1px solid rgba(75,63,168,.30); border-radius:999px;
  padding:.1rem .45rem; cursor:pointer; vertical-align:middle;
  white-space:nowrap; transition:background .15s;
}
.copy-email-btn:hover { background:rgba(75,63,168,.22); }
.copy-email-btn.copied { color:#16a34a; background:rgba(22,163,74,.10); border-color:rgba(22,163,74,.30); }

/* Input */
.sheet-input {
  display:block; width:100%; padding:.7rem .9rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.95rem; color:#111;
  border:1.5px solid #d0ccc5; border-radius:9px; outline:none;
  transition:border-color .15s; margin-bottom:1rem; background:#fafaf8;
}
.sheet-input:focus { border-color:#4b3fa8; background:#fff; }
.sheet-input.shake { animation:shake .3s ease-in-out; }
@keyframes shake {
  0%,100% { transform:translateX(0); }
  25%      { transform:translateX(-7px); }
  75%      { transform:translateX( 7px); }
}

/* Button */
.connect-btn {
  width:100%; height:2.85rem;
  background:#4b3fa8; color:#fff;
  font-family:'DINBlack',sans-serif; font-size:.9rem;
  text-transform:uppercase; letter-spacing:.09em;
  border:none; border-radius:9px; cursor:pointer;
  transition:background .15s, transform .1s, box-shadow .15s;
  box-shadow:0 2px 8px rgba(75,63,168,.3);
}
.connect-btn:hover:not(:disabled) { background:#3b3190; transform:translateY(-1px); box-shadow:0 4px 14px rgba(75,63,168,.35); }
.connect-btn:active:not(:disabled) { transform:translateY(0); }
.connect-btn:disabled { opacity:.5; cursor:default; transform:none; box-shadow:none; }

/* Log */
.log-panel {
  margin-top:1.25rem; background:#0d1117; border-radius:9px;
  padding:.9rem 1rem; max-height:220px; overflow-y:auto;
  font-family:'Courier New',Courier,monospace; font-size:.79rem; line-height:1.8;
  scrollbar-width:thin; scrollbar-color:#333 transparent;
}
.log-panel::-webkit-scrollbar { width:4px; }
.log-panel::-webkit-scrollbar-thumb { background:#333; border-radius:2px; }
.ll     { display:block; white-space:pre-wrap; }
.ll.inf { color:#8b9bba; }
.ll.ok  { color:#4ade80; }
.ll.err { color:#ff8a80; }

/* Welcome: start the free week or log in, before anything else */
.choose { display:flex; flex-direction:column; gap:.7rem; }
.opt { display:flex; flex-direction:column; align-items:center; gap:.2rem; padding:1rem; border:1.5px solid #d6d9e6; border-radius:11px; background:#fff; color:#2e2763; cursor:pointer; font:inherit; transition:border-color .12s, background .12s, transform .1s; }
.opt b { font-family:'DINBlack',sans-serif; font-weight:400; font-size:1rem; letter-spacing:.04em; text-transform:uppercase; }
.opt span { font-size:.8rem; color:#8a8fa8; }
.opt:hover { border-color:#a99cff; background:#f7f5ff; }
.opt.primary { background:#4b3fa8; border-color:#4b3fa8; color:#fff; box-shadow:0 2px 8px rgba(75,63,168,.3); }
.opt.primary span { color:rgba(255,255,255,.78); }
.opt.primary:hover { background:#3b3190; transform:translateY(-1px); }
.auth-row { display:flex; gap:.5rem; }
.auth-col { display:flex; flex-direction:column; gap:.6rem; }
.auth-col .sheet-input { margin:0; }
.auth-col .connect-btn { width:100%; margin:0; }
.pw-rules { list-style:none; margin:.1rem 0 .2rem; padding:0; display:grid; grid-template-columns:1fr 1fr; gap:.25rem .9rem; font-size:.8rem; color:#9aa0b8; }
.pw-rules li { display:flex; align-items:center; gap:.45rem; transition:color .15s; }
.pw-rules li::before { content:''; width:1rem; height:1rem; flex-shrink:0; border-radius:50%; border:1.5px solid #c9cce0; box-sizing:border-box; transition:background .15s, border-color .15s; }
.pw-rules li.ok { color:#2e7d32; }
.pw-rules li.ok::before { border-color:#2e9d4c; background:#2e9d4c url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.5l4.5 4.5L19 7.5'/%3E%3C/svg%3E") center/70% no-repeat; }
/* Signed out: CardBoard's purple, like the designer's sign-in page */
body.signed-out { background:#2e2763; }
body.signed-out .brand-name { color:#fff; }
body.signed-out .brand-tagline { color:rgba(255,255,255,.65); }
body.signed-out .card { box-shadow:0 18px 50px rgba(0,0,0,.35); }
.auth-row .sheet-input { margin:0; flex:1; }
.auth-row .connect-btn { width:auto; padding:0 1.1rem; white-space:nowrap; }
.code-input { letter-spacing:.35em; text-align:center; font-family:ui-monospace,Menlo,monospace; }
.auth-msg { font-size:.85rem; color:#666; margin:0 0 .9rem; line-height:1.5; }
.auth-err { color:#c0392b; font-size:.82rem; min-height:1.1rem; margin-top:.6rem; }
.link-btn { background:none; border:none; color:#6b7090; font:inherit; font-size:.82rem; text-decoration:underline; text-underline-offset:2px; cursor:pointer; padding:0; }
.link-btn:hover { color:#2e2763; }
.auth-foot { margin-top:1rem; text-align:center; }
.who { text-align:center; font-size:.82rem; color:#8a8fa8; margin:-.4rem 0 1rem; }
.who b { color:#2e2763; }
[hidden] { display:none !important; }

/* ── Designs page (signed in) ─────────────────────────────────────────── */
body.designs { display:block; padding:0; background:#f3f4f8; }
body.designs .page { display:none; }
.app { display:none; min-height:100vh; }
body.designs .app { display:flex; flex-direction:column; }
.d-top { flex-shrink:0; background:#1a1a2e; color:#fff; display:flex; align-items:center; gap:.75rem; padding:.75rem 1.25rem; position:sticky; top:0; z-index:20; box-shadow:inset 0 -3px 0 #5b4fd6; }   /* the designer's bar */
.d-brand { display:flex; align-items:center; gap:.65rem; text-decoration:none; color:#fff; min-width:0; }
.d-brand img { width:2.2rem; height:2.2rem; border-radius:7px; flex-shrink:0; }
.d-name { display:flex; flex-direction:column; min-width:0; }
.d-name b { font-family:'DINBlack',sans-serif; font-weight:400; font-size:1rem; letter-spacing:.03em; line-height:1.25; }
.d-name small { font-size:.73rem; opacity:.6; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.d-brand .c1 { color:#f2c14e; } .d-brand .c2 { color:#a99cff; }
.d-top .grow { flex:1; }
.d-create { display:inline-flex; align-items:center; justify-content:center; width:6.6rem; height:2.1rem; padding:0; border:none; border-radius:999px; background:#f2c14e; color:#2e2763; font-family:'DINBlack',sans-serif; font-size:.7rem; letter-spacing:.09em; text-transform:uppercase; cursor:pointer; box-shadow:0 2px 8px rgba(0,0,0,.18); transition:background .15s; flex-shrink:0; }   /* the designer's Save button */
.d-create:hover { background:#ffd36b; }
.d-menu-wrap { position:relative; flex-shrink:0; }
.top-btn { background:rgba(255,255,255,.14); border:none; color:#fff; width:2.1rem; height:2.1rem; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; }
.top-btn:hover { background:rgba(255,255,255,.24); }
.top-btn { overflow:hidden; padding:0; }
.top-pic { width:100%; height:100%; object-fit:cover; border-radius:50%; }
.main-menu { display:none; position:absolute; top:calc(100% + .4rem); right:0; background:#1a1a2e; border:1px solid rgba(255,255,255,.18); border-radius:8px; min-width:190px; z-index:300; box-shadow:0 8px 24px rgba(0,0,0,.4); padding:.25rem 0; }
.main-menu.open { display:block; }
.mm-who { padding:.55rem 1rem .5rem; font-size:.75rem; color:rgba(255,255,255,.55); border-bottom:1px solid rgba(255,255,255,.12); margin-bottom:.2rem; word-break:break-all; }
.mm-who b { display:block; color:#fff; font-size:.85rem; }
.mm-item { display:block; width:100%; background:none; border:none; color:rgba(255,255,255,.85); text-align:left; cursor:pointer; font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; padding:.6rem 1rem; text-decoration:none; }
.mm-item:hover { background:rgba(255,255,255,.1); color:#fff; }
.mm-div { border:none; border-top:1px solid rgba(255,255,255,.12); margin:.2rem 0; }
.d-av { border-radius:50%; background:#f2c14e; color:#1a1a2e; display:inline-flex; align-items:center; justify-content:center; font-family:'DINBlack',sans-serif; flex-shrink:0; }
.d-body { flex:1; display:flex; min-height:0; }
.d-side { width:250px; flex-shrink:0; background:#fff; border-right:1px solid #e1e4ee; display:flex; flex-direction:column; }
.d-me { text-align:center; padding:1.6rem 1rem 1.3rem; border-bottom:1px solid #e1e4ee; }
.d-me .d-av { width:84px; height:84px; font-size:2.1rem; border:none; box-shadow:0 3px 12px rgba(46,39,99,.2); }
.d-pic { position:relative; overflow:hidden; cursor:pointer; }
.d-pic img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
.d-pic .cam { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(26,26,46,.5); color:#fff; opacity:0; transition:opacity .15s; }
.d-pic .cam svg { width:30px; height:30px; }
.d-pic:hover .cam, .d-pic.drop .cam, .d-pic.busy .cam { opacity:1; }
.d-pic.drop { outline:3px dashed #5b4fd6; outline-offset:3px; }
.d-pic.busy .cam svg { display:none; }
.d-pic.busy .cam::after { content:''; width:26px; height:26px; border:3px solid rgba(255,255,255,.35); border-top-color:#fff; border-radius:50%; animation:dspin .7s linear infinite; }
@keyframes dspin { to { transform:rotate(360deg); } }
.d-me .nm { margin-top:.7rem; font-family:'DINBlack',sans-serif; font-size:1.05rem; color:#1f1b47; word-break:break-word; }
.d-me .em { font-size:.78rem; color:#8a8fa8; word-break:break-all; margin-top:.15rem; }
.d-me .pl { display:inline-block; margin-top:.55rem; font-size:.74rem; padding:.15rem .6rem; border-radius:999px; background:#f1effc; color:#4b3fa8; }
.d-nav { padding:.8rem .7rem; display:flex; flex-direction:column; gap:.25rem; }
.d-nav a, .d-nav button { display:flex; align-items:center; gap:.85rem; padding:.7rem .9rem; border-radius:12px; color:#2b2d42; text-decoration:none; font:inherit; font-size:1rem; background:none; border:2px solid transparent; cursor:pointer; text-align:left; }
.d-nav a:hover, .d-nav button:hover { background:#f4f3fb; }
.d-nav .on { border-color:#4b3fa8; color:#4b3fa8; background:#f7f5ff; }
.d-nav svg { width:22px; height:22px; flex-shrink:0; }
.d-side .foot { margin-top:auto; padding:1rem; border-top:1px solid #e1e4ee; text-align:center; }
.d-main { flex:1; min-width:0; padding:2rem 2.2rem 3rem; }
.d-head { display:flex; align-items:center; gap:1rem; margin-bottom:1.4rem; }
.d-head h1 { font-family:'DINRegular',sans-serif; font-weight:400; font-size:2rem; color:#1f1b47; flex:1; }
.d-search { position:relative; width:min(380px, 45vw); }
.d-search input { width:100%; height:44px; border:1.5px solid #cfd2e0; border-radius:999px; padding:0 2.6rem 0 1.1rem; font:inherit; font-size:.98rem; background:#fff; outline:none; }
.d-search input:focus { border-color:#4b3fa8; }
.d-search svg { position:absolute; right:.95rem; top:50%; transform:translateY(-50%); width:20px; height:20px; color:#8a8fa8; pointer-events:none; }
.d-search .x { position:absolute; right:.7rem; top:50%; transform:translateY(-50%); border:none; background:#e6e7ef; width:22px; height:22px; border-radius:50%; cursor:pointer; color:#555; font-size:.9rem; line-height:1; display:none; }
.d-search.has .x { display:block; } .d-search.has svg { display:none; }
.d-bar { display:flex; align-items:center; gap:.4rem; margin-bottom:1.4rem; flex-wrap:wrap; }
.d-chip { display:inline-flex; align-items:center; gap:.5rem; height:44px; padding:0 1.15rem; border:2px solid transparent; border-radius:999px; background:none; color:#4a4e69; font:inherit; font-size:1rem; cursor:pointer; }
.d-chip:hover { background:#e9e8f5; }
.d-chip.on { border-color:#4b3fa8; color:#4b3fa8; background:#fff; }
.d-chip svg { width:20px; height:20px; }
.d-chip .n { font-size:.78rem; color:#8a8fa8; }
.d-bar .grow { flex:1; }
.d-sort { display:inline-flex; align-items:center; gap:.35rem; color:#2b2d42; font-size:.98rem; }
.d-sort select { border:none; background:transparent; font:inherit; font-family:'DINBlack',sans-serif; color:#1f1b47; cursor:pointer; padding:.3rem .2rem; outline:none; }
.d-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); gap:1.4rem; }
.d-card { position:relative; background:#fff; border:1px solid #dfe2ec; border-radius:10px; cursor:pointer; text-decoration:none; color:inherit; display:flex; flex-direction:column; transition:box-shadow .15s, transform .15s, border-color .15s; }
.d-card:hover { box-shadow:0 8px 24px rgba(46,39,99,.14); transform:translateY(-2px); border-color:#c9c4ee; }
.d-thumb { aspect-ratio:4 / 3; background:#eef0f6; border-radius:10px 10px 0 0; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.d-thumb img { max-width:72%; max-height:86%; object-fit:contain; filter:drop-shadow(0 6px 12px rgba(20,20,50,.22)); border-radius:6px; }
.d-thumb .ph { width:34%; aspect-ratio:5 / 7; border-radius:8px; background:#fff; border:2px dashed #c9cde0; display:flex; align-items:center; justify-content:center; color:#b1b5cc; }
.d-thumb .ph svg { width:42%; height:42%; }
.d-info { position:relative; padding:.85rem 1rem .9rem; }
.d-info .t { font-size:1.02rem; color:#1f1b47; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; padding-right:1.6rem; }
.d-info .m { display:flex; justify-content:space-between; align-items:flex-end; gap:.5rem; margin-top:.45rem; font-size:.82rem; color:#7d8199; line-height:1.45; }
.d-info .m .r { display:inline-flex; align-items:center; gap:.3rem; white-space:nowrap; }
.d-info .m svg { width:15px; height:15px; }
.d-tag { color:#4b3fa8; } .d-tag.view { color:#9a8a5a; }
.d-more { position:absolute; right:.4rem; top:.6rem; width:30px; height:30px; border:none; border-radius:50%; background:transparent; color:#7d8199; cursor:pointer; font-size:1.2rem; line-height:1; display:flex; align-items:center; justify-content:center; }
.d-more:hover { background:#eef0f6; color:#2e2763; }
.d-card:has(.d-menu) { z-index:6; }
.d-menu { position:absolute; right:.3rem; top:2.5rem; z-index:5; background:#fff; border:1px solid #dfe2ec; border-radius:10px; box-shadow:0 10px 30px rgba(20,20,50,.18); padding:.35rem; min-width:190px; max-width:calc(100vw - 2rem); }
.d-menu button { display:block; width:100%; text-align:left; border:none; background:none; padding:.55rem .75rem; border-radius:7px; font:inherit; font-size:.9rem; color:#2b2d42; cursor:pointer; }
.d-menu button:hover { background:#f4f3fb; }
.d-menu button.danger { color:#c0392b; border-top:1px solid #eceaf5; border-radius:0 0 7px 7px; margin-top:.2rem; }
.d-menu button.danger:hover { background:#fdf0ee; }
.d-empty { text-align:center; padding:4rem 1rem; color:#6b7090; }
.d-empty b { display:block; font-family:'DINBlack',sans-serif; font-size:1.25rem; color:#1f1b47; margin-bottom:.4rem; }
.d-empty .connect-btn { width:auto; padding:0 1.6rem; margin-top:1.2rem; }
.d-loading { color:#8a8fa8; padding:2rem 0; }
/* Templates: pick one, then Use this layout */
.t-sub { margin:-.9rem 0 1.4rem; color:#6b7090; font-size:.98rem; }
.t-use { height:44px; padding:0 1.4rem; border:none; border-radius:999px; background:#4b3fa8; color:#fff; font-family:'DINBlack',sans-serif; font-size:.82rem; letter-spacing:.08em; text-transform:uppercase; cursor:pointer; white-space:nowrap; }
.t-use:hover:not(:disabled) { background:#3b3190; }
.t-use:disabled { opacity:.4; cursor:default; }
.t-card { position:relative; background:#fff; border:2px solid #dfe2ec; border-radius:12px; cursor:pointer; display:flex; flex-direction:column; text-align:left; padding:0; font:inherit; color:inherit; transition:box-shadow .15s, transform .15s, border-color .15s; }
.t-card:hover { box-shadow:0 8px 24px rgba(46,39,99,.14); transform:translateY(-2px); }
.t-card .d-thumb { border-radius:10px 10px 0 0; }
.t-card .d-info .t { font-family:'DINBlack',sans-serif; }
.t-card .d-info .m { display:block; margin-top:.3rem; white-space:normal; }
.t-card .d-info { display:flex; flex-direction:column; flex:1; }
.t-go { display:block; margin-top:auto; align-self:stretch; text-align:center; height:40px; line-height:40px; margin-top:.9rem; border-radius:999px; background:#4b3fa8; color:#fff; font-family:'DINBlack',sans-serif; font-size:.74rem; letter-spacing:.08em; text-transform:uppercase; cursor:pointer; }
.t-go:hover { background:#3b3190; }
.t-card .d-info .m { flex:1; }
.t-card .ph-t { width:auto; height:70%; aspect-ratio:var(--ar, 5 / 7); border-radius:8px; background:#fff; border:2px dashed #c9cde0; display:flex; align-items:center; justify-content:center; color:#9aa0b8; font-family:'DINBlack',sans-serif; font-size:.8rem; letter-spacing:.06em; text-transform:uppercase; padding:.5rem; text-align:center; }
.tpl-note { display:flex; align-items:center; gap:.8rem; margin-right:2.4rem !important; background:#f3f1ff; border:1px solid #d9d3ff; color:#2e2763; border-radius:10px; padding:.7rem .9rem; margin:0 0 1rem; font-size:.92rem; }
.tpl-note img { width:42px; border-radius:5px; box-shadow:0 2px 6px rgba(20,20,50,.2); }
.tpl-note .grow { flex:1; }
.tpl-note .link-btn { white-space:nowrap; }
.d-toast { position:fixed; left:50%; bottom:1.5rem; transform:translateX(-50%) translateY(300%); opacity:0; background:#2e2763; color:#fff; padding:.6rem 1.1rem; border-radius:9px; font-size:.9rem; transition:transform .2s; z-index:80; }
.d-toast.show { transform:translateX(-50%) translateY(0); opacity:1; }

/* + Create: connect a Google Sheet */
.d-ov { position:fixed; inset:0; background:rgba(20,18,45,.55); display:none; align-items:flex-start; justify-content:center; padding:5vh 1rem; z-index:60; overflow:auto; }
.d-ov.open { display:flex; }
.d-dlg { position:relative; width:100%; max-width:560px; }
.d-dlg .card { margin:0; }
.d-dlg .x-btn { position:absolute; right:1rem; top:1rem; width:32px; height:32px; border:none; border-radius:50%; background:#f1f2f7; color:#555; font-size:1.1rem; cursor:pointer; }
.d-dlg .x-btn:hover { background:#e4e5ee; }
.d-start { display:grid; grid-template-columns:1fr 1fr; gap:.7rem; margin:.2rem 0 1.3rem; }
.d-start a { display:flex; flex-direction:column; gap:.2rem; padding:.85rem .95rem; border:1.5px solid #d6d9e6; border-radius:11px; color:#2e2763; text-decoration:none; transition:border-color .12s, background .12s; }
.d-start a:hover { border-color:#a99cff; background:#f7f5ff; }
.d-start b { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.92rem; }
.d-start span { font-size:.78rem; color:#8a8fa8; line-height:1.4; }
.d-btns { display:flex; gap:.6rem; }
.d-btns .connect-btn { flex:1; }
.cancel-btn { height:2.85rem; padding:0 1.2rem; border:1.5px solid #d6d9e6; border-radius:9px; background:#fff; color:#4a4e69; font:inherit; cursor:pointer; }
.cancel-btn:hover { background:#f6f6fa; }
@keyframes dshake { 0%,100% { transform:translateX(0); } 20%,60% { transform:translateX(-8px); } 40%,80% { transform:translateX(8px); } }
.d-dlg.shake { animation:dshake .35s; }
/* askDialog — the same confirm dialog as the designer */
.ask-overlay { position:fixed; inset:0; z-index:950; background:rgba(20,18,45,.45); display:flex; align-items:center; justify-content:center; padding:1rem; animation:askFade .15s ease; }
.ask-overlay .dlg { width:min(400px,92vw); background:#fff; border-radius:14px; padding:1.2rem 1.3rem 1.1rem; box-shadow:0 20px 60px rgba(20,20,60,.3); animation:askPop .18s cubic-bezier(.3,1.4,.5,1); }
.ask-overlay .dlg-title { margin:0 0 .25rem; font-family:'DINBlack',sans-serif; font-weight:400; font-size:1.05rem; color:#2e2763; letter-spacing:.02em; }
.ask-overlay .ask-msg { margin:.35rem 0 .2rem; font-size:.86rem; line-height:1.5; color:#4a4e68; }
.ask-overlay .dlg-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1.1rem; }
.ask-overlay .attr-btn { height:2.05rem; padding:0 1rem; border:1.5px solid #d6d9e6; border-radius:7px; background:#fff; color:#3b3f5c; font-size:.75rem; cursor:pointer; white-space:nowrap; }
.ask-overlay .attr-btn:hover { border-color:#a99cff; }
.ask-overlay .print-go { height:2.05rem; border:none; border-radius:7px; background:#4b3fa8; color:#fff; font-family:'DINBlack',sans-serif; font-size:.72rem; letter-spacing:.08em; text-transform:uppercase; padding:0 1.2rem; cursor:pointer; }
.ask-overlay .print-go.danger { background:#c0392b; }
.ask-overlay .print-go.danger:hover { background:#a93226; }
.ask-overlay button:focus-visible { outline:2px solid #7b6cf6; outline-offset:2px; }
@keyframes askFade { from { opacity:0; } }
@keyframes askPop { from { transform:scale(.94); opacity:0; } }

@media (max-width:760px) {
  .d-side { display:none; }
  .d-main { padding:1.3rem 1rem 2.5rem; }
  .d-head { flex-wrap:wrap; } .d-head h1 { font-size:1.6rem; } .d-search { width:100%; }
  .d-name small { display:none; }
  .d-grid { grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:.9rem; }
  .d-start { grid-template-columns:1fr; }
}
/* New design: the same three steps as the other apps' Link to your sheet (css/gameboard-ui.css, here in CardBoard's colours) */
#createDlg { --gb-accent:#4b3fa8; --gb-accent-dk:#3b3190; --gb-accent-lt:#f3f1ff; --gb-black:'DINBlack',sans-serif; --gb-text:'DINRegular',sans-serif; }
#createDlg .gs-start { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:.7rem; margin:.2rem 0 1.3rem; }
#createDlg .gs-start a { display:flex; flex-direction:column; gap:.2rem; padding:.85rem .95rem; border:1.5px solid #d6d9e6; border-radius:11px; color:#2e2763; text-decoration:none; transition:border-color .12s, background .12s; }
#createDlg .gs-start a:hover { border-color:var(--gb-accent); background:var(--gb-accent-lt); }
#createDlg .gs-start b { font-family:var(--gb-black); font-weight:400; font-size:.92rem; }
#createDlg .gs-start span { font-size:.78rem; color:#8a8fa8; line-height:1.4; }
/* Link to your sheet: three numbered steps */
#createDlg .gs-intro { font-size:.86rem; color:#666; line-height:1.6; margin:0 0 1.1rem; }
#createDlg .gs-steps { list-style:none; counter-reset:gs; padding:0; margin:0 0 1.2rem; }
#createDlg .gs-steps > li { counter-increment:gs; position:relative; padding:0 0 1.1rem 2.3rem; font-size:.88rem; color:#555; line-height:1.55; }
#createDlg .gs-steps > li::before { content:counter(gs); position:absolute; left:0; top:-.05rem; width:1.55rem; height:1.55rem; border-radius:50%; background:var(--gb-accent); color:#fff; font-family:var(--gb-black); font-size:.8rem; display:flex; align-items:center; justify-content:center; }
#createDlg .gs-steps > li > b { display:block; font-family:var(--gb-black); font-weight:400; color:#222; font-size:.9rem; margin:0 0 .45rem; }
#createDlg .gs-steps > li:last-child { padding-bottom:0; }
#createDlg .gs-steps > li:last-child > b { margin:0; }
#createDlg .gs-acct { display:flex; flex-direction:column; align-items:flex-start; gap:.45rem; background:#f6f7fb; border:1px solid #e3e5ef; border-radius:9px; padding:.5rem .65rem; }
#createDlg .gs-acct code { font-size:.8rem; color:#333; overflow-wrap:anywhere; line-height:1.5; }
#createDlg .gs-steps .gs-start { margin:.1rem 0 .7rem; }
#createDlg .gs-more { margin:.6rem 0 0; border:1px solid #e3e5ef; border-radius:9px; background:#fff; }
#createDlg .gs-more summary { cursor:pointer; padding:.45rem .7rem; font-family:var(--gb-black); font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; line-height:1.2; color:var(--gb-accent-dk); list-style:none; display:flex; align-items:center; gap:.45rem; }
#createDlg .gs-more summary::-webkit-details-marker { display:none; }
#createDlg .gs-more summary svg { flex:none; width:15px; height:15px; display:block; margin-top:-1px; }
#createDlg .gs-more summary::after { content:'▾'; margin-left:auto; font-size:.75rem; transition:transform .15s; }
#createDlg .gs-more[open] summary::after { transform:rotate(180deg); }
#createDlg .gs-more p { margin:0; padding:0 .8rem .6rem; font-size:.8rem; line-height:1.5; color:#555; }
#createDlg .gs-more p b { color:#333; }
#createDlg .gs-how { font-size:.8rem; color:#888; margin:0 0 .2rem; }
#createDlg .gs-sub { padding-left:1.15rem; margin:0 0 .7rem; font-size:.84rem; }
#createDlg .gs-sub li { margin:0 0 .2rem; }
#createDlg .gs-sub strong { color:#333; }
#createDlg .gs-shot { margin:0; }
#createDlg .gs-shot img { display:block; width:100%; max-width:380px; border-radius:10px; border:1px solid #e3e5ef; box-shadow:0 2px 10px rgba(0,0,0,.08); }
#createDlg .gs-shot figcaption { font-size:.74rem; color:#999; margin:.35rem 0 0; }
/* Wide screens: the whole width, the three steps side by side */
@media (min-width:900px) {
  .d-dlg:has(.gs-wide) { max-width:min(100%, 1700px); }
  #createDlg .gs-wide { min-height:calc(90vh - 2rem); box-sizing:border-box; padding:2rem 2.4rem; }
  #createDlg .gs-wide .gs-steps { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.7fr) minmax(0,1fr); gap:2rem; align-items:start; }
  #createDlg .gs-wide .gs-steps > li { padding:1.1rem 1.2rem 1.3rem 1.2rem; border:1.5px solid #e6e8f0; border-radius:14px; background:#fcfcfe; }
  #createDlg .gs-wide .gs-steps > li::before { position:static; margin:0 0 .6rem; width:2rem; height:2rem; font-size:1rem; }
  #createDlg .gs-wide .gs-steps > li > b { font-size:1rem; margin:0 0 .7rem; }
  #createDlg .gs-wide .gs-steps > li:last-child > b { margin:0 0 .7rem; }
  #createDlg .gs-wide .gs-shot img { max-width:min(100%, 360px); }   /* the whole dialog fits on the screen */
  #createDlg .gs-wide .gs-steps .gs-btns { flex-wrap:wrap; }
  #createDlg .gs-wide .log-panel { margin-top:1rem; max-height:50vh; }   /* the progress, under step 3 */
}
@media (max-width:560px) { #createDlg .gs-start { grid-template-columns:1fr; } }
#createDlg .why { margin:0 0 1.1rem; }
</style>
</head>
<body>
<div class="page">

  <div class="brand">
    <img class="brand-logo" src="images/cardboard_icon_180.png" alt="CardBoard">
    <div class="brand-name">CardBoard</div>
    <div class="brand-tagline">Design and print cards from your Google Sheet</div>
  </div>

  <!-- First visit: start the free week or log in. Nothing else happens until then. -->
  <div class="card" id="authCard" hidden>
    <div id="authChoose">
      <div class="card-title" style="text-align:center">Welcome</div>
      <p class="auth-msg" style="text-align:center">Sign in to design your cards. New to CardBoard? Your first week is free, with every feature.</p>
      <div class="choose">
        <button type="button" class="opt primary" onclick="authMode('signup')"><b>Start your free week</b><span>Every feature for 7 days · no card needed</span></button>
        <button type="button" class="opt" onclick="authMode('login')"><b>Log in</b><span>I already have a GameBoard account</span></button>
      </div>
    </div>
    <form id="authEmail" hidden onsubmit="event.preventDefault();authSubmit()">
      <div class="card-title" id="authTitle">Log in</div>
      <p class="auth-msg" id="authMsg"></p>
      <div class="auth-col">
        <input class="sheet-input" id="authAddr" type="email" autocomplete="email" placeholder="Email" aria-label="Email">
        <input class="sheet-input" id="authPass" type="password" autocomplete="current-password" placeholder="Password" aria-label="Password">
        <input class="sheet-input" id="authPass2" type="password" autocomplete="new-password" placeholder="Password again" aria-label="Password again">
        <ul class="pw-rules" id="authRules" aria-live="polite">
          <li data-rule="len">At least 8 characters</li><li data-rule="letter">A letter</li><li data-rule="num">A number</li><li data-rule="match">Both passwords match</li>
        </ul>
        <button type="submit" class="connect-btn" id="authSendBtn">Log in</button>
      </div>
      <div class="auth-err" id="authErr1"></div>
      <div class="auth-foot"><button type="button" class="link-btn" id="authForgot" onclick="authMode('reset')">Forgot password?</button><span id="authForgotSep"> &nbsp;·&nbsp; </span><button type="button" class="link-btn" id="authSwitch" onclick="authMode(_mode === 'login' ? 'signup' : 'login')"></button> &nbsp;·&nbsp; <button type="button" class="link-btn" onclick="authMode('')">Back</button></div>
    </form>
    <form id="authCode" hidden onsubmit="event.preventDefault();authVerify()">
      <div class="card-title">Check your email</div>
      <p class="auth-msg" id="authSent"></p>
      <div class="auth-col"><input class="sheet-input code-input" id="authCodeIn" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" aria-label="Code" oninput="var v=this.value.replace(/\D/g,'').slice(0,6);if(v!==this.value)this.value=v">
        <button type="submit" class="connect-btn" id="authGo">Confirm</button></div>
      <div class="auth-err" id="authErr2"></div>
      <div class="auth-foot"><button type="button" class="link-btn" onclick="authMode(_mode)">Back</button></div>
    </form>
  </div>

</div>

<!-- Signed in: the Designs page -->
<div class="app" id="app">
  <header class="d-top">
    <a class="d-brand" href="cardboard" title="Your designs"><img src="images/cardboard_icon_180.png" alt=""><span class="d-name"><b><span class="c1">Card</span><span class="c2">Board</span></b><small>Design, print, and share your card deck — fast.</small></span></a>
    <div class="grow"></div>
    <button type="button" class="d-create" onclick="openCreateDialog()">+ Design</button>
    <div class="d-menu-wrap">
      <button type="button" class="top-btn" id="menuBtn" onclick="toggleMainMenu(event)" title="Menu"><img class="top-pic" id="menuPic" alt="" hidden><svg id="menuIco" width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="7.5" r="4.5"/><path d="M3.5 21c0-4.14 3.81-7.5 8.5-7.5s8.5 3.36 8.5 7.5"/></svg></button>
      <div class="main-menu" id="mainMenu">
        <div class="mm-who" id="mmWho"></div>
        <a class="mm-item" href="cardboard/help" target="_blank">Help</a>
        <a class="mm-item" href="cardboard/features">Features</a>
        <a class="mm-item" href="gameboard">GameBoard</a>
        <hr class="mm-div">
        <button type="button" class="mm-item" onclick="authSignOut()">Sign out</button>
      </div>
    </div>
  </header>
  <div class="d-body">
    <aside class="d-side">
      <div class="d-me"><label class="d-av d-pic" id="sideAv" title="Click or drop a picture to change it" ondragover="event.preventDefault();this.classList.add('drop')" ondragleave="this.classList.remove('drop')" ondrop="picDrop(event)"><span class="ini" id="sideIni"></span><img id="sideImg" alt="" hidden><span class="cam"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M4 8h3l2-2.5h6L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg></span><input type="file" accept="image/*" hidden onchange="picPick(this)"></label><div class="nm" id="meName"></div><div class="em" id="meEmail"></div><span class="pl" id="mePlan"></span></div>
      <nav class="d-nav">
        <a href="cardboard" class="on" id="navDesigns" onclick="return showView('designs')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>Designs</a>
        <a href="cardboard#templates" id="navTemplates" onclick="return showView('templates')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="3.5" y="5" width="10" height="14" rx="2"/><path d="M16.5 7.5l3 .8-3 11.5-4.5-1.2"/><path d="M6.5 9h4M6.5 12h4"/></svg>Templates</a>
        <a href="cardboard/help"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.3a2.6 2.6 0 0 1 5 .9c0 1.7-2.5 2.3-2.5 4"/><circle cx="12" cy="17.3" r=".6" fill="currentColor"/></svg>Help</a>
        <a href="cardboard/features"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M12 3l2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.4 6.7 19.4l1.2-6L3.4 9.3l6-.7z"/></svg>Features</a>
        <a href="gameboard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M3.5 10.5 12 4l8.5 6.5V20h-6v-5.5h-5V20h-6z"/></svg>GameBoard</a>
      </nav>
      <div class="foot"><button type="button" class="link-btn" onclick="authSignOut()">Sign out</button></div>
    </aside>
    <main class="d-main">
      <section id="tplView" hidden>
        <div class="d-head"><h1>Templates</h1></div>
        <p class="t-sub">Start from a finished layout. Click <b>Use this design</b>, then link a Google Sheet: a deck with the layout, its columns and a few sample cards is added to it.</p>
        <div id="tGrid"><div class="d-loading">Loading the templates…</div></div>
      </section>
      <section id="desView">
      <div class="d-head">
        <h1>Your designs</h1>
        <label class="d-search" id="dSearchWrap"><input type="search" id="dSearch" placeholder="Search" autocomplete="off" oninput="dRender()"><button type="button" class="x" onclick="dClearSearch()" title="Clear">×</button><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg></label>
      </div>
      <div class="d-bar">
        <button type="button" class="d-chip on" data-f="all" onclick="dFilter('all')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>All</button>
        <button type="button" class="d-chip" data-f="mine" onclick="dFilter('mine')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2.5"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>Yours <span class="n" id="nMine"></span></button>
        <button type="button" class="d-chip" data-f="seen" id="chipSeen" onclick="dFilter('seen')" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>Viewed <span class="n" id="nSeen"></span></button>
        <div class="grow"></div>
        <label class="d-sort"><select id="dSort" onchange="dRender()"><option value="edited">Edited</option><option value="name">Name</option></select></label>
      </div>
      <div id="dGrid"><div class="d-loading">Loading your designs…</div></div>
      </section>
    </main>
  </div>
</div>

<div class="d-ov" id="createOverlay" onclick="closeCreateDialog()">
  <div class="d-dlg" id="createDlg" onclick="event.stopPropagation()" onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();startSetup();}">
    <button type="button" class="x-btn" onclick="closeCreateDialog()" title="Close">×</button>
  <div class="card gs-wide" id="connectCard">
    <div class="tpl-note" id="tplNote" hidden><img id="tplNoteImg" alt="" hidden><span class="grow">Template: <b id="tplNoteName"></b> — a deck with its layout, columns and sample cards is added to the sheet you link.</span><button type="button" class="link-btn" onclick="dropTemplate()">Don’t use it</button></div>
    <div class="card-title">Link to your sheet</div>
    <p class="gs-intro">Keep your cards in a Google Sheet — one tab per deck, one row per card, one column per field (Name, Cost, Text, Image…). CardBoard turns each row into a card using a layout you design, then lays them out on print sheets.</p>
    <details class="why">
        <summary>Why a Google Sheet?</summary>
        <p><b>It’s where your cards live.</b> Your cards, your layouts and your tags are all saved in your sheet (CardBoard adds a <i>layouts</i> and a <i>tags</i> tab for its part) — CardBoard is the designer, the sheet is the home. That’s why it needs one to start.</p>
        <ul>
          <li><b>Yours to keep.</b> The sheet stays in your Google Drive. Stop using CardBoard any time and everything is still there.</li>
          <li><b>Edit cards the fast way.</b> Change numbers across a whole deck, sort, filter, use formulas or paste in a list — then Fetch, and every card follows.</li>
          <li><b>Work with others.</b> Share the sheet with co-designers or playtesters the usual Google way.</li>
          <li><b>Nothing gets lost.</b> Google keeps the sheet’s version history, so you can always go back.</li>
        </ul>
    </details>
    <ol class="gs-steps">
      <li>
        <b>Copy the CardBoard service account</b>
        <div class="gs-acct"><code>editor@<wbr>zapsheets-480701.iam.gserviceaccount.com</code><button type="button" class="copy-email-btn" id="copyEmailBtn" onclick="copyEmail()">Copy</button></div>
        <details class="gs-more">
          <summary><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="11" x2="12" y2="17"/><circle cx="12" cy="7.5" r=".6" fill="currentColor"/></svg>What is a service account?</summary>
          <p><b>Why share with this address?</b> It’s CardBoard’s own Google account — a service account, not a person. CardBoard reads your sheet and saves the changes you make in the app back to it through this account. It’s the same account for all the GameBoard apps.</p>
          <p><b>What it can see.</b> Only the sheets you share with it. It has no access to the rest of your Google Drive, your Gmail or any other files.</p>
          <p><b>Why Editor?</b> To add the tabs CardBoard needs and write your changes. It only changes the sheet when you do something in the app; you’ll see those changes in the sheet’s version history.</p>
          <p><b>Your data stays yours.</b> The sheet stays in your Google Drive. CardBoard keeps a copy of what it reads on its server so pages load quickly; it isn’t shared with anyone.</p>
          <p><b>Stop any time.</b> Remove the address in the sheet’s Share window and CardBoard can no longer open the sheet. Everything in it stays where it is.</p>
        </details>
      </li>
      <li>
        <b>Open (or make) a Google Spreadsheet, share it with CardBoard, and copy its URL</b>
        <div class="gs-start">
          <a href="https://docs.google.com/spreadsheets/d/1sy20Ig_fmfpTfnpkEUXibgErXj2XISiqr-Ag6Xe3uRc/copy" target="_blank" rel="noopener" id="sampleLink"><b>Copy the sample deck</b><span>Sea Creatures, with a finished layout</span></a>
          <a href="https://docs.google.com/spreadsheets/" target="_blank" rel="noopener"><b>Open a sheet</b><span>One of your Google Sheets</span></a>
          <a href="https://sheets.new" target="_blank" rel="noopener"><b>Start a blank sheet</b><span>One tab per deck, one row per card</span></a>
        </div>
        <div class="gs-how">In the sheet:</div>
        <ol class="gs-sub">
          <li>Choose <strong>File › Share › Share with others</strong> (or the <strong>Share</strong> button at the top right).</li>
          <li>Paste the service account into <strong>Add people, groups…</strong>, leave it as <strong>Editor</strong>, and click <strong>Send</strong>.</li>
          <li>Copy the sheet’s URL from your browser’s address bar (or click <strong>Copy link</strong> in the Share window).</li>
        </ol>
        <figure class="gs-shot"><img src="images/help/google-share.jpg" alt="Google Sheets’ Share window: the service account added as Editor, with Copy link and Send" loading="lazy"><figcaption>The Share window in Google Sheets — the service account added as <b>Editor</b>.</figcaption></figure>
      </li>
      <li><b>Paste the spreadsheet URL or ID</b>
        <input type="text" id="sheetInput" class="sheet-input"
          placeholder="https://docs.google.com/spreadsheets/d/… or sheet ID"
          onkeydown="if(event.key==='Enter')startSetup()"
          autocomplete="off" spellcheck="false" />
        <div class="d-btns">
          <button type="button" class="cancel-btn" onclick="forceCloseCreateDialog()">Cancel</button>
          <button id="connectBtn" class="connect-btn" onclick="startSetup()">Connect &amp; Open</button>
        </div>
        <div id="logPanel" class="log-panel" style="display:none"></div>
      </li>
    </ol>
  </div>
  </div>
</div>
<div class="d-toast" id="dToast"></div>


<script>
(function() {
  'use strict';

  var APP_BASE = (function() {
    var b = document.querySelector('base');
    return b ? b.getAttribute('href') : '/';
  })();

  window.copyEmail = function() {
    var email = 'editor@zapsheets-480701.iam.gserviceaccount.com';
    var btn   = document.getElementById('copyEmailBtn');
    navigator.clipboard.writeText(email).then(function() {
      btn.textContent = 'Copied!'; btn.classList.add('copied');
      setTimeout(function() { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
    }).catch(function() {
      var ta = document.createElement('textarea');
      ta.value = email; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); btn.textContent = 'Copied!'; btn.classList.add('copied');
        setTimeout(function() { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
      } catch(e) {}
      document.body.removeChild(ta);
    });
  };

  function extractSheetId(raw) {
    raw = (raw || '').trim();
    var m = raw.match(/\/spreadsheets\/d\/([A-Za-z0-9_\-]+)/);
    if (m) return m[1];
    if (/^[A-Za-z0-9_\-]{10,}$/.test(raw)) return raw;
    return null;
  }

  var _pending = [];
  function cancelPending() { _pending.forEach(clearTimeout); _pending = []; }

  function addLog(msg, cls) {
    var panel = document.getElementById('logPanel');
    var el    = document.createElement('span');
    el.className   = 'll ' + (cls || 'inf');
    el.textContent = msg;
    panel.appendChild(el);
    panel.scrollTop = panel.scrollHeight;
  }

  // ── Account (the same one the designer keeps in this browser) ──────────────
  var acct = null;
  try { acct = JSON.parse(localStorage.getItem('cb_account') || 'null'); } catch (e) {}
  window._mode = '';
  function $(id) { return document.getElementById(id); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function(c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function post(fields) {
    var fd = new FormData(); Object.keys(fields).forEach(function(k) { fd.append(k, fields[k]); });
    return fetch(APP_BASE + 'push/gameboardAccount.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); })
      .then(function(r) { if (r && r.waiting) location.href = APP_BASE + 'waiting';   // the Waiting Room: no sign-in until let in
        if (!r || r.error) throw new Error(r && r.error ? r.error : 'No response'); return r; });
  }
  // Let in from the Waiting Room to other apps only: CardBoard can't be used (gb-uninvited.js covers the page)
  function cbUninvited(a) {
    var x = a && a.apps && a.apps.cardboard;
    if (!x || x.state !== 'uninvited') { if (window.GBUninvited) GBUninvited.hide(); return; }
    var N = { pitchboard: 'PitchBoard', devboard: 'DevBoard', billboard: 'BillBoard' };
    var have = Object.keys(N).filter(function(k) { return /^(trial|code|subscribed|activated)$/.test(((a.apps || {})[k] || {}).state || ''); }).map(function(k) { return N[k]; });
    var go = function() { GBUninvited.show({ name: 'CardBoard', icon: APP_BASE + 'images/cardboard_icon_180.png', home: APP_BASE + 'dashboard', apps: have }); };
    if (window.GBUninvited) return go();
    var s = document.createElement('script'); s.src = APP_BASE + 'js/gb-uninvited.js?v=1'; s.onload = go; document.head.appendChild(s);
  }
  function show() {
    document.body.classList.toggle('signed-out', !acct);
    cbUninvited(acct && acct.account);
    document.body.classList.toggle('designs', !!acct);
    $('authCard').hidden = !!acct;
    if (acct) { fillMe(); dLoad(); }
    else authMode('');
  }

  // ── The Designs page ───────────────────────────────────────────────────────
  var DES = null, _filter = 'all';
  try { DES = JSON.parse(localStorage.getItem('cb_designs') || 'null'); } catch (e) {}
  function fillMe() {
    var a = acct.account || {}, em = a.email || '', nm = (a.name || '').trim() || em.split('@')[0];
    var ini = (nm.match(/[A-Za-z0-9]/) || ['?'])[0].toUpperCase();
    $('sideIni').textContent = ini; $('sideImg').hidden = !a.image; if (a.image && $('sideImg').getAttribute('src') !== a.image) $('sideImg').src = a.image; $('sideIni').hidden = !!a.image; $('menuPic').hidden = !a.image; $('menuIco').style.display = a.image ? 'none' : ''; if (a.image && $('menuPic').getAttribute('src') !== a.image) $('menuPic').src = a.image; $('mmWho').innerHTML = '<b>' + esc(nm) + '</b>' + esc(em);
    $('meName').textContent = nm; $('meEmail').textContent = em;
    var left = Math.max(0, Math.ceil((new Date(a.until).getTime() - Date.now()) / 864e5));
    $('mePlan').textContent = a.state === 'trial' ? 'Free week · ' + left + (left === 1 ? ' day' : ' days') + ' left'
      : a.state === 'subscribed' ? 'Subscribed' : a.state === 'activated' ? 'Unlocked' : a.state === 'code' ? 'Unlocked with a code' : a.state === 'locked' ? 'Free week ended' : '';
    $('mePlan').hidden = !$('mePlan').textContent;
  }
  // Profile picture: click or drop one on the circle. Made smaller here first; the server squares it.
  function shrink(file) {
    return new Promise(function(ok) {
      var url = URL.createObjectURL(file), im = new Image();
      im.onload = function() {
        var k = Math.min(1, 1024 / Math.max(im.width, im.height)), c = document.createElement('canvas');
        c.width = Math.round(im.width * k); c.height = Math.round(im.height * k);
        c.getContext('2d').drawImage(im, 0, 0, c.width, c.height); URL.revokeObjectURL(url);
        c.toBlob(function(b) { ok(b || file); }, 'image/jpeg', .9);
      };
      im.onerror = function() { URL.revokeObjectURL(url); ok(file); };
      im.src = url;
    });
  }
  function picUpload(file) {
    if (!file || !/^image\//.test(file.type)) { dToast('Drop a picture (JPEG, PNG, GIF or WebP)'); return; }
    var el = $('sideAv'); el.classList.add('busy');
    shrink(file).then(function(b) {
      var fd = new FormData(); fd.append('action', 'image'); fd.append('cbt', acct.token); fd.append('file', b, 'picture.jpg');
      return fetch(APP_BASE + 'push/gameboardAccount.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); });
    }).then(function(r) {
      if (!r || r.error) throw new Error(r && r.error ? r.error : 'No response');
      storeAcct(r); fillMe(); dToast('✓ Picture updated');
    }).catch(function(e) { dToast('Couldn’t change your picture — ' + e.message); })
      .then(function() { el.classList.remove('busy'); });
  }
  window.picPick = function(inp) { var f = inp.files && inp.files[0]; inp.value = ''; picUpload(f); };
  window.picDrop = function(e) { e.preventDefault(); $('sideAv').classList.remove('drop'); picUpload(e.dataTransfer.files && e.dataTransfer.files[0]); };

  function seenIds() { try { var l = JSON.parse(localStorage.getItem('cb_seen') || '[]'); return Array.isArray(l) ? l : []; } catch (e) { return []; } }
  function storeAcct(r) { acct = { token: r.token, account: r.account, sheet: '', checked: Date.now() }; try { localStorage.setItem('cb_account', JSON.stringify(acct)); } catch (e) {} cbUninvited(acct.account); }
  function listPost() {
    var fd = new FormData(); fd.append('cbt', acct.token); fd.append('seen', seenIds().map(function(x) { return x.id; }).join(','));
    return fetch(APP_BASE + 'push/cardboardSheets.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); });
  }
  function dLoad() {
    if (DES) dRender();
    // a fresh sign-in first: it carries the sheets set up since (on any device)
    post({ action: 'refresh', cbt: acct.token }).then(function(r) { storeAcct(r); fillMe(); }, function(e) { if (/sign in/i.test(e.message)) { authSignOut(); throw e; } })
      .then(listPost)
      .then(function(r) {
        if (!r || r.error) throw new Error(r && r.error ? r.error : 'No response');
        DES = r.sheets || []; try { localStorage.setItem('cb_designs', JSON.stringify(DES)); } catch (e) {}
        dRender();
      })
      .catch(function(e) { if (!DES) $('dGrid').innerHTML = '<div class="d-empty"><b>Couldn’t load your designs</b>' + esc(e.message || '') + '</div>'; });
  }
  function ago(t) {
    if (!t) return '';
    var s = Math.max(0, Date.now() / 1000 - t), m = s / 60, h = m / 60, d = h / 24;
    if (s < 60) return 'just now';
    if (m < 60) return Math.round(m) === 1 ? 'a minute ago' : Math.round(m) + ' minutes ago';
    if (h < 24) return Math.round(h) === 1 ? 'an hour ago' : Math.round(h) + ' hours ago';
    if (d < 30) return Math.round(d) === 1 ? 'a day ago' : Math.round(d) + ' days ago';
    if (d < 365) return Math.round(d / 30) <= 1 ? 'a month ago' : Math.round(d / 30) + ' months ago';
    return Math.round(d / 365) <= 1 ? 'a year ago' : Math.round(d / 365) + ' years ago';
  }
  var DECK_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="7" y="3" width="12" height="16" rx="2"/><path d="M4.5 6.5v12A2.5 2.5 0 0 0 7 21h9"/></svg>';
  var CARD_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2.5"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>';
  window.dFilter = function(f) {
    _filter = f;
    document.querySelectorAll('.d-chip').forEach(function(c) { c.classList.toggle('on', c.dataset.f === f); });
    dRender();
  };
  window.dClearSearch = function() { $('dSearch').value = ''; dRender(); $('dSearch').focus(); };
  window.dRender = function() {
    var list = DES || [], q = $('dSearch').value.trim().toLowerCase(), sort = $('dSort').value;
    $('dSearchWrap').classList.toggle('has', !!q);
    var mine = list.filter(function(x) { return x.mine; }).length, seen = list.length - mine;
    $('nMine').textContent = mine || ''; $('nSeen').textContent = seen || ''; $('chipSeen').hidden = !seen;
    if (!seen && _filter === 'seen') _filter = 'all';
    var seenAt = {}; seenIds().forEach(function(x) { seenAt[x.id] = Math.round((x.t || 0) / 1000); });
    var show = list.filter(function(x) {
      if (_filter === 'mine' && !x.mine) return false;
      if (_filter === 'seen' && x.mine) return false;
      if (!q) return true;
      return (x.title || '').toLowerCase().indexOf(q) >= 0 || (x.decks || []).some(function(d) { return d.toLowerCase().indexOf(q) >= 0; });
    }).map(function(x) { return Object.assign({}, x, { when: Math.max(x.edited || 0, x.mine ? 0 : seenAt[x.id] || 0) }); });
    show.sort(sort === 'name' ? function(a, b) { return (a.title || 'zzz').localeCompare(b.title || 'zzz', undefined, { sensitivity: 'base' }); } : function(a, b) { return b.when - a.when; });
    if (!list.length) {
      $('dGrid').innerHTML = '<div class="d-empty"><b>No designs yet</b>Connect a Google Sheet, or start from a template, to design your first cards.<br><button type="button" class="connect-btn" onclick="openCreateDialog()">+ Design</button> <button type="button" class="cancel-btn" style="margin-top:1.2rem" onclick="showView(\'templates\')">Browse templates</button></div>';
      return;
    }
    if (!show.length) { $('dGrid').innerHTML = '<div class="d-empty"><b>Nothing matches</b>' + (q ? 'No design or deck called “' + esc(q) + '”.' : '') + '</div>'; return; }
    $('dGrid').innerHTML = '<div class="d-grid">' + show.map(function(x) {
      var n = (x.decks || []).length, title = x.title || 'Untitled sheet';
      return '<a class="d-card" href="' + esc(x.id) + '/cardboard" data-id="' + esc(x.id) + '">' +
        '<div class="d-thumb">' + (x.thumb ? '<img src="' + esc(x.thumb) + '" alt="" loading="lazy" onerror="this.outerHTML=\'<div class=ph>' + CARD_SVG.replace(/"/g, '&quot;') + '</div>\'">' : '<div class="ph">' + CARD_SVG + '</div>') + '</div>' +
        '<div class="d-info"><div class="t" title="' + esc(title) + '">' + esc(title) + '</div>' +
        '<div class="m"><span>' + (x.when ? (x.mine ? 'Edited ' : 'Viewed ') + ago(x.when) : '&nbsp;') + '<br><span class="d-tag' + (x.mine ? '' : ' view') + '">' + (x.mine ? 'Yours' : 'View only') + '</span></span>' +
        '<span class="r" title="' + esc((x.decks || []).join(', ')) + '">' + DECK_SVG + n + (n === 1 ? ' deck' : ' decks') + '</span></div>' +
        '<button type="button" class="d-more" title="More" onclick="dMenu(event, this)">⋯</button></div></a>';
    }).join('') + '</div>';
  };
  window.toggleMainMenu = function(e) { e.stopPropagation(); $('mainMenu').classList.toggle('open'); };
  document.addEventListener('click', function(e) { if (!e.target.closest('.d-menu-wrap')) $('mainMenu').classList.remove('open'); });
  function closeMenu() { var m = document.querySelector('.d-menu'); if (m) m.remove(); }
  window.dMenu = function(ev, btn) {
    ev.preventDefault(); ev.stopPropagation();
    var card = btn.closest('.d-card'), had = card.querySelector('.d-menu'); closeMenu(); if (had) return;
    var id = card.dataset.id, x = (DES || []).filter(function(d) { return d.id === id; })[0] || {};
    var m = document.createElement('div'); m.className = 'd-menu';
    m.innerHTML = '<button type="button" data-a="open">Open</button><button type="button" data-a="tab">Open in a new tab</button>' +
      '<button type="button" data-a="sheet">Open the Google Sheet</button><button type="button" data-a="copy">Copy link</button>' +
      (x.mine ? '<button type="button" data-a="delete" class="danger">Delete</button>' : '<button type="button" data-a="forget">Remove from this list</button>');
    m.addEventListener('click', function(e) {
      e.preventDefault(); e.stopPropagation();
      var a = e.target.dataset.a, url = location.origin + APP_BASE + id + '/cardboard'; closeMenu();
      if (a === 'open') location.href = url;
      if (a === 'tab') window.open(url, '_blank');
      if (a === 'sheet') window.open('https://docs.google.com/spreadsheets/d/' + id + '/edit', '_blank');
      if (a === 'copy') (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function() { dToast('✓ Link copied'); }, function() { prompt('Copy this link', url); });
      if (a === 'delete') {
        askDialog({ title: 'Delete ' + (x.title || 'this design') + '?', message: 'It leaves this list and its share link stops working. Your Google Sheet and everything in it stay as they are.', ok: 'Delete', cancel: 'Keep', danger: true }).then(function(yes) {
          if (!yes) return;
          var fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id); fd.append('cbt', acct.token);
          fetch('push/cardboardSheets.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); }).then(function(res) {
            if (!res.ok) { dToast(res.error || 'Couldn’t delete'); return; }
            try { localStorage.setItem('cb_seen', JSON.stringify(seenIds().filter(function(s) { return s.id !== id; }))); } catch (er) {}
            DES = (DES || []).filter(function(d) { return d.id !== id; }); try { localStorage.setItem('cb_designs', JSON.stringify(DES)); } catch (er) {}
            dRender(); dToast('✓ Deleted');
          }).catch(function() { dToast('Couldn’t delete'); });
        });
      }
      if (a === 'forget') {
        try { localStorage.setItem('cb_seen', JSON.stringify(seenIds().filter(function(s) { return s.id !== id; }))); } catch (er) {}
        DES = (DES || []).filter(function(d) { return d.id !== id; }); try { localStorage.setItem('cb_designs', JSON.stringify(DES)); } catch (er) {}
        dRender();
      }
    });
    btn.parentNode.appendChild(m);
  };
  document.addEventListener('click', function(e) { if (!e.target.closest('.d-menu') && !e.target.closest('.d-more')) closeMenu(); });
  // askDialog({title, message, ok, cancel, danger}) → Promise<boolean> — the designer's confirm dialog
  function askDialog(o) {
    return new Promise(function(done) {
      var ov = document.createElement('div');
      ov.className = 'ask-overlay';
      ov.innerHTML = '<div class="dlg" role="alertdialog" aria-modal="true" aria-labelledby="askTitle">' +
        '<h2 class="dlg-title" id="askTitle">' + esc(o.title || '') + '</h2>' +
        '<p class="ask-msg">' + esc(o.message || '') + '</p>' +
        '<div class="dlg-actions">' + (o.cancel === '' ? '' : '<button type="button" class="attr-btn" data-a="0">' + esc(o.cancel || 'Cancel') + '</button>') +
        '<button type="button" class="print-go' + (o.danger ? ' danger' : '') + '" data-a="1">' + esc(o.ok || 'OK') + '</button></div></div>';
      var prev = document.activeElement;
      function close(v) { document.removeEventListener('keydown', key, true); ov.remove(); if (prev && prev.focus) prev.focus(); done(v); }
      function key(ev) {
        if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); close(false); }
        else if (ev.key === 'Enter') { ev.preventDefault(); ev.stopPropagation(); var f = document.activeElement; close(f && f.dataset && f.dataset.a ? f.dataset.a === '1' : !o.danger); }
        else if (ev.key === 'Tab') {
          var b = ov.querySelectorAll('button'), i = Array.prototype.indexOf.call(b, document.activeElement);
          ev.preventDefault(); b[(i + (ev.shiftKey ? b.length - 1 : 1)) % b.length].focus();
        }
      }
      ov.addEventListener('click', function(ev) {
        var b = ev.target.closest('[data-a]');
        if (b) close(b.dataset.a === '1'); else if (ev.target === ov) close(false);
      });
      document.addEventListener('keydown', key, true);
      document.body.appendChild(ov);
      ov.querySelector(o.danger ? '[data-a="0"]' : '[data-a="1"]').focus();   // risky actions default to Cancel
    });
  }
  function dToast(t) { var el = $('dToast'); el.textContent = t; el.classList.add('show'); clearTimeout(el._h); el._h = setTimeout(function() { el.classList.remove('show'); }, 1800); }

  // ── Templates: the decks of the Sea Creatures sample sheet that have a layout ──
  var TPLS = null, _tplPick = '', _useTpl = null, TPL_SHEET = '1sy20Ig_fmfpTfnpkEUXibgErXj2XISiqr-Ag6Xe3uRc';
  window.showView = function(v) {
    var t = v === 'templates';
    $('tplView').hidden = !t; $('desView').hidden = t;
    $('navTemplates').classList.toggle('on', t); $('navDesigns').classList.toggle('on', !t);
    try { history.replaceState(null, '', t ? '#templates' : location.pathname + location.search); } catch (e) {}
    if (t) tLoad();
    return false;
  };
  function tLoad() {
    if (TPLS) { tRender(); return; }
    var fd = new FormData(); fd.append('id', TPL_SHEET); fd.append('action', 'templates');
    fetch(APP_BASE + 'push/cardboardData.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); }).then(function(r) {
      if (!r || r.error) throw new Error(r && r.error ? r.error : 'No response');
      TPLS = r.templates || []; tRender();
    }).catch(function(e) { $('tGrid').innerHTML = '<div class="d-empty"><b>Couldn’t load the templates</b>' + esc(e.message || '') + '</div>'; });
  }
  function tRender() {
    if (!TPLS.length) { $('tGrid').innerHTML = '<div class="d-empty"><b>No templates yet</b></div>'; return; }
    $('tGrid').innerHTML = '<div class="d-grid">' + TPLS.map(function(t, i) {
      var ar = (t.w || 180) + ' / ' + (t.h || 252);
      return '<button type="button" class="t-card" data-i="' + i + '" onclick="useTemplate(' + i + ')">' +
        '<div class="d-thumb">' + (t.thumb ? '<img src="' + esc(APP_BASE + t.thumb) + '" alt="" loading="lazy">' : '<div class="ph-t" style="--ar:' + ar + '">' + esc(t.name) + '</div>') + '</div>' +
        '<div class="d-info"><div class="t">' + esc(t.name) + '</div><div class="m">' + esc(t.desc || ((t.w > t.h ? 'Landscape' : 'Portrait') + ' card')) + '</div>' +
        '<span class="t-go" role="button" onclick="event.stopPropagation();useTemplate(' + i + ')">Use this design</span></div></button>';
    }).join('') + '</div>';
  }
  window.pickTemplate = function(i) { var t = TPLS[i]; if (!t) return; _tplPick = _tplPick === t.name ? '' : t.name; tRender(); };
  window.useTemplate = function(i) {
    if (typeof i === 'number' && TPLS[i]) _tplPick = TPLS[i].name;
    var t = (TPLS || []).filter(function(x) { return x.name === _tplPick; })[0]; if (!t) return;
    _useTpl = t; openCreateDialog(true);
  };
  window.dropTemplate = function() { _useTpl = null; showTplNote(); };
  function showTplNote() {
    var t = _useTpl;
    $('tplNote').hidden = !t; $('sampleLink').hidden = !!t;   // a template goes into a new, blank sheet
    if (!t) return;
    $('tplNoteName').textContent = t.name;
    $('tplNoteImg').hidden = !t.thumb; if (t.thumb) $('tplNoteImg').src = APP_BASE + t.thumb;
  }
  if (location.hash === '#templates') setTimeout(function() { showView('templates'); }, 0);

  // + Create (the dialog pattern: dirty-check on ×/Esc/backdrop, Cancel always closes, ⌘Enter connects)
  var _createInitial = null;
  window.openCreateDialog = function(fromTpl) {
    if (!fromTpl) _useTpl = null;
    showTplNote();
    $('sheetInput').value = ''; $('logPanel').innerHTML = ''; $('logPanel').style.display = 'none';
    $('connectBtn').disabled = false; $('connectBtn').textContent = 'Connect & Open';
    _createInitial = { url: '' };
    $('createOverlay').classList.add('open');
    setTimeout(function() { $('sheetInput').focus(); }, 30);
  };
  window.forceCloseCreateDialog = function() { $('createOverlay').classList.remove('open'); };
  window.closeCreateDialog = function() {
    if (_createInitial && $('sheetInput').value.trim() !== _createInitial.url && !$('connectBtn').disabled) {
      var d = $('createDlg'); d.classList.remove('shake'); void d.offsetWidth; d.classList.add('shake'); return;
    }
    forceCloseCreateDialog();
  };
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { if (document.querySelector('.d-menu')) { closeMenu(); return; } if ($('createOverlay').classList.contains('open')) closeCreateDialog(); }
  });

  // '' = the two choices; login = email + password; signup / reset = email + password, then a code from the email
  var pending = null;
  window.authMode = function(m) {
    window._mode = m; pending = null;
    $('authChoose').hidden = !!m; $('authEmail').hidden = !m; $('authCode').hidden = true;
    $('authErr1').textContent = ''; $('authErr2').textContent = '';
    $('authTitle').textContent = { login: 'Log in', signup: 'Start your free week', reset: 'Reset your password' }[m] || '';
    $('authMsg').textContent = { login: 'Use your GameBoard email and password.',
      signup: 'Every feature for 7 days — no card needed. Choose a password; we’ll email you a code to confirm.',
      reset: 'Choose a new password; we’ll email you a code to confirm it’s you.' }[m] || '';
    $('authPass').value = ''; $('authPass2').value = '';
    $('authPass').placeholder = m === 'login' ? 'Password' : 'Password (8 or more characters)';
    $('authPass').setAttribute('autocomplete', m === 'login' ? 'current-password' : 'new-password');
    $('authPass2').hidden = $('authRules').hidden = m === 'login'; checkRules();
    $('authForgot').hidden = $('authForgotSep').hidden = m !== 'login';
    $('authSendBtn').textContent = { login: 'Log in', signup: 'Create account', reset: 'Email me a code' }[m] || '';
    $('authSwitch').textContent = m === 'login' ? 'New here? Start your free week' : 'Already have an account? Log in';
    if (m) setTimeout(function() { ($('authAddr').value ? $('authPass') : $('authAddr')).focus(); }, 30);
  };
  function signedIn(r) {
    acct = { token: r.token, account: r.account, sheet: '', checked: Date.now() };
    try { localStorage.setItem('cb_account', JSON.stringify(acct)); } catch (e) {}
    show();
  }
  window.authSubmit = function() {
    var m = window._mode, em = $('authAddr').value.trim(), pw = $('authPass').value, err = $('authErr1');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { err.textContent = 'Enter your email address.'; return; }
    if (m === 'login') { if (!pw) { err.textContent = 'Enter your password.'; return; } }
    else if (!checkRules()) { err.textContent = 'Your password needs everything on the list.'; return; }
    var b = $('authSendBtn'), label = b.textContent; b.disabled = true; b.textContent = m === 'login' ? 'Logging in…' : 'Sending…'; err.textContent = '';
    var done = function() { b.disabled = false; b.textContent = label; };
    if (m === 'login') {
      post({ action: 'login', email: em, password: pw }).then(signedIn).catch(function(e) { err.textContent = e.message; }).then(done);
      return;
    }
    post({ action: 'send', email: em, mode: m }).then(function(r) {
      pending = { email: em, password: pw };
      $('authEmail').hidden = true; $('authCode').hidden = false; $('authCodeIn').value = r.debug_code || '';
      $('authSent').innerHTML = 'We sent a 6-digit code to <b>' + esc(em) + '</b>. Enter it to ' + (m === 'reset' ? 'set your new password.' : 'confirm your email and start your free week.') + ' It works for 15 minutes.';
      $('authGo').textContent = m === 'reset' ? 'Set password' : 'Start my free week';
      setTimeout(function() { $('authCodeIn').focus(); }, 30);
    }).catch(function(e) { err.textContent = e.message; }).then(done);
  };
  // New password: tick off each rule as it's met (the server checks the same rules)
  function checkRules() {
    var pw = $('authPass').value, all = true, ok = {
      len: pw.length >= 8, letter: /\p{L}/u.test(pw), num: /\d/.test(pw), match: pw !== '' && pw === $('authPass2').value };
    $('authRules').querySelectorAll('li').forEach(function(li) { var y = ok[li.dataset.rule]; li.classList.toggle('ok', y); if (!y) all = false; });
    return all;
  }
  function onPw() { if (checkRules()) $('authErr1').textContent = ''; }
  $('authPass').addEventListener('input', onPw); $('authPass2').addEventListener('input', onPw);
  window.authVerify = function() {
    var code = $('authCodeIn').value.replace(/\D/g, '');
    if (!pending) { authMode(window._mode); return; }
    if (code.length !== 6) { $('authErr2').textContent = 'Enter the 6-digit code from the email.'; return; }
    var b = $('authGo'), label = b.textContent; b.disabled = true; b.textContent = 'Checking…'; $('authErr2').textContent = '';
    post({ action: 'verify', email: pending.email, password: pending.password, code: code }).then(function(r) { pending = null; signedIn(r); })
      .catch(function(e) { $('authErr2').textContent = e.message; }).then(function() { b.disabled = false; b.textContent = label; });
  };
  window.authSignOut = function() { acct = null; DES = null; try { localStorage.removeItem('cb_account'); localStorage.removeItem('cb_designs'); } catch (e) {} show(); };
  show();
  // Coming from the GameBoard home on mygameboard.app: the account comes along in the link (#gbt=…)
  (function() {
    var m = /[#&]gbt=([^&]+)/.exec(location.hash || ''); if (!m) return;
    history.replaceState(null, '', location.pathname + location.search);
    post({ action: 'refresh', cbt: decodeURIComponent(m[1]) }).then(signedIn).catch(function() {});
  })();

  window.startSetup = function() {
    if (!acct) { show(); return; }
    var inp     = document.getElementById('sheetInput');
    var btn     = document.getElementById('connectBtn');
    var panel   = document.getElementById('logPanel');
    var sheetId = extractSheetId(inp.value);

    if (!sheetId) {
      inp.classList.remove('shake');
      void inp.offsetWidth;
      inp.classList.add('shake');
      inp.focus();
      setTimeout(function() { inp.classList.remove('shake'); }, 400);
      return;
    }

    btn.disabled    = true;
    btn.textContent = 'Connecting…';
    panel.innerHTML = '';
    panel.style.display = '';

    addLog('Connecting to Google Sheet…', 'inf');
    _pending.push(setTimeout(function() { addLog('Reading the decks…', 'inf'); }, 700));

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/cardboardData.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function() {
      cancelPending();
      var result;
      try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }

      if (result && result.need_auth) {   // the saved sign-in is too old: sign in again
        if (result.refresh && acct) {
          post({ action: 'refresh', cbt: acct.token }).then(function(r) {
            acct = { token: r.token, account: r.account, sheet: '', checked: Date.now() };
            try { localStorage.setItem('cb_account', JSON.stringify(acct)); } catch (e) {}
            btn.disabled = false; btn.textContent = 'Connect & Open'; startSetup();
          }).catch(function() { authSignOut(); });
        } else authSignOut();
        return;
      }
      if (!result || result.error) {
        var errMsg = (result && result.error) || xhr.responseText || 'Unknown error';
        var isPermErr = errMsg.indexOf('403') !== -1
                     || errMsg.toLowerCase().indexOf('permission') !== -1
                     || errMsg.toLowerCase().indexOf('could not open') !== -1;
        if (isPermErr) {
          addLog('✕  Sheet not shared with the CardBoard service account.', 'err');
          addLog('   Add editor@zapsheets-480701.iam.gserviceaccount.com (Editor) and try again.', 'err');
        } else {
          addLog('✕  ' + errMsg, 'err');
        }
        btn.disabled    = false;
        btn.textContent = 'Connect & Open';
        return;
      }

      if (result.title) addLog('✓  Sheet: ' + result.title, 'ok');
      if (result.title) try { var _n = JSON.parse(localStorage.getItem('gb_sheet_names') || '{}'); _n[sheetId] = result.title; localStorage.setItem('gb_sheet_names', JSON.stringify(_n)); } catch (e) {}   // the GameBoard dashboard lists it by name
      var tabs = result.tabs || [];
      addLog('✓  ' + tabs.length + (tabs.length === 1 ? ' deck: ' : ' decks: ') + tabs.map(function(t) { return t.title; }).join(', '), 'ok');
      // Connecting sets the sheet up as yours (unless someone else already has): you design it, others view it
      post({ action: 'claim', cbt: acct.token, sheet: sheetId }).then(function(r) {
        acct = { token: r.token, account: r.account, sheet: sheetId, checked: Date.now() };
        try { localStorage.setItem('cb_account', JSON.stringify(acct)); } catch (e) {}
        addLog(r.account && r.account.owner ? '✓  Set up as yours' : '✓  Someone else set this sheet up — it opens view only', 'ok');
      }).catch(function(e) { addLog('!  ' + e.message, 'err'); }).then(function() {
        if (!_useTpl) return;
        var t = _useTpl; addLog('Adding the ' + t.name + ' template…', 'inf');
        var fd = new FormData(); fd.append('id', sheetId); fd.append('action', 'fromtemplate'); fd.append('tab', t.name); fd.append('cbt', acct.token);
        return fetch(APP_BASE + 'push/cardboardData.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); }).then(function(r) {
          if (!r || r.error) throw new Error(r && r.error ? r.error : 'No response');
          try { localStorage.setItem('cb_tab:' + sheetId, r.tab || t.name); } catch (e) {}
          addLog('✓  Added the deck “' + (r.tab || t.name) + '” with its layout and sample cards', 'ok');
        }).catch(function(e) { addLog('!  Couldn’t add the template — ' + e.message, 'err'); });
      }).then(function() {
        addLog('✓  Ready — opening CardBoard…', 'ok');
        setTimeout(function() { window.location.href = APP_BASE + sheetId + '/cardboard'; }, 800);
      });
    };

    xhr.onerror = function() {
      cancelPending();
      addLog('✕  Network error — could not reach the server.', 'err');
      btn.disabled    = false;
      btn.textContent = 'Connect & Open';
    };

    xhr.send('action=tabs&id=' + encodeURIComponent(sheetId) + '&cbt=' + encodeURIComponent(acct ? acct.token : ''));
  };
})();
</script>
</body>
</html>
