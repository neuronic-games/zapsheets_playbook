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
- Accounts stored at `sheets/{sheet_id}/accounts.json` with bcrypt hashes
- `sessionStorage` key `devboard_collab_user` persists login across refreshes
- Two-step signup: first POST returns `{prompt_create:true}`, second with `confirm_new=1` creates account
- New user flag: `_isNewCollabUser` — set on signup, used to gate people-sheet addition
- People row added at bio save/skip time (not at auth time), using real name if available

## Python Scripts
- All scripts take `{sheet_id}|{base64_json}` as a single CLI argument
- `safe_str(v)` prefixes strings with `'` to prevent Sheets formula interpretation (except `=IMAGE()`)
- Row resize after image insert: `UpdateDimensionPropertiesRequest` via `batch_update`

## Fetch (collab view)
- Only fetch sheets: `games`, `people`, `bios`, `contracts`
- Do not fetch individual game session tabs
