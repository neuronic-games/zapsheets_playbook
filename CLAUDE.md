# DevBoard — Project Notes for Claude

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

## Python Scripts
- All scripts take `{sheet_id}|{base64_json}` as a single CLI argument
- `safe_str(v)` prefixes strings with `'` to prevent Sheets formula interpretation (except `=IMAGE()`)
- Row resize after image insert: `UpdateDimensionPropertiesRequest` via `batch_update`

## Fetch (collab view)
- Only fetch sheets: `games`, `people`, `bios`, `contracts`
- Do not fetch individual game session tabs
