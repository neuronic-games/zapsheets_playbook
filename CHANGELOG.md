# Changelog

## 2026-10-01

### DevBoard — Time & Materials (T&M)

Added a full Time & Materials tracking feature to both the owner and collab views of DevBoard.

**Entry**
- New **+ T&M** button on both the owner dashboard (per game card) and the collab view (when signed in)
- Dialog has a **Notes** field and **Time** combo box (side by side), followed by one or more **Material / Cost** rows
- Time field is a combo box with preset increments (15 min, 30 min, 1 hr, etc.) plus free-text input
- Material rows auto-add as you type; date always defaults to today
- Owner view uses **My Name** from Settings as the person; collab view uses the signed-in user

**Storage**
- T&M rows are written to the `[Game] dev` Google Sheet as `Time` or `Material` event rows
- Time rows store minutes/hours in Observations; Material rows store cost (`$X.XX`) in Observations and description in Solutions
- Sheet rows are color-coded: amber for Time, teal for Material

**Display**
- T&M rows are hidden from session lists in both views
- A **T&M summary row** appears when a game is expanded:
  - Owner view: shows total time and cost **per person** (e.g. `JEAN 2h 30m · $45.00`)
  - Collab view: shows **Your T&M** totals for the signed-in user
