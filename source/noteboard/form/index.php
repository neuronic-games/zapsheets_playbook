<?php
/**
 * NoteBoard public feedback form.
 * URL: /{sheet_id}/noteboard/{hash}
 *
 * Resolves the hash → game name from noteboard-index.json,
 * renders the form, and on POST submits via submitNote.php.
 */

// Parse sheet_id and hash from the URL
$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip BASE_PATH prefix if set
$_bp = '';
if (function_exists('getenv')) {
    $_bpFile = __DIR__ . '/../../../dotEnv.php';
    if (file_exists($_bpFile)) {
        require_once $_bpFile;
        $_bp = rtrim($_ENV['BASE_PATH'] ?? '', '/');
        if ($_bp !== '' && str_starts_with($_rp, $_bp)) {
            $_rp = substr($_rp, strlen($_bp)) ?: '/';
        }
    }
}

preg_match('#^/([A-Za-z0-9_\-]+)/noteboard/([a-f0-9]+)/?$#', $_rp, $_m);
$_sheet_id = $_m[1] ?? '';
$_hash     = $_m[2] ?? '';

// Derive app base (everything before /{sheet_id})
$_base = '/';
if ($_sheet_id) {
    $pos = strpos($_rp, '/' . $_sheet_id . '/');
    if ($pos !== false) {
        $_base = substr($_rp, 0, $pos + 1);
    }
}
if (substr($_base, -1) !== '/') { $_base .= '/'; }

// Resolve hash → internal key + display name
$_game_key  = '';   // internal key from noteboard-index.json (used to find the tab)
$_game_name = '';   // display name shown to the user
$_error     = '';

if (!$_sheet_id || !$_hash) {
    $_error = 'Invalid feedback link.';
} else {
    $indexPath = __DIR__ . '/../../../sheets/' . $_sheet_id . '/noteboard-index.json';
    if (!file_exists($indexPath)) {
        $_error = 'This feedback link has not been set up yet.';
    } else {
        $index = json_decode(file_get_contents($indexPath), true) ?: [];
        if (!isset($index[$_hash])) {
            $_error = 'This feedback link is not valid.';
        } else {
            $_game_key  = $index[$_hash];          // e.g. "notes" or "Dim Sum A-Go-Go"
            $_game_name = $_game_key;              // default display = internal key
            // Override display name from cache if available
            $_safe       = str_replace(['/', '\\'], '-', $_game_key);
            $_cache_path = __DIR__ . '/../../../sheets/' . $_sheet_id . '/notes-' . $_safe . '-en.json';
            if (file_exists($_cache_path)) {
                $_cache = json_decode(file_get_contents($_cache_path), true) ?: [];
                if (isset($_cache['topic']) && $_cache['topic'] !== '') {
                    $_game_name = $_cache['topic'];
                }
            }
        }
    }
}

// Handle POST submission
$_submitted   = false;
$_submit_err  = '';

if ($_game_name && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_name  = trim($_POST['nb_name']  ?? '');
    $post_email = trim($_POST['nb_email'] ?? '');
    $post_note  = trim($_POST['nb_note']  ?? '');

    if (!$post_note) {
        $_submit_err = 'Please write your feedback before submitting.';
    } else {
        $pythonPath = $_ENV['PYTHON'] ?? 'python3';
        $payload    = json_encode([
            'key'   => $_game_key,    // internal key → determines which tab to write to
            'game'  => $_game_name,   // display name → stored in cache topic field
            'name'  => $post_name,
            'email' => $post_email,
            'note'  => $post_note,
        ], JSON_UNESCAPED_UNICODE);
        $encoded = base64_encode($payload);
        $arg     = $_sheet_id . '|' . $encoded;
        $cmd     = escapeshellarg($pythonPath) . ' '
                 . escapeshellarg(__DIR__ . '/../../../push/gsubmitnote.py') . ' '
                 . escapeshellarg($arg) . ' 2>&1';
        $output  = trim((string) shell_exec($cmd));
        $result  = json_decode($output, true);

        if (!empty($result['ok'])) {
            $_submitted = true;
        } else {
            $_submit_err = ($result['error'] ?? $output) ?: 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= $_game_name ? htmlspecialchars($_game_name) . ' — Feedback' : 'NoteBoard' ?></title>
<link rel="icon" type="image/png" href="images/pb_icon_180.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

body {
  background:#f3f0eb;
  font-family:'DINRegular',Arial,sans-serif;
  color:#1a1a1a;
  min-height:100vh;
  display:flex;
  align-items:flex-start;
  justify-content:center;
  padding:2.5rem 1rem 3rem;
}

.page { width:100%; max-width:480px; }

/* ── Brand header ─────────────────────────────────────────────── */
.brand { text-align:center; margin-bottom:2rem; }
.brand-icon {
  width:64px; height:64px; border-radius:16px;
  background:#1a1a1a; display:inline-flex;
  align-items:center; justify-content:center; margin-bottom:.7rem;
  box-shadow:0 4px 16px rgba(0,0,0,.18);
}
.brand-icon svg { width:34px; height:34px; }
.brand-name {
  font-family:'DINBlack',sans-serif; font-size:1.5rem;
  text-transform:uppercase; letter-spacing:.09em; color:#111; line-height:1;
}

/* ── Card ─────────────────────────────────────────────────────── */
.card {
  background:#fff; border-radius:16px;
  padding:2rem 2rem 2.25rem;
  box-shadow:0 2px 24px rgba(0,0,0,.08);
}

.game-label {
  font-size:.73rem; font-family:'DINBlack',sans-serif;
  text-transform:uppercase; letter-spacing:.09em;
  color:#c8860a; margin-bottom:.3rem;
}
.game-name {
  font-family:'DINBlack',sans-serif; font-size:1.5rem;
  color:#111; margin-bottom:1.4rem; line-height:1.15;
}

.field-label {
  display:block; font-size:.72rem; font-family:'DINBlack',sans-serif;
  text-transform:uppercase; letter-spacing:.07em; color:#888;
  margin-bottom:.3rem;
}
.field-input, .field-textarea {
  display:block; width:100%;
  padding:.65rem .9rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.95rem; color:#111;
  border:1.5px solid #d0ccc5; border-radius:9px; outline:none;
  transition:border-color .15s; background:#fafaf8;
  margin-bottom:1rem;
}
.field-input:focus, .field-textarea:focus { border-color:#c8860a; background:#fff; }
.field-textarea { min-height:120px; resize:vertical; line-height:1.55; }

.optional-tag {
  font-family:'DINRegular',sans-serif; font-size:.68rem;
  text-transform:none; color:#bbb; letter-spacing:0; margin-left:.3rem;
}

/* ── Submit ───────────────────────────────────────────────────── */
.submit-btn {
  width:100%; height:2.85rem;
  background:#c8860a; color:#fff;
  font-family:'DINBlack',sans-serif; font-size:.9rem;
  text-transform:uppercase; letter-spacing:.09em;
  border:none; border-radius:9px; cursor:pointer;
  transition:background .15s, transform .1s, box-shadow .15s;
  box-shadow:0 2px 8px rgba(200,134,10,.3);
  margin-top:.25rem;
}
.submit-btn:hover { background:#a06d08; transform:translateY(-1px); box-shadow:0 4px 14px rgba(200,134,10,.35); }
.submit-btn:active { transform:translateY(0); }
.submit-btn:disabled { opacity:.5; cursor:default; transform:none; box-shadow:none; }

/* ── Error ────────────────────────────────────────────────────── */
.error-box {
  background:#fff1f0; border:1px solid #fca5a5; color:#b91c1c;
  border-radius:8px; padding:.7rem .9rem; font-size:.85rem; margin-bottom:1rem;
}

/* ── Thank you ────────────────────────────────────────────────── */
.thankyou { text-align:center; }
.thankyou-icon {
  width:72px; height:72px; border-radius:50%;
  background:#16a34a; display:inline-flex;
  align-items:center; justify-content:center; margin-bottom:1.2rem;
  box-shadow:0 4px 16px rgba(22,163,74,.28);
}
.thankyou-icon svg { width:36px; height:36px; }
.thankyou h2 {
  font-family:'DINBlack',sans-serif; font-size:1.5rem;
  text-transform:uppercase; letter-spacing:.07em; color:#111; margin-bottom:.6rem;
}
.thankyou p { font-size:.92rem; color:#555; line-height:1.6; }

/* ── Error page ───────────────────────────────────────────────── */
.errpage { text-align:center; padding:2rem 0; }
.errpage p { font-size:.92rem; color:#888; }
</style>
</head>
<body>
<div class="page">

  <!-- Branding -->
  <div class="brand">
    <div class="brand-icon">
      <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="6" y="8" width="32" height="28" rx="4" fill="none" stroke="#fff" stroke-width="2.5"/>
        <line x1="12" y1="16" x2="32" y2="16" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
        <line x1="12" y1="22" x2="32" y2="22" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
        <line x1="12" y1="28" x2="24" y2="28" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
        <circle cx="34" cy="30" r="7" fill="#c8860a"/>
        <line x1="34" y1="27" x2="34" y2="33" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
        <line x1="31" y1="30" x2="37" y2="30" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </div>
    <div class="brand-name">NoteBoard</div>
  </div>

  <div class="card">

<?php if ($_error): ?>
    <div class="errpage">
      <p><?= htmlspecialchars($_error) ?></p>
    </div>

<?php elseif ($_submitted): ?>
    <div class="thankyou">
      <div class="thankyou-icon">
        <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
          <polyline points="8,18 15,25 28,11" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2>Thank You!</h2>
      <p>Your feedback on <strong><?= htmlspecialchars($_game_name) ?></strong> has been recorded. We appreciate you taking the time to share your thoughts.</p>
    </div>

<?php else: ?>
    <div class="game-label">Feedback for</div>
    <div class="game-name"><?= htmlspecialchars($_game_name) ?></div>

    <?php if ($_submit_err): ?>
    <div class="error-box"><?= htmlspecialchars($_submit_err) ?></div>
    <?php endif; ?>

    <form method="post" action="" onsubmit="return beforeSubmit(this)">
      <label class="field-label" for="nb_name">Your Name <span class="optional-tag">(optional)</span></label>
      <input class="field-input" id="nb_name" name="nb_name" type="text"
        placeholder="Jane Smith"
        value="<?= htmlspecialchars($_POST['nb_name'] ?? '') ?>" autocomplete="name" />

      <label class="field-label" for="nb_email">Email <span class="optional-tag">(optional)</span></label>
      <input class="field-input" id="nb_email" name="nb_email" type="email"
        placeholder="you@example.com"
        value="<?= htmlspecialchars($_POST['nb_email'] ?? '') ?>" autocomplete="email" />

      <label class="field-label" for="nb_note">Your Feedback</label>
      <textarea class="field-textarea" id="nb_note" name="nb_note"
        placeholder="Share your thoughts about this game…"
        required><?= htmlspecialchars($_POST['nb_note'] ?? '') ?></textarea>

      <button type="submit" class="submit-btn" id="submitBtn">Submit Feedback</button>
    </form>

<?php endif; ?>

  </div><!-- /.card -->
</div><!-- /.page -->

<script>
window.beforeSubmit = function(form) {
  var note = form.nb_note.value.trim();
  if (!note) {
    form.nb_note.focus();
    return false;
  }
  var btn = document.getElementById('submitBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }
  return true;
};
</script>
</body>
</html>
