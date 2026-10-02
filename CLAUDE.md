# DevBoard — Project Notes for Claude

## Auto-commit
After every turn in which you modify, create, or delete any file in this repo, run the commit skill automatically — no need for the user to ask. Stage all changes and write a concise commit message describing what changed.

## Stack
- PHP + Google Sheets (via Python scripts using gspread)
- Per-sheet data cached as JSON in `sheets/{sheet_id}/`
- Two views: `source/devboard/dashboard/index.php` (owner) and `source/devboard/shared/index.php` (collab/public)

## Dialog Pattern

Every modal dialog follows this standard behavior:

### Dirty-check
- On open: snapshot initial field values into a `_*Initial` object
- On close attempt (X button, Esc, backdrop click): compare current values to snapshot
- If dirty: call `shakeDialog(el)` and return — do not close
- After successful save: re-sync `_*Initial` so the dialog no longer appears dirty
- **Action-only panels** (e.g. sign-in forms) are never dirty — only data-entry panels with saveable fields get the dirty check

### Force-close
- Cancel buttons always close immediately, even if dirty
- Use a `forceClose*Dialog()` variant (no dirty check) wired to Cancel
- Use the checking `close*Dialog()` variant for X buttons and backdrop clicks

### Cmd+Enter to save
- The inner dialog `div` has `onkeydown` that checks `(event.metaKey||event.ctrlKey) && event.key==='Enter'`
- On match: `event.preventDefault()` then call the submit function directly
- This applies to all data-entry dialogs (not action-only panels like sign-in)

### Esc handler
- Single `keydown` listener handles all open dialogs
- Check each overlay in priority order; call the checking `close*Dialog()` for each

### Backdrop click
- Overlay `onclick` calls the checking `close*Dialog()`
- Inner dialog `onclick` uses `event.stopPropagation()` to prevent bubbling

### Naming convention
```
open*Dialog()        — populates fields, snapshots _*Initial, adds .open class
close*Dialog()       — dirty-checks; shakes if dirty, closes if clean
forceClose*Dialog()  — closes unconditionally (Cancel button)
```

### After save
- On success: call `forceClose*Dialog()` (not the checking variant)
- Re-sync `_*Initial` before closing if the dialog might stay open on error

## Combo Box Pattern

All custom combo dropdowns (type-to-filter fields with a dropdown list) follow this pattern exactly. Do not deviate — past attempts with `onblur`, body portals, or `position:fixed` all broke item selection.

### HTML structure
```html
<div class="combo-wrap" id="fooCombo">
  <input type="text" id="fooInput" class="field-input combo-input"
    autocomplete="off"
    oninput="fooRebuild(this.value)"
    onfocus="fooRebuild(this.value)"
    onkeydown="fooKey(event)" />
  <div class="combo-dropdown" id="fooDrop"></div>
</div>
```
- **No `onblur`** — ever. `onblur` fires before `onmousedown` on list items, preventing selection.
- Items go in `.combo-dropdown` which is a child of `.combo-wrap` (not appended to `document.body`).

### CSS (already in dashboard/index.php — do not duplicate)
```css
.combo-dropdown { display:none; position:absolute; left:0; right:0; top:calc(100% + 2px); ... }
.combo-wrap.open .combo-dropdown { display:block; }
.combo-option, .combo-item { padding:.5rem .8rem; ... }
.combo-option:hover, .combo-item:hover { background:#e8f4f8; color:#1a5f7a; }
```
Use class `.combo-item` for items (preferred) or `.combo-option` — both are styled.

### JS pattern
```js
function fooRebuild(filter) {
  var drop  = document.getElementById('fooDrop');
  var lower = (filter || '').toLowerCase();
  var items = SOURCE_ARRAY.filter(function(n) { return !lower || n.toLowerCase().indexOf(lower) !== -1; });
  if (!items.length) { drop.innerHTML = ''; document.getElementById('fooCombo').classList.remove('open'); return; }
  drop.innerHTML = items.map(function(n) {
    return '<div class="combo-item" data-name="' + esc(n) + '" onmousedown="fooPick(this.dataset.name)">' + esc(n) + '</div>';
  }).join('');
  document.getElementById('fooCombo').classList.add('open');
}
function fooPick(n) {
  document.getElementById('fooInput').value = n;
  document.getElementById('fooDrop').innerHTML = '';
  document.getElementById('fooCombo').classList.remove('open');
}
function fooKey(e) {
  if (e.key === 'Escape') { document.getElementById('fooCombo').classList.remove('open'); }
  if (e.key === 'Enter')  { var first = document.querySelector('#fooDrop .combo-item'); if (first) fooPick(first.dataset.name); }
}
```
- Use `onmousedown` (not `onclick`) on items so it fires before focus leaves the input.
- Use `classList.add/remove('open')` on the `.combo-wrap` — never toggle `display` directly.
- To close all open combos on dialog open: `document.getElementById('fooCombo').classList.remove('open')`.

### Container overflow
If a combo sits inside a container with `overflow:auto` or `overflow:hidden`, the dropdown will be clipped. Fix: add `overflow:visible` to the container, or restructure so the `.combo-wrap` is not a descendant of an overflowing container.

## Collab Auth

### Storage
- Accounts stored at `sheets/{sheet_id}/accounts.json` with bcrypt hashes (`password_hash` / `password_verify`)
- Bio data lives in the Google Sheet's Bios tab, cached at `sheets/{sheet_id}/bios.json`
- People names live in the People tab, cached at `sheets/{sheet_id}/people.json`
- Login state persisted in `sessionStorage` as `devboard_collab_user` — survives page refresh, cleared on tab close

### Sign-in / sign-up flow (`push/collabAuth.php`)
1. POST `email`, `password`, `id` (sheet ID)
2. If email not found → return `{ prompt_create: true }` (do not create yet)
3. Client shows confirmation panel; user clicks "Create Profile"
4. POST again with `confirm_new=1` → account is created, bio returned empty
5. If email found → verify password; on success return `{ ok, new: false, email, bio }`
6. Response always includes `bio` object built from `people.json` (name) + `bios.json` (all other fields)

### Client state (`source/devboard/shared/index.php`)
- `_collabUser` — `null` when signed out; `{ email, bio }` when signed in
- `_isNewCollabUser` — `true` only during the new-signup bio-fill flow
- `_loadStoredUser()` / `_saveStoredUser()` / `_clearStoredUser()` — sessionStorage helpers
- `_updateMenuLabel()` — updates the name/email shown below the DevBoard title
- `_updateSignedInState()` — shows/hides the not-signed-in banner and updates menu Sign In/Out label

### Profile dialog panels
The profile overlay (`#profileOverlay`) has three mutually exclusive panels:
- `#authForm` — sign-in form (email + password); action-only, never dirty
- `#authEditBio` — editable bio form; shown for new users after signup and for signed-in users opening Profile; dirty-checked per dialog pattern
- `#authProfile` — read-only bio display (currently unused path); never dirty

### Bio fetch on Profile open (`push/collabGetBio.php`)
When a signed-in user opens Profile, fresh bio is fetched from `collabGetBio.php` (reads `people.json` + `bios.json`) before populating the form. This ensures stale sessionStorage data doesn't show outdated fields.

### Email change (`push/collabUpdateEmail.php`)
Renames the email key in `accounts.json` preserving insertion order; validates new email isn't already registered.

### People sheet
- Row added when new user saves or skips their bio (not at auth time)
- Uses real name if provided, falls back to email
- `_addTopeople(name, email)` — fire-and-forget POST to `push/addPerson.php`

### Session gating
- `guardedOpenSessionDialog()` / `guardedOpenEditDialog()` — check `_collabUser`; redirect to Profile dialog if not signed in
- Edit buttons on session cards are only rendered when `_collabUser` is set

## Games Data (`sheets/{sheet_id}/games.json`)
Known columns: `Name`, `Status`, `Summary`, `Description`, `Count`, `Duration`, `Availability`, `Designer1–4`, `Cover URL`, `Image URL`, `Page URL`, `Video URL`, `Sellsheet URL`, `Play URL`, `Print URL`, `Rules URL`, `Playbook Sheet ID`, `Date Started`, `Date Signed`, `Date Published`

### Sellsheet button (collab view)
- If `Sellsheet URL` has data for the current game → show "Sellsheet" button (opens in new tab)
- Else if game page exists (`$_gameUrl`) → show "Page" button
- Otherwise → no button

## Python Scripts
- All scripts take `{sheet_id}|{base64_json}` as a single CLI argument
- `safe_str(v)` prefixes strings with `'` to prevent Sheets formula interpretation (except `=IMAGE()`)
- Row resize after image insert: `UpdateDimensionPropertiesRequest` via `batch_update`

## Fetch (collab view)
- Only fetch sheets: `games`, `people`, `bios`, `contracts`
- Do not fetch individual game session tabs
