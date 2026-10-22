<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/gameboard/demo/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ironwood Press — GameBoard Demo</title>
<link rel="icon" type="image/png" href="images/gb_logo.png" />
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:        #111827;
  --surface:   #1a2035;
  --surface2:  #1f2847;
  --surface3:  #243058;
  --border:    rgba(255,255,255,.08);
  --text:      #f1f5f9;
  --muted:     rgba(255,255,255,.45);
  --muted2:    rgba(255,255,255,.25);
  --green:     #4caa4b;
  --green-b:   #3d8f3c;
  --green-t:   rgba(76,170,75,.18);
  --amber:     #e8a030;
  --amber-t:   rgba(232,160,48,.15);
  --radius:    12px;
}
html { scroll-behavior: smooth; }
body { background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; min-height: 100vh; }

/* ── BANNER ── */
.banner { background: var(--surface); border-bottom: 1px solid var(--border); }
.banner-inner { max-width: 1100px; margin: 0 auto; padding: 0 1.5rem; display: flex; align-items: center; gap: 14px; height: 64px; }
.banner-logo { width: 36px; height: 36px; border-radius: 9px; object-fit: contain; flex-shrink: 0; }
.banner-brand { flex: 1; min-width: 0; }
.banner-name { font-size: 1rem; font-weight: 700; color: var(--text); line-height: 1.2; letter-spacing: -.01em; }
.banner-tag  { font-size: 11px; color: var(--muted); margin-top: 1px; }
.banner-nav  { display: flex; gap: 8px; }
.banner-btn  { font-size: 12px; font-weight: 500; padding: 7px 14px; border-radius: 7px; border: 1px solid var(--border); color: var(--muted); background: transparent; cursor: pointer; transition: all .15s; text-decoration: none; }
.banner-btn:hover { color: var(--text); border-color: rgba(255,255,255,.18); }
.banner-btn.primary { background: var(--green); border-color: var(--green); color: #fff; }
.banner-btn.primary:hover { background: var(--green-b); }

/* ── CAROUSEL ── */
.carousel { position: relative; height: 220px; overflow: hidden; background: linear-gradient(135deg, #0d1829 0%, #1a2744 50%, #0f1e3a 100%); }
.carousel-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(17,24,39,.95) 0%, rgba(17,24,39,.3) 60%, transparent 100%); }
.carousel-content { position: absolute; bottom: 1.5rem; left: 50%; transform: translateX(-50%); text-align: center; white-space: nowrap; }
.carousel-co { font-size: 1.5rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--text); text-shadow: 0 2px 20px rgba(0,0,0,.5); }
.carousel-sub { font-size: 11px; color: var(--muted); letter-spacing: .06em; margin-top: 3px; }
.carousel-dots { position: absolute; bottom: .6rem; left: 50%; transform: translateX(-50%); display: flex; gap: 5px; }
.carousel-dots span { width: 6px; height: 6px; border-radius: 50%; background: var(--muted2); }
.carousel-dots span:first-child { background: var(--amber); width: 18px; border-radius: 3px; }
/* decorative shapes */
.carousel::before { content: ''; position: absolute; top: -60px; right: 8%; width: 300px; height: 300px; border-radius: 50%; background: radial-gradient(circle, rgba(232,160,48,.08) 0%, transparent 70%); }
.carousel::after  { content: ''; position: absolute; bottom: -40px; left: 5%; width: 200px; height: 200px; border-radius: 50%; background: radial-gradient(circle, rgba(76,170,75,.07) 0%, transparent 70%); }

/* ── DEMO BANNER ── */
.demo-notice { background: var(--amber-t); border-bottom: 1px solid rgba(232,160,48,.2); padding: 10px 1.5rem; text-align: center; font-size: 12.5px; color: var(--amber); display: flex; align-items: center; justify-content: center; gap: 8px; }
.demo-notice a { color: var(--amber); font-weight: 600; text-decoration: none; border-bottom: 1px solid rgba(232,160,48,.4); }

/* ── CATALOG ── */
.catalog { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
.catalog-header { display: flex; align-items: baseline; gap: .75rem; margin-bottom: 1.5rem; }
.catalog-title { font-size: 1.05rem; font-weight: 700; color: var(--text); }
.catalog-count { font-size: 12px; color: var(--muted); }
.game-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }

/* ── GAME CARD ── */
.game-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; cursor: pointer; transition: transform .15s, box-shadow .15s, border-color .15s; }
.game-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.35); border-color: rgba(255,255,255,.14); }
.game-cover { position: relative; height: 180px; overflow: hidden; }
.game-body  { padding: 1rem 1rem .9rem; }
.game-name  { font-size: .92rem; font-weight: 700; color: var(--text); line-height: 1.3; margin-bottom: .35rem; }
.game-meta  { font-size: 11.5px; color: var(--muted); display: flex; gap: .75rem; margin-bottom: .6rem; }
.game-summary { font-size: 11.5px; color: var(--muted); line-height: 1.55; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.game-badge { display: inline-flex; align-items: center; font-size: 10px; font-weight: 600; padding: 3px 9px; border-radius: 999px; background: var(--green-t); color: #7dcf7c; border: 1px solid rgba(76,170,75,.3); letter-spacing: .03em; }

/* ── BOX ART (CSS) ── */
.box { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
.box-title { position: absolute; bottom: 0; left: 0; right: 0; padding: .65rem .75rem .6rem; font-size: 14px; font-weight: 800; line-height: 1.2; letter-spacing: -.01em; text-shadow: 0 1px 8px rgba(0,0,0,.6); }
.box-pub { font-size: 8.5px; font-weight: 500; text-transform: uppercase; letter-spacing: .08em; opacity: .65; margin-top: 2px; }
.box-icon { font-size: 48px; opacity: .75; filter: drop-shadow(0 2px 8px rgba(0,0,0,.4)); z-index: 1; }

/* Individual box art themes */
.box-ember {
  background: linear-gradient(160deg, #7c1d00 0%, #c43a00 35%, #e85c1a 60%, #f5a623 100%);
}
.box-ember::before { content:''; position:absolute; top:-20px; right:-20px; width:120px; height:120px; background:radial-gradient(circle,rgba(255,200,50,.25),transparent 70%); border-radius:50%; }
.box-ember .box-title { background: linear-gradient(to top, rgba(100,10,0,.9), transparent); color: #ffd580; }

.box-cartographers {
  background: linear-gradient(155deg, #0b1e3d 0%, #1a3a6b 40%, #2a5298 70%, #1e4d8c 100%);
}
.box-cartographers::before { content:''; position:absolute; inset:0; background-image: repeating-linear-gradient(0deg, transparent, transparent 18px, rgba(255,255,255,.03) 18px, rgba(255,255,255,.03) 19px), repeating-linear-gradient(90deg, transparent, transparent 18px, rgba(255,255,255,.03) 18px, rgba(255,255,255,.03) 19px); }
.box-cartographers .box-title { background: linear-gradient(to top, rgba(5,15,40,.95), transparent); color: #d4af60; }

.box-frostfall {
  background: linear-gradient(160deg, #0a1628 0%, #0e2a4e 30%, #1a5276 60%, #2e86c1 100%);
}
.box-frostfall::before { content:''; position:absolute; top:10px; left:50%; transform:translateX(-50%); width:90px; height:90px; background:radial-gradient(circle,rgba(174,214,241,.2),transparent 70%); border-radius:50%; }
.box-frostfall .box-title { background: linear-gradient(to top, rgba(5,15,35,.95), transparent); color: #aed6f1; }

.box-copper {
  background: linear-gradient(160deg, #1a0e00 0%, #3d2100 35%, #7d4e00 65%, #c17f24 100%);
}
.box-copper::before { content:''; position:absolute; bottom:-10px; right:-10px; width:100px; height:100px; background:radial-gradient(circle,rgba(193,127,36,.3),transparent 70%); border-radius:50%; }
.box-copper .box-title { background: linear-gradient(to top, rgba(20,10,0,.95), transparent); color: #e8b84b; }

.box-librarian {
  background: linear-gradient(160deg, #0d0018 0%, #2d0057 40%, #4a1078 65%, #6a20a0 100%);
}
.box-librarian::before { content:''; position:absolute; inset:0; background-image: radial-gradient(ellipse at 30% 20%, rgba(180,100,255,.12) 0%, transparent 60%); }
.box-librarian .box-title { background: linear-gradient(to top, rgba(8,0,20,.95), transparent); color: #d7aef0; }

.box-harbor {
  background: linear-gradient(160deg, #001a2c 0%, #003d5c 35%, #006994 60%, #00a8cc 100%);
}
.box-harbor::before { content:''; position:absolute; bottom:20px; left:0; right:0; height:30px; background:linear-gradient(to right, rgba(0,168,204,.15), rgba(0,168,204,.08), rgba(0,168,204,.15)); border-radius:50%; }
.box-harbor .box-title { background: linear-gradient(to top, rgba(0,15,30,.95), transparent); color: #7ee8ff; }

.box-wildroot {
  background: linear-gradient(160deg, #071a00 0%, #14400a 35%, #266b10 65%, #3d9c1e 100%);
}
.box-wildroot::before { content:''; position:absolute; top:-10px; left:-10px; width:110px; height:110px; background:radial-gradient(circle,rgba(80,200,50,.1),transparent 70%); border-radius:50%; }
.box-wildroot .box-title { background: linear-gradient(to top, rgba(5,15,2,.95), transparent); color: #a8e063; }

.box-clocktower {
  background: linear-gradient(160deg, #111111 0%, #2a2a2a 35%, #444 65%, #666 100%);
}
.box-clocktower::before { content:''; position:absolute; inset:0; background-image: repeating-linear-gradient(45deg, transparent, transparent 3px, rgba(255,255,255,.015) 3px, rgba(255,255,255,.015) 4px); }
.box-clocktower .box-title { background: linear-gradient(to top, rgba(5,5,5,.95), transparent); color: #f5c842; }

.box-verdant {
  background: linear-gradient(160deg, #003322 0%, #005533 35%, #007a44 65%, #00a060 100%);
}
.box-verdant .box-title { background: linear-gradient(to top, rgba(0,20,12,.95), transparent); color: #80ffcc; }

.box-drifters {
  background: linear-gradient(160deg, #1a0a2e 0%, #2d1060 35%, #4a1a8a 65%, #6830b0 100%);
}
.box-drifters::before { content:''; position:absolute; inset:0; background-image:radial-gradient(ellipse at 60% 30%, rgba(120,60,255,.12) 0%, transparent 55%); }
.box-drifters .box-title { background: linear-gradient(to top, rgba(10,4,20,.95), transparent); color: #c0a0ff; }

/* ── ABOUT ── */
.about-section { border-top: 1px solid var(--border); max-width: 1100px; margin: 0 auto; padding: 2.5rem 1.5rem 3rem; }
.about-inner { display: flex; gap: 3rem; align-items: flex-start; flex-wrap: wrap; }
.about-brand { display: flex; gap: 14px; align-items: center; flex-shrink: 0; }
.about-logo  { width: 48px; height: 48px; border-radius: 12px; object-fit: contain; }
.about-name  { font-size: 1.1rem; font-weight: 800; color: var(--text); letter-spacing: -.01em; }
.about-loc   { font-size: 12px; color: var(--muted); margin-top: 2px; }
.about-text  { flex: 1; font-size: 13.5px; color: var(--muted); line-height: 1.7; min-width: 220px; }
.about-social { display: flex; gap: 8px; flex-wrap: wrap; }
.social-btn  { font-size: 11px; font-weight: 500; padding: 6px 13px; border-radius: 999px; border: 1px solid var(--border); color: var(--muted); text-decoration: none; transition: all .15s; display: flex; align-items: center; gap: 5px; }
.social-btn:hover { color: var(--text); border-color: rgba(255,255,255,.2); }
.social-btn.set { border-color: rgba(76,170,75,.35); color: #7dcf7c; }

/* ── FOOTER ── */
footer { border-top: 1px solid var(--border); text-align: center; padding: 1.5rem; font-size: 12px; color: var(--muted2); }
footer a { color: var(--muted2); text-decoration: none; }
footer a:hover { color: var(--muted); }

@media(max-width:600px){ .game-grid { grid-template-columns: 1fr 1fr; } .carousel { height: 160px; } .about-inner { gap: 1.5rem; } }
</style>
</head>
<body>

<!-- Banner -->
<div class="banner">
  <div class="banner-inner">
    <img class="banner-logo" src="images/gb_logo.png" alt="Gb" />
    <div class="banner-brand">
      <div class="banner-name">Ironwood Press</div>
      <div class="banner-tag">Independent board game publisher · Est. 2017</div>
    </div>
    <div class="banner-nav">
      <a href="gameboard/demo" class="banner-btn">Our Games</a>
      <a href="gameboard/demo" class="banner-btn">About</a>
      <a href="gameboard/features" class="banner-btn primary">Get GameBoard</a>
    </div>
  </div>
</div>

<!-- Demo notice -->
<div class="demo-notice">
  🎲 This is a <strong>GameBoard demo</strong> — fictional publisher, made-up games. &nbsp;<a href="gameboard/features">Learn how to build yours →</a>
</div>

<!-- Carousel -->
<div class="carousel">
  <div class="carousel-overlay"></div>
  <div class="carousel-content">
    <div class="carousel-co">Ironwood Press</div>
    <div class="carousel-sub">Games that linger at the table long after the last card is played</div>
  </div>
  <div class="carousel-dots">
    <span></span><span></span><span></span><span></span>
  </div>
</div>

<!-- Catalog -->
<div class="catalog">
  <div class="catalog-header">
    <span class="catalog-title">Published Games</span>
    <span class="catalog-count">10 titles</span>
  </div>

  <div class="game-grid">

    <!-- 1. Ember Roads -->
    <div class="game-card">
      <div class="game-cover">
        <div class="box box-ember">
          <div class="box-icon">🔥</div>
          <div class="box-title">Ember Roads<div class="box-pub">Ironwood Press</div></div>
        </div>
      </div>
      <div class="game-body">
        <div class="game-name">Ember Roads</div>
        <div class="game-meta"><span>👥 2–5</span><span>⏱ 75 min</span><span>🎯 12+</span></div>
        <div class="game-summary">Race across a burning continent, trading fuel and favors before the last road turns to ash.</div>
        <br/><span class="game-badge">Published</span>
      </div>
    </div>

    <!-- 2. The Cartographers Guild -->
    <div class="game-card">
      <div class="game-cover">
        <div class="box box-cartographers">
          <div class="box-icon">🗺️</div>
          <div class="box-title">The Cartographers Guild<div class="box-pub">Ironwood Press</div></div>
        </div>
      </div>
      <div class="game-body">
        <div class="game-name">The Cartographers Guild</div>
        <div class="game-meta"><span>👥 1–4</span><span>⏱ 90 min</span><span>🎯 14+</span></div>
        <div class="game-summary">Explore uncharted lands, fill in the blanks on your map, and claim the glory of discovery.</div>
        <br/><span class="game-badge">Published</span>
      </div>
    </div>

    <!-- 3. Frostfall -->
    <div class="game-card">
      <div class="game-cover">
        <div class="box box-frostfall">
          <div class="box-icon">❄️</div>
          <div class="box-title">Frostfall<div class="box-pub">Ironwood Press</div></div>
        </div>
      </div>
      <div class="game-body">
        <div class="game-name">Frostfall</div>
        <div class="game-meta"><span>👥 2–4</span><span>⏱ 60 min</span><span>🎯 10+</span></div>
        <div class="game-summary">Survive a harsh arctic winter by sharing resources — until you can't afford to share anymore.</div>
        <br/><span class="game-badge">Published</span>
      </div>
    </div>

    <!-- 4. Copper & Coal -->
    <div class="game-card">
      <div class="game-cover">
        <div class="box box-copper">
          <div class="box-icon">⚙️</div>
          <div class="box-title">Copper &amp; Coal<div class="box-pub">Ironwood Press</div></div>
        </div>
      </div>
      <div class="game-body">
        <div class="game-name">Copper &amp; Coal</div>
        <div class="game-meta"><span>👥 2–4</span><span>⏱ 80 min</span><span>🎯 13+</span></div>
        <div class="game-summary">Build industrial empires in the age of steam. Control rail lines, forge contracts, and outpace rivals.</div>
        <br/><span class="game-badge">Published</span>
      </div>
    </div>

    <!-- 5. The Last Librarian -->
    <div class="game-card">
      <div class="game-cover">
        <div class="box box-librarian">
          <div class="box-icon">📚</div>
          <div class="box-title">The Last Librarian<div class="box-pub">Ironwood Press</div></div>
        </div>
      </div>
      <div class="game-body">
        <div class="game-name">The Last Librarian</div>
        <div class="game-meta"><span>👥 1–4</span><span>⏱ 50 min</span><span>🎯 11+</span></div>
        <div class="game-summary">Recover ancient tomes before the city falls. A co-op puzzle race against time and bureaucracy.</div>
        <br/><span class="game-badge">Published</span>
      </div>
    </div>

    <!-- 6. Sunken Harbor -->
    <div class="game-card">
      <div class="game-cover">
        <div class="box box-harbor">
          <div class="box-icon">⚓</div>
          <div class="box-title">Sunken Harbor<div class="box-pub">Ironwood Press</div></div>
        </div>
      </div>
      <div class="game-body">
        <div class="game-name">Sunken Harbor</div>
        <div class="game-meta"><span>👥 2–5</span><span>⏱ 100 min</span><span>🎯 14+</span></div>
        <div class="game-summary">Salvage riches from the deep, but beware — what you recover may change the fate of the port.</div>
        <br/><span class="game-badge">Published</span>
      </div>
    </div>

    <!-- 7. Wildroot -->
    <div class="game-card">
      <div class="game-cover">
        <div class="box box-wildroot">
          <div class="box-icon">🌿</div>
          <div class="box-title">Wildroot<div class="box-pub">Ironwood Press</div></div>
        </div>
      </div>
      <div class="game-body">
        <div class="game-name">Wildroot</div>
        <div class="game-meta"><span>👥 2–4</span><span>⏱ 40 min</span><span>🎯 8+</span></div>
        <div class="game-summary">A gentle engine-builder about coaxing a wild forest to bloom. Serene and surprisingly cutthroat.</div>
        <br/><span class="game-badge">Published</span>
      </div>
    </div>

    <!-- 8. The Iron Clocktower -->
    <div class="game-card">
      <div class="game-cover">
        <div class="box box-clocktower">
          <div class="box-icon">🕰️</div>
          <div class="box-title">The Iron Clocktower<div class="box-pub">Ironwood Press</div></div>
        </div>
      </div>
      <div class="game-body">
        <div class="game-name">The Iron Clocktower</div>
        <div class="game-meta"><span>👥 3–6</span><span>⏱ 120 min</span><span>🎯 16+</span></div>
        <div class="game-summary">A steampunk social deduction game. One player winds the clock — the others must stop it in time.</div>
        <br/><span class="game-badge">Published</span>
      </div>
    </div>

    <!-- 9. Verdant Commons -->
    <div class="game-card">
      <div class="game-cover">
        <div class="box box-verdant">
          <div class="box-icon">🌱</div>
          <div class="box-title">Verdant Commons<div class="box-pub">Ironwood Press</div></div>
        </div>
      </div>
      <div class="game-body">
        <div class="game-name">Verdant Commons</div>
        <div class="game-meta"><span>👥 2–4</span><span>⏱ 35 min</span><span>🎯 8+</span></div>
        <div class="game-summary">Tend a shared garden with your neighbors. A light, charming drafting game about community and growth.</div>
        <br/><span class="game-badge">Published</span>
      </div>
    </div>

    <!-- 10. Night Drifters -->
    <div class="game-card">
      <div class="game-cover">
        <div class="box box-drifters">
          <div class="box-icon">🌙</div>
          <div class="box-title">Night Drifters<div class="box-pub">Ironwood Press</div></div>
        </div>
      </div>
      <div class="game-body">
        <div class="game-name">Night Drifters</div>
        <div class="game-meta"><span>👥 2–5</span><span>⏱ 55 min</span><span>🎯 12+</span></div>
        <div class="game-summary">Urban explorers mapping a city that only exists after dark. A hand-management game with shifting alliances.</div>
        <br/><span class="game-badge">Published</span>
      </div>
    </div>

  </div>
</div>

<!-- About -->
<div class="about-section">
  <div class="about-inner">
    <div>
      <div class="about-brand">
        <img class="about-logo" src="images/gb_logo.png" alt="Ironwood Press" />
        <div>
          <div class="about-name">Ironwood Press</div>
          <div class="about-loc">📍 Portland, OR</div>
        </div>
      </div>
      <div style="margin-top:1rem;">
        <div class="about-social">
          <a href="#" class="social-btn set">📸 Instagram</a>
          <a href="#" class="social-btn set">🐦 X / Twitter</a>
          <a href="#" class="social-btn set">🎲 BoardGameGeek</a>
          <a href="#" class="social-btn">📘 Facebook</a>
          <a href="#" class="social-btn">▶️ YouTube</a>
        </div>
      </div>
    </div>
    <p class="about-text">We're a small independent publisher making games for people who linger at the table. Every Ironwood game is designed to be picked up quickly and played for years — games that create moments worth talking about long after you've put the pieces away.</p>
  </div>
</div>

<footer>
  Powered by <a href="gameboard/features">GameBoard</a> — the catalog tool for board game publishers &nbsp;·&nbsp; <a href="/">ZapSheets</a>
</footer>

</body>
</html>
