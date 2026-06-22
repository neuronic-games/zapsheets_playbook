# ZapSheets Playbook — Specification

Board game pitch-tracking system. One Google Sheet per game catalog; a dashboard lets you view pitches by game or publisher, filter by status, sync from the sheet, and see stats on the Dashboard.

---

## Directory Structure

```
zapsheets_playbook/
├── router.php                        # URL router for the PHP built-in server
├── source/
│   └── dashboard/
│       └── index.php                 # Canonical dashboard template (edit here)
├── sheets/
│   └── {sheetId}/
│       ├── pitches.json              # Pitch entries
│       ├── games.json                # Game catalog
│       ├── people.json               # Publisher contacts
│       ├── settings.json             # User settings (name, phone, email)
│       ├── connections.json          # Fallback for pitches
│       └── dashboard/
│           └── index.php             # Deployed copy of source/dashboard/index.php
├── push/
│   ├── pushSheetUpdate.php           # Receives SYNC POST; invokes gread.py
│   ├── deploySource.php              # Copies source/ → all sheets/*/dashboard/
│   └── gread.py                      # Python: reads Google Sheet tab → writes JSON
└── docs/
    ├── architecture.html             # Architecture diagram
    └── spec.md                       # This document
```

### Key rule
`source/dashboard/index.php` is the single source of truth for the UI. Never edit the per-sheet copies directly. Changes must be deployed via SYNC or the shell command:

```bash
for dir in sheets/*/dashboard; do cp source/dashboard/index.php "$dir/index.php"; done
```

---

## Data Model

### pitches.json
Each row is a single communication event between the user and a publisher about a game.

| Field | Type | Notes |
|---|---|---|
| Date | string | ISO date of the communication |
| Game | string | Game title (matches games.json Name) |
| Publisher | string | Publisher company name |
| Contact | string | Contact person at publisher |
| Status | string | `Pitched` · `Interested` · `Passed` · `Signed` · `Published` |
| Email | string | Contact email (optional fallback) |
| Notes | string | Free text |

### games.json
One row per game in the catalog.

| Field | Type | Notes |
|---|---|---|
| Name | string | Canonical game title (primary key) |
| Designer1–4 | string | Up to four designer names |
| Date Started | string | When pitching began |
| Date Signed | string | When a deal was signed |
| Date Published | string | When published |
| Status | string | `Signed` · `Published` (overrides date-based detection) |
| Rules | string | URL to rules PDF/doc |
| Play | string | URL to play online |
| Print | string | URL to print-and-play |
| Sellsheet | string / Sellsheet URL | URL to sell sheet |
| View / BGG | string | URL to BGG page or website |
| Playbook Sheet ID | string | Sheet ID of linked game playbook (→ /sheets/{id}/view) |

### people.json
Publisher contact directory.

| Field | Type | Notes |
|---|---|---|
| Name | string | Contact full name |
| Company | string | Publisher name |
| Email | string | Email address |

### settings.json
Two-column key/value sheet. The first column header is `My Name`; the value column header is the user's name (e.g. `TAM`).

| My Name | Value |
|---|---|
| My Email | contact@example.com |
| My Phone | 917-000-0000 |

---

## URL Routes (router.php)

| Pattern | Resolves to |
|---|---|
| `/{id}/dashboard` | `sheets/{id}/dashboard/index.php` |
| `/{id}/view` | `sheets/{id}/view/index.php` |
| `/{id}/*.json` | `sheets/{id}/*.json` (static file) |
| `/push/*` | `push/*.php` |

---

## Dashboard Application (index.php)

### Global State

```js
var currentView     = 'game';     // 'game' | 'publisher' | 'dashboard'
var currentSort     = 'date';     // 'date' | 'alpha'
var allPitches      = [];         // all pitch entries
var filteredPitches = [];         // pitches after search applied
var searchQuery     = '';
var activeFilters   = {};         // { pitched, interested, passed, signed, published }
var peopleIndex     = {};         // "Name|Company" → email
var gamesIndex      = {};         // game name → games.json row
var totalGameCount  = 0;
var totalPubCount   = 0;
var myName  = '';
var myPhone = '';
var myEmail = '';
```

### Data Loading

`loadAll(onComplete)` fires four parallel XHR calls and waits for all to complete before calling `render()`:

- `pitches.json` (falls back to `connections.json`)
- `settings.json`
- `people.json`
- `games.json`

### Views

#### Game View
Groups: Game → Publisher → Contact → entry rows.

- Games with no pitch entries are injected from `gamesIndex` and show "No pitches yet".
- Publisher rows are collapsed by default; click to expand.
- Passed publishers are additionally dimmed (grey text).
- Age tags (3mo+ / 6mo+) appear on the game header based on the most stale non-passed publisher.
- Link pills (Rules, Play, Print, Sellsheet, View, Info) appear when expanded, sourced from games.json.

#### Publisher View
Groups: Publisher → Game → Contact → entry rows.

- Game rows are collapsed by default with their current status pill.
- Click to expand contacts and history.

#### Dashboard View
Stat cards, three Chart.js charts, and a game timeline section.

**Stat cards:** Total Games, Published, Signed, In Pitching, Not Pitched, Publishers, Avg to Sign (mo), Avg to Publish (mo).

**Charts (Chart.js 4.4.1, lazy-loaded):**
- Status doughnut (Not Pitched / Pitching / Signed / Published)
- Top 12 Publishers horizontal bar (by games pitched count)
- Pitches Over Time monthly bar

**Game Timelines:**
One row per game, sorted Published → Signed → Pitching → Not Started, then alphabetical. Four milestone dots connected by a line:

| Stage | Color | Date source |
|---|---|---|
| Started | Navy | Date Started in games.json |
| Pitching | Navy (pill) | Earliest pitch entry date |
| Signed | Purple | Date Signed in games.json |
| Published | Blue | Date Published in games.json |

The Pitching milestone is a pill shape. Width = `min(count, 30) × 5 px` (max 150 px), where count = unique publishers pitched.

### Summary Bar (Filter Pills)

Five clickable pills with OR-logic multi-select:

| Pill | Counts |
|---|---|
| Pitched | Game-publisher pairs whose latest status is Pitched |
| Interested | Game-publisher pairs whose latest status is Interested |
| Passed | Game-publisher pairs whose latest status is Passed |
| Signed | Games where isGameSigned = true |
| Published | Games where isGamePublished = true |

`isGameSigned` and `isGamePublished` check three sources in order: `Date Signed/Published` field, `Status` field, and any pitch entry with that status.

### Search

- Filters pitches to those matching game name, designer names, publisher, or contact.
- Unpitched games (from gamesIndex) are included when their name or designers match the query.
- When search is empty, all unpitched games are shown.

### Sort

- **Date**: most-recently-active game/publisher first.
- **Alpha**: A–Z by game/publisher name.

### Email (mailto links)

Each contact row has an Email button that generates a pre-populated `mailto:` link:

```
Subject: {Game Title}
Body:
{My Name}
{My Phone}
{My Email}

Title: {Game Title}
Rules: {url}
Print: {url}
Play:  {url}
```

Field lookup tries multiple name variants (e.g. `Rules`, `Rules URL`, `Rules Link`, `Link Rules`).

---

## Sync Mechanism

### Trigger
The SYNC button in the top bar calls `syncData()` and opens a terminal-style dialog showing live progress.

### Flow

```
syncData()
  │
  ├─ POST pushSheetUpdate.php?sheetname=pitches  → gread.py → Google Sheets → pitches.json
  ├─ POST pushSheetUpdate.php?sheetname=games    → gread.py → Google Sheets → games.json
  ├─ POST pushSheetUpdate.php?sheetname=people   → gread.py → Google Sheets → people.json
  ├─ POST pushSheetUpdate.php?sheetname=settings → gread.py → Google Sheets → settings.json
  │
  ├─ POST deploySource.php → copies source/dashboard/index.php → sheets/*/dashboard/index.php
  │
  └─ loadAll() → re-renders the current view
```

### Response prefixes (pushSheetUpdate.php / gread.py)

| Prefix | Meaning | Dialog icon |
|---|---|---|
| `ERROR:` | Something failed | ✗ red |
| `SKIP:` | Sheet tab not found | – grey |
| (other) | Success message | ✓ green |

### gread.py
Uses `gspread` + service account credentials. Reads with `get_all_values()` (not `get_all_records()`, which fails on blank column headers). Writes a JSON array of objects keyed by the first row.

---

## Key Functions Reference

| Function | Purpose |
|---|---|
| `loadAll(cb)` | Parallel-loads 4 JSON files, then calls `render()` and optional callback |
| `render(pitches, settings, people, games)` | Builds indexes, sets global state, calls `buildSummary` + `buildView` |
| `buildView()` | Dispatches to `buildGameView` / `buildPublisherView` / `buildDashboardView` |
| `buildGameView(pitches)` | Returns HTML string for Game view |
| `buildPublisherView(pitches)` | Returns HTML string for Publisher view |
| `buildDashboardView()` | Renders stat cards, charts, and timelines into #content |
| `buildSummary(pitches)` | Updates summary bar pills and toggle button labels |
| `isGameSigned(name, entries)` | true if game is signed (checks gamesIndex + entries) |
| `isGamePublished(name, entries)` | true if game is published |
| `gameAgeTag(pubMap)` | Returns 3mo+ or 6mo+ badge HTML for stale non-passed publishers |
| `latestEntry(entries)` | Returns the entry with the most recent Date |
| `toggleCard(header)` | Expands/collapses a card (game or publisher group) |
| `togglePubPassed(header)` | Expands/collapses a publisher/game sub-group |
| `syncData()` | Runs the full SYNC flow with dialog logging |
| `mailtoHref(email, gameName)` | Builds pre-populated mailto URL |
| `buildEmailBody(gameName)` | Builds email body from settings + games data |
| `escHtml(s)` | HTML-escapes a string |
| `fmtMonYr(date)` | Formats a Date as "Jan 2023" |

---

## Fonts

DINBlack and DINRegular are loaded from `../../fonts/`. All headings, labels, and badge text use DINBlack; body text uses DINRegular.

---

## Dependencies

| Dependency | Version | How loaded |
|---|---|---|
| Chart.js | 4.4.1 | Lazy-loaded from cdnjs on first Dashboard view |
| gspread (Python) | — | pip, server-side only |
| google-auth (Python) | — | pip, server-side only |

No npm packages. No frontend build step.
