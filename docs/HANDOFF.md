# Handoff — working on this repo (GameBoard apps)

Read this with `CLAUDE.md`. It is what a new session needs to pick up the work: how the person works, how this
setup behaves, how the newer features fit together, and what is still open. Keep it current: update it at the end
of each larger piece of work (and the "Open items" list whenever something is decided or finished).

Last updated: 2026-11-26.

---

## 1. How we work

- **The apps** (one repo, one server):
  - PitchBoard = `source/dashboard/index.php` (+ setup/help/features in `source/pitchboard/`, slideshow in `source/slides/`)
  - DevBoard = `source/devboard/dashboard/index.php` (owner), `source/devboard/shared/index.php` (collaborators)
  - CardBoard = `source/cardboard/index.php` (home / designs) and `source/cardboard/designer/` (`index.php` + `cardboard.js`, inlined into the page — no version number to bump)
  - BillBoard = `source/billboard/`
  - GameBoard home / dashboard = `source/gameboard/index.php`; Waiting Room = `source/waiting/index.php`
  - Short names in requests: **Pb, Db, Cb, Bb**.
- **Commit after every change** (see `CLAUDE.md` → Auto-commit). End commit messages with the attribution lines
  the session gives (Co-Authored-By / Claude-Session).
- **Deploying is the person's job**: `git push production main:test` (test), then `git push production main`.
  Say "ready to push" — don't push.
- **Cache-busting**: shared scripts are loaded with `?v=N`. When you change one, bump N everywhere it's loaded:
  - `js/gb-account.js` (now **v=16**): pitchboard/index.php, dashboard/index.php, devboard/index.php, devboard/dashboard/index.php, billboard/index.php (2×), billboard/setup/index.php
  - `js/gb-queue.js` (now **v=3**): dashboard/index.php, devboard/dashboard/index.php
  - `js/gb-acctdlg.js` (v=2) is loaded by gb-account.js, cardboard.js and gameboard/index.php
  - `js/gb-uninvited.js` (v=1) is loaded on demand by gb-account.js and CardBoard
- **Writing style** in the apps: plain words, short sentences, no jargon (the person is the user).
- **Help pages** (`source/{app}/help/index.php`) are kept in step with features; screenshots live in
  `images/help/{app}/`.

## 2. Security rules (always)

- Keys and secrets never go into tracked files. `server-env/*.env` is excluded via `.git/info/exclude` — never commit it.
- SMTP and other secrets live only in the server's `.env`.
- Never paste live `sk_live` / `rk_live` keys into chat.
- The person's email is for identification only — don't send it to outside services without asking.
- Don't type the person's passwords; ask them to sign in themselves (e.g. in the browser pane).

## 3. Working in this setup (Claude desktop / device bridge)

- The repo is on the person's Mac, reached through the device tools (`$HOME/mnt/zapsheets_playbook` in
  `device_bash`). The cloud workspace can't reach mygameboard.app, and neither can the Mac's shell; the
  **browser pane** can (the person is usually signed in there) — use it to look at the live apps.
- **git leaves lock files** (deleting isn't allowed in the connected folder): after each commit move
  `.git/HEAD.lock`, `.git/index.lock`, `.git/objects/maintenance.lock` to `.git/stale-*` names.
  `sed -i.bk` backups go to `.git/stale-files/`.
- **Staged/committed file copies can be stale**: `device_stage_files` copies can lag — wait a few seconds and
  check the content (md5 or a grep). `device_commit_files` can write an older copy: always write a NEW unique file
  name under `/mnt/user-data/outputs`, wait ~8–12 s, commit with force, then verify on the device. JPEGs re-encode,
  so check them visually.
- No PHP on the Mac: lint by staging into the workspace and running `php -l` / `node --check` there.
- Testing approach used so far: render pages locally with `php -r` + Playwright in the workspace (harness in
  `/home/claude/hp` if it still exists), or extract a function from the staged JS and run it in a small test page.
  When testing on the live site, never leave changes behind (undo, and clear `cb_draft:` keys in CardBoard).

## 4. GameBoard accounts — newer parts

(The basics are in `CLAUDE.md` → GameBoard Accounts.)

- **Account / Profile / Settings**: Account = shared across apps (name, email, picture, password, plan, codes —
  `js/gb-acctdlg.js`); Profile = what one app keeps about the person (header shows the account, "Edit in Account");
  Settings = the app's own settings, not about the person.
- **Admin sheet** (ID in `push/gameboardAuth.php` → `CB_ADMIN_SHEET`):
  - Users tab: one row per email. Columns the person added by hand are fine (code reads headers by name).
    `Role = admin` marks an admin.
  - settings tab: key in A, value in B. `WaitingRoomOn = TRUE` turns the Waiting Room on.
  - Image column holds `=IMAGE("url")`. `gaccounts.py write()` writes a row *around* the Image cell and only writes
    Image when it changed (writing the whole row blanked it for a moment and concurrent sign-ins wiped pictures).
- **Waiting Room** (`source/waiting/index.php`, route `/waiting` in `.htaccess` and `router.php`):
  - While WaitingRoomOn, a new sign-up gets `Status = waiting`, no free week, and no token: sign-up / log-in answer
    `{error, waiting: true}` and every client goes to `/waiting`.
  - Admins (Role = admin) see only the list on /waiting: oldest first, tick people + apps, **Invite**. That writes
    **Trial Apps** ("DevBoard 2026-12-02 23:11:33, PitchBoard …", one end per app), sets Status = trial, and emails
    them ("You're invited to …", button to `gameboard?login`, which opens the log-in form).
  - Apps not invited to have state **`uninvited`** (gameboardAuth `cbAccessFromUser`). A full-page screen
    (`js/gb-uninvited.js`) covers those apps; the server refuses them (`gbGate`, `cbRequireAccess`,
    `gbBlockUninvited()` in setupSheet / setupDevboard / pushSheet / cardboardSheets / CardBoard reads).
  - Changing Status by hand in the sheet still works: the free week of every app starts at the next sign-in.
  - **No subscribing to an uninvited app while the room is on** (decided 2026-11-26): `stripeCheckout.php` asks
    `gaccounts.py` (`action: room` → the row as it is now + WaitingRoomOn) and refuses an app the person is
    `uninvited` to, and the all-in-one if any app is. With the room off, checkout works as before.

## 5. CardBoard — newer parts

- **Layouts** are JSON in the sheet's `layouts` tab (one row per tab; `gcardboard.py save`). Element kinds: text,
  data (as text / image / map), image, shape, gen. Sizes in points (180 × 252 = poker).
- **Tags** come from the sheet's `tags` tab ([name] → text or picture).
- **Formatting inside a cell** (bold / italic / underline / strikethrough / colour on part of the text):
  - Read: `gread.py` writes `sheets/{id}/{tab}.fmt.json` next to the cached rows (the rows stay the same for every
    app); `gcardboard.py peek_rows` adds `_fmt` to the first rows; `cardboardData.php cbWithFmt()` attaches it to
    each row as `_fmt {field: {t: text, r: [[start, {b,i,u,s,c}]]}}`. Used only while the value still equals `t`.
    Whole-cell formatting and formula results are ignored.
  - Draw: `richCell()` in cardboard.js.
  - Edit: the Layout panel's Value box and double-click editing on the card are both rich editors (`fmtBarHtml`,
    `fmtRead`); changes are kept with the pending cell (`queueCell(..., fmt)`) and Save writes them with
    `updateCells` + `textFormatRuns` (`gcardboard.py savecells`, cells may carry `fmt`). Edits are committed on
    pointerdown outside the editor (a later blur happened after the panel was redrawn and lost the formatting).
- **Fetch** sets `cb_fetch:{sheet}` in sessionStorage and reloads; the next load asks the server with `force` so it
  re-reads the sheet instead of its cached `{tab}.json`.
- **Auto width / height** (`e.autoW`, `e.autoH`, text-like elements): drawn by `elStyle()` anchored to the text's
  alignment (left / centre / right; top / middle / bottom). Dragging a side fixes it (`fixAuto`). In list groups the
  Auto side isn't capped. While designing an empty Auto box keeps one line (`.designing .el.auto-h`).
- **Padding**: `pad` for every side, or `padSep` + `padT/padR/padB/padL` (`padBox`, `padCss`). Note: gen
  sequences also use `pad` for zero-padding digits (old overlap, left as is).
- In-place editing of light text gets a dark box (`editContrast`).
- **View-only share links** (menu → Share, owner only): `/v-{token}/cardboard`. The page only knows `v-{token}`;
  `push/cardboardView.php` maps it to the sheet (`shares/cardboard-view/{token}.json`, and
  `sheets/{id}/_cardboard_view.txt` back). cardboardData / cardboardImage / cardboardPublish (list only) resolve it and
  refuse every write; claim refuses it. Managed by `push/cardboardShare.php` (get / create / stop). Note: uploaded
  picture links (`images/{sheetId}/…`) still contain the sheet ID.
- **Drawing off-screen** (`#exportStage`) is moved to `<body>` at load: inside the hidden Export view it drew blank
  (Designs thumbnails, the Print PDF).
- **Print**: whole page fits with Zoom (−/+/Fit, pinch); double-sided pairs side by side; download as PDF
  (`runPrintPdf`, jsPDF from cdnjs, ~220 dpi).

## 6. DevBoard — newer parts

- **Save queue** (`js/gb-queue.js`): writes to queued endpoints are kept in localStorage and sent on Save / after a
  pause; reads save first; `reloadAfterPending` reloads after saving changes left from last time.
  `GBQueue.items(endpoint)` lists kept changes; `saveBeforeLeaving` saves before following a link to a
  server-built page.
- **Payments** are Contracts rows with Type = Payment (Payee, Quote as "$150.00", Date). `_fixPaymentCells()`
  repairs old payment cells **once per payment per browser** (`db_payfix:{sheet}`) — a date the column's format
  shows as 2026-11-25 looks like text and used to be "repaired" on every load, saving and reloading the page.
- **Links from the sheet** may come back as `[text](url)`: `_dbSameSite()` turns them into the plain address (and
  old zapsheets.com contract links into this site's).
- **Activity** (Summary): latest first, "Show more" at the bottom.
- The tab you're on is kept in sessionStorage (`db_tab:{sheet}`) across reloads.

## 7. PitchBoard — newer parts

- Unsaved game changes (updateGame / addGame in the queue) are laid over `games.json` after load
  (`_pbApplyKeptGames`), and older headings (Rules URL, Link Play …) are kept in step. Links to the game page / sell
  sheet / slideshow save first.

## 8. Open items

- **Toy Run (CardBoard)**: the person is pasting Dropbox links into the Card Image column of "Char - Neighbor"; the
  back's rage-frame image element needs a link to `frame_rage.png`. Power Number has no white outline (CardBoard
  text has no outline). Fonts stand in: Roboto Condensed for Oswald, DIN Medium for Eurostile.
- **CardBoard help**: text is up to date (2026-11-26: formatting, tags, share links, designs page, print zoom/PDF,
  mappings for fixed elements and groups, Auto size, padding). Screenshots retaken the same day (layout, cards,
  tags, print, export, text-selected, mapping-0/1/2, menu) with the harness in the session's `outputs/cbshot`
  (`scenes.py` builds `_shot_*.html`, screenshot the preview, `crop.py` crops into `images/help/cardboard/`).
  Not retaken: Settings and the older detail shots.
- Earlier offers not taken up (only if asked): split collaborator T&M by contract; bio fields in Pb Profile; centre
  the session chevron; "Remake slip" for payments; restyle the TM/Pulse/Note setup pages; a marker for newly added
  payments in Activity.
