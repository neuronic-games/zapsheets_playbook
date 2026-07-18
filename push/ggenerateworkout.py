# ggenerateworkout.py
# Generates 12 weeks of structured workout data and writes it to the FitBoard
# "Week" worksheet in an existing Google Spreadsheet.
#
# Arg: {sheet_id}|{base64_json}
# JSON payload: {
#   "goal":       "strength" | "hypertrophy" | "weight_loss" | "general",
#   "days":       3 | 4 | 5,
#   "level":      "beginner" | "intermediate" | "advanced",
#   "start_date": "YYYY-MM-DD",
#   "weight_lbs": float  (current body weight in lbs; 0 = unknown),
#   "age":        int    (0 = unknown)
# }

import gspread
import sys, os, json, base64
from datetime import date, timedelta

# ── Credentials ───────────────────────────────────────────────────────────────
credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

# ── Parse args ────────────────────────────────────────────────────────────────
if len(sys.argv) < 2:
    print(json.dumps({"error": "Arg required: {sheet_id}|{base64_json}"}))
    sys.exit(1)

parts = sys.argv[1].split('|', 1)
if len(parts) != 2:
    print(json.dumps({"error": "Arg must be {sheet_id}|{base64_json}"}))
    sys.exit(1)

sheet_id, json_b64 = parts

try:
    params = json.loads(base64.b64decode(json_b64).decode('utf-8'))
except Exception as e:
    print(json.dumps({"error": f"Could not decode params: {str(e)}"}))
    sys.exit(1)

goal       = str(params.get('goal',       'general')).strip().lower()
days_pw    = int(params.get('days',       4))
level      = str(params.get('level',      'beginner')).strip().lower()
start_str  = str(params.get('start_date', str(date.today()))).strip()
weight_lbs = float(params.get('weight_lbs', 0) or 0)
age        = int(params.get('age',        0) or 0)

# Validate / clamp
if goal   not in ('strength', 'hypertrophy', 'weight_loss', 'general'):
    goal = 'general'
if days_pw not in (3, 4, 5):
    days_pw = 4
if level  not in ('beginner', 'intermediate', 'advanced'):
    level = 'beginner'

try:
    start_date = date.fromisoformat(start_str)
except ValueError:
    start_date = date.today()

# ── Open spreadsheet ──────────────────────────────────────────────────────────
try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# ── Workout programs ──────────────────────────────────────────────────────────
# Each entry: list of (day_type, [exercises]) tuples.
# 'offsets' = day-of-week offsets from Monday (Mon=0, Tue=1, … Sun=6).

PROGRAMS = {
    3: {
        'days': [
            ('Full Body A', [
                'Goblet Squat',
                'Dumbbell Bench Press',
                'Seated Cable Row',
                'Dumbbell Shoulder Press',
                'Romanian Deadlift (Dumbbell)',
                'Plank',
            ]),
            ('Full Body B', [
                'Leg Press',
                'Incline Dumbbell Press',
                'Lat Pulldown',
                'Lateral Raise',
                'Lying Leg Curl',
                'Bicycle Crunch',
            ]),
            ('Full Body C', [
                'Walking Lunge',
                'Cable Chest Fly',
                'Face Pull',
                'Arnold Press',
                'Single-Leg Romanian Deadlift',
                'Dead Bug',
            ]),
        ],
        'offsets': [0, 2, 4],   # Mon / Wed / Fri
    },
    4: {
        'days': [
            ('Upper Strength', [
                'Barbell Bench Press',
                'Neutral Grip Lat Pulldown',
                'Chest-Supported Row',
                'Seated Dumbbell Shoulder Press',
                'Cable Tricep Pushdown (Rope)',
                'Alternating Dumbbell Curl',
            ]),
            ('Lower Strength', [
                'Barbell Back Squat',
                'Romanian Deadlift (Dumbbell)',
                'Leg Press',
                'Walking Lunges',
                'Standing Calf Raise',
                'Dead Bug',
            ]),
            ('Upper Hypertrophy', [
                'Incline Dumbbell Press',
                'Seated Cable Row',
                'Machine Chest Press',
                'Face Pull',
                'Overhead Tricep Extension',
                'Hammer Curl',
            ]),
            ('Lower + Core', [
                'Bulgarian Split Squat (Dumbbell)',
                'Leg Extension',
                'Lying Leg Curl',
                'Walking Lunge',
                'Ab Wheel Rollout',
                'Plank',
            ]),
        ],
        'offsets': [0, 1, 3, 4],    # Mon / Tue / Thu / Fri
    },
    5: {
        'days': [
            ('Push', [
                'Barbell Bench Press',
                'Overhead Press',
                'Incline Dumbbell Press',
                'Cable Lateral Raise',
                'Tricep Pushdown (Rope)',
                'Overhead Tricep Extension',
            ]),
            ('Pull', [
                'Barbell Row',
                'Lat Pulldown',
                'Face Pull',
                'Dumbbell Curl',
                'Hammer Curl',
                'Rear Delt Fly',
            ]),
            ('Legs', [
                'Barbell Back Squat',
                'Romanian Deadlift',
                'Leg Press',
                'Lying Leg Curl',
                'Standing Calf Raise',
                'Ab Wheel Rollout',
            ]),
            ('Upper + Core', [
                'Overhead Press',
                'Incline Cable Fly',
                'Seated Cable Row',
                'Arnold Press',
                'Skull Crusher',
                'Cable Crunch',
            ]),
            ('Lower + Core', [
                'Bulgarian Split Squat',
                'Walking Lunge',
                'Leg Extension',
                'Standing Calf Raise',
                'Dead Bug',
                'Plank',
            ]),
        ],
        'offsets': [0, 1, 2, 3, 4],  # Mon – Fri
    },
}

# ── Rep/set schemes ───────────────────────────────────────────────────────────
# Keyed by (goal, level); value is list of 3 (sets, reps) tuples — one per phase.
# Phase 0 = Weeks 1–4 (Foundation), Phase 1 = Weeks 5–8 (Build), Phase 2 = Weeks 9–12 (Peak).
# Uses en-dash (–) and multiplication sign (×) to match existing FitBoard data format.

SCHEMES = {
    ('strength',    'beginner'):     [('3', '5'),      ('3', '4–5'),  ('4', '3–5')],
    ('strength',    'intermediate'): [('4', '5'),      ('4', '3–5'),  ('4', '2–4')],
    ('strength',    'advanced'):     [('5', '5'),      ('5', '3–4'),  ('5', '2–3')],
    ('hypertrophy', 'beginner'):     [('3', '10–12'),  ('3', '8–10'), ('4', '8–10')],
    ('hypertrophy', 'intermediate'): [('3', '8–12'),   ('4', '8–10'), ('4', '6–8')],
    ('hypertrophy', 'advanced'):     [('4', '8–12'),   ('5', '6–10'), ('5', '6–8')],
    ('weight_loss', 'beginner'):     [('2', '12–15'),  ('3', '12–15'),('3', '15')],
    ('weight_loss', 'intermediate'): [('3', '12–15'),  ('4', '12–15'),('4', '15')],
    ('weight_loss', 'advanced'):     [('4', '12–15'),  ('4', '15'),   ('4', '15–20')],
    ('general',     'beginner'):     [('2', '10–12'),  ('3', '8–12'), ('3', '8–10')],
    ('general',     'intermediate'): [('3', '8–12'),   ('3', '10–12'),('4', '8–12')],
    ('general',     'advanced'):     [('4', '8–12'),   ('4', '10–12'),('5', '8–10')],
}

# ── Weight calculation parameters ─────────────────────────────────────────────
# (bw_ratio, weekly_inc_lbs, min_weight_lbs, is_bodyweight)
#   bw_ratio      — starting weight as fraction of body weight at beginner level
#   weekly_inc_lbs — weight added per week at full (age <30) rate
#   min_weight_lbs — absolute floor for starting weight (0 = no floor)
#   is_bodyweight  — True → leave Weight columns blank

EXERCISE_PARAMS = {
    # ── Compound barbell ────────────────────────────────────────────────────────
    'Barbell Bench Press':             (0.55, 2.5,  45,  False),
    'Barbell Back Squat':              (0.75, 5.0,  45,  False),
    'Romanian Deadlift':               (0.65, 2.5,  45,  False),
    'Romanian Deadlift (Dumbbell)':    (0.20, 2.5,  15,  False),
    'Overhead Press':                  (0.30, 2.5,  35,  False),
    'Barbell Row':                     (0.45, 2.5,  45,  False),
    # ── Leg machines ────────────────────────────────────────────────────────────
    'Leg Press':                       (1.00, 5.0,  45,  False),
    'Leg Extension':                   (0.30, 2.5,  20,  False),
    'Lying Leg Curl':                  (0.25, 2.5,  20,  False),
    # ── Cable machines ──────────────────────────────────────────────────────────
    'Lat Pulldown':                    (0.45, 2.5,  30,  False),
    'Neutral Grip Lat Pulldown':       (0.45, 2.5,  30,  False),
    'Seated Cable Row':                (0.35, 2.5,  25,  False),
    'Cable Chest Fly':                 (0.12, 2.5,  10,  False),
    'Incline Cable Fly':               (0.12, 2.5,  10,  False),
    'Cable Lateral Raise':             (0.04, 1.25,  5,  False),
    'Cable Tricep Pushdown (Rope)':    (0.18, 2.5,  15,  False),
    'Tricep Pushdown (Rope)':          (0.18, 2.5,  15,  False),
    'Overhead Tricep Extension':       (0.15, 2.5,  15,  False),
    'Cable Crunch':                    (0.22, 2.5,  20,  False),
    'Face Pull':                       (0.12, 1.25, 10,  False),
    'Chest-Supported Row':             (0.25, 2.5,  15,  False),
    # ── Machine ─────────────────────────────────────────────────────────────────
    'Machine Chest Press':             (0.40, 2.5,  25,  False),
    # ── Dumbbell compound ───────────────────────────────────────────────────────
    'Goblet Squat':                    (0.18, 2.5,  15,  False),
    'Dumbbell Bench Press':            (0.22, 2.5,  15,  False),
    'Incline Dumbbell Press':          (0.18, 2.5,  15,  False),
    'Dumbbell Shoulder Press':         (0.12, 2.5,  10,  False),
    'Seated Dumbbell Shoulder Press':  (0.12, 2.5,  10,  False),
    'Arnold Press':                    (0.10, 1.25, 10,  False),
    'Bulgarian Split Squat (Dumbbell)':(0.12, 2.5,   0,  False),
    'Bulgarian Split Squat':           (0.12, 2.5,   0,  False),
    # ── Dumbbell isolation ──────────────────────────────────────────────────────
    'Lateral Raise':                   (0.04, 1.25,  5,  False),
    'Hammer Curl':                     (0.08, 1.25,  8,  False),
    'Dumbbell Curl':                   (0.08, 1.25,  8,  False),
    'Alternating Dumbbell Curl':       (0.08, 1.25,  8,  False),
    'Rear Delt Fly':                   (0.04, 1.25,  5,  False),
    'Skull Crusher':                   (0.10, 1.25, 10,  False),
    # ── Lunge / split squat ─────────────────────────────────────────────────────
    'Walking Lunge':                   (0.08, 2.5,   0,  False),
    'Walking Lunges':                  (0.08, 2.5,   0,  False),
    'Single-Leg Romanian Deadlift':    (0.12, 2.5,   8,  False),
    # ── Calf ────────────────────────────────────────────────────────────────────
    'Standing Calf Raise':             (0.50, 2.5,  25,  False),
    # ── Bodyweight / core ────────────────────────────────────────────────────────
    'Plank':                           (0,    0,     0,  True),
    'Dead Bug':                        (0,    0,     0,  True),
    'Bicycle Crunch':                  (0,    0,     0,  True),
    'Ab Wheel Rollout':                (0,    0,     0,  True),
}

# Level multiplier applied to the starting weight
LEVEL_MULT = {'beginner': 1.0, 'intermediate': 1.6, 'advanced': 2.2}

def get_age_factor(age: int) -> float:
    """Weekly progression rate scales down with age."""
    if age == 0 or age < 30:
        return 1.0
    if age < 40:
        return 0.9
    if age < 50:
        return 0.8
    return 0.7

def calc_weight(exercise: str, body_weight_lbs: float, level: str, week_num: int, age: int):
    """
    Returns (weight_lbs_str, weight_kg_str) for the given exercise / week.
    Returns ('', '') if body weight is unknown or the exercise is bodyweight-only.
    """
    ep = EXERCISE_PARAMS.get(exercise)
    if ep is None or ep[3]:                 # unknown or bodyweight exercise
        return ('', '')
    if body_weight_lbs <= 0:
        return ('', '')

    bw_ratio, weekly_inc, min_w, _ = ep
    lv_mult  = LEVEL_MULT.get(level, 1.0)
    af       = get_age_factor(age)

    start  = max(body_weight_lbs * bw_ratio * lv_mult, float(min_w))
    weight = start + (week_num - 1) * weekly_inc * af

    # Round to nearest 2.5 lbs and enforce floor
    weight = round(weight / 2.5) * 2.5
    if min_w > 0:
        weight = max(weight, float(min_w))

    weight_kg = weight / 2.20462
    return (f'{weight:.1f}', f'{weight_kg:.1f}')

# ── YouTube tutorial links ─────────────────────────────────────────────────────

YT_LINKS = {
    'Barbell Bench Press':             'https://www.youtube.com/watch?v=vcBig73ojpE',
    'Barbell Back Squat':              'https://www.youtube.com/watch?v=bEv6CCg2BC8',
    'Romanian Deadlift':               'https://www.youtube.com/watch?v=Q5vwsJFwhyg',
    'Romanian Deadlift (Dumbbell)':    'https://www.youtube.com/watch?v=Q5vwsJFwhyg',
    'Overhead Press':                  'https://www.youtube.com/watch?v=d2uus7QUt4c',
    'Barbell Row':                     'https://www.youtube.com/watch?v=rqTOAM8WoeM',
    'Leg Press':                       'https://www.youtube.com/watch?v=K5n2vg3oZa4',
    'Leg Extension':                   'https://www.youtube.com/watch?v=2lvdnQg04PM',
    'Lying Leg Curl':                  'https://www.youtube.com/watch?v=3gZm9wGTsEo',
    'Lat Pulldown':                    'https://www.youtube.com/watch?v=O94yEoGXtBY',
    'Neutral Grip Lat Pulldown':       'https://www.youtube.com/watch?v=O94yEoGXtBY',
    'Seated Cable Row':                'https://www.youtube.com/watch?v=OeLb503NZHk',
    'Cable Chest Fly':                 'https://www.youtube.com/watch?v=dvUwjPLTP8k',
    'Incline Cable Fly':               'https://www.youtube.com/watch?v=ULNOSXDVGsk',
    'Cable Lateral Raise':             'https://www.youtube.com/watch?v=qitQHqNZbeM',
    'Cable Tricep Pushdown (Rope)':    'https://www.youtube.com/watch?v=qHDrQglWgS4',
    'Tricep Pushdown (Rope)':          'https://www.youtube.com/watch?v=qHDrQglWgS4',
    'Overhead Tricep Extension':       'https://www.youtube.com/watch?v=GzmlxvSFE7A',
    'Cable Crunch':                    'https://www.youtube.com/watch?v=AV5PmZJIrrw',
    'Face Pull':                       'https://www.youtube.com/watch?v=ljgqer1ZpXg',
    'Chest-Supported Row':             'https://www.youtube.com/watch?v=SzSbQwRbe5U',
    'Machine Chest Press':             'https://www.youtube.com/watch?v=pLofEAcfsO8',
    'Goblet Squat':                    'https://www.youtube.com/watch?v=k_EhLGvM8TQ',
    'Dumbbell Bench Press':            'https://www.youtube.com/watch?v=VmB1G1K7v94',
    'Incline Dumbbell Press':          'https://www.youtube.com/watch?v=awEEyL5zGvU',
    'Dumbbell Shoulder Press':         'https://www.youtube.com/watch?v=qEwKCR5JCog',
    'Seated Dumbbell Shoulder Press':  'https://www.youtube.com/watch?v=qEwKCR5JCog',
    'Arnold Press':                    'https://www.youtube.com/watch?v=ris9tKqMwgU',
    'Bulgarian Split Squat (Dumbbell)':'https://www.youtube.com/watch?v=VPhhE6bBzZE',
    'Bulgarian Split Squat':           'https://www.youtube.com/watch?v=VPhhE6bBzZE',
    'Lateral Raise':                   'https://www.youtube.com/watch?v=3VcKaXpzqRo',
    'Hammer Curl':                     'https://www.youtube.com/watch?v=zC3nLlEvin4',
    'Dumbbell Curl':                   'https://www.youtube.com/watch?v=Jfp4b5Olc7A',
    'Alternating Dumbbell Curl':       'https://www.youtube.com/watch?v=Jfp4b5Olc7A',
    'Rear Delt Fly':                   'https://www.youtube.com/watch?v=jI1wwrBjsYI',
    'Skull Crusher':                   'https://www.youtube.com/watch?v=RavQHfFxbdA',
    'Walking Lunge':                   'https://www.youtube.com/watch?v=_DLIS8SySzs',
    'Walking Lunges':                  'https://www.youtube.com/watch?v=_DLIS8SySzs',
    'Single-Leg Romanian Deadlift':    'https://www.youtube.com/watch?v=Zfr6wizR8rs',
    'Standing Calf Raise':             'https://www.youtube.com/watch?v=k67UjgvJdEk',
    'Plank':                           'https://www.youtube.com/watch?v=mwlp75MS6Rg',
    'Dead Bug':                        'https://www.youtube.com/watch?v=bxn9FBrt4-A',
    'Bicycle Crunch':                  'https://www.youtube.com/watch?v=tCSEga7I1l8',
    'Ab Wheel Rollout':                'https://www.youtube.com/watch?v=NbudTqiwguk',
}

# ── Date generation ───────────────────────────────────────────────────────────

def week_monday(d: date) -> date:
    """Return the Monday of the week containing date d."""
    return d - timedelta(days=d.weekday())

def generate_rows(goal, level, days_pw, start_date, weight_lbs, age, num_weeks=12):
    program      = PROGRAMS[days_pw]
    day_defs     = program['days']
    offsets      = program['offsets']
    scheme_key   = (goal, level)
    phases       = SCHEMES.get(scheme_key, SCHEMES[('general', 'beginner')])

    # Anchor to the Monday of the week that contains start_date.
    # Any dates before start_date in the first week are simply skipped so the
    # plan begins cleanly on or after the requested start.
    first_monday = week_monday(start_date)

    rows = []
    for week_num in range(1, num_weeks + 1):
        phase          = 0 if week_num <= 4 else (1 if week_num <= 8 else 2)
        sets_str, reps = phases[phase]
        target         = f'{sets_str} × {reps}'   # e.g. "3 × 8–10"

        for day_idx, (day_type, exercises) in enumerate(day_defs):
            workout_date = first_monday + timedelta(days=(week_num - 1) * 7 + offsets[day_idx])
            if workout_date < start_date:
                continue  # skip days before the requested start in week 1

            day_label = f'Week {week_num} – Day {day_idx + 1} – {day_type}'

            for exercise in exercises:
                w_lbs, w_kg = calc_weight(exercise, weight_lbs, level, week_num, age)
                rows.append({
                    'Date':                 '',   # user fills in when doing the workout
                    'Done':                 '',
                    'Day':                  day_label,
                    'Exercise':             exercise,
                    'YT Video Link':        YT_LINKS.get(exercise, ''),
                    'Target Sets/Reps':     target,
                    'Weight (lbs)':         w_lbs,
                    'Weight (kg)':          w_kg,
                    'Set 1': '', 'Set 2': '', 'Set 3': '', 'Set 4': '',
                    'Total Reps':           '',
                    'Total Volume (lbs)':   '',
                    'My Notes':             '',
                })
    return rows

# ── Write to sheet ────────────────────────────────────────────────────────────

FALLBACK_HEADERS = [
    'Date', 'Done', 'Day', 'Exercise',
    'YT Video Link', 'Target Sets/Reps',
    'Weight (lbs)', 'Weight (kg)',
    'Set 1', 'Set 2', 'Set 3', 'Set 4',
    'Total Reps', 'Total Volume (lbs)', 'My Notes',
]

try:
    ws = wb.worksheet('Week')
except Exception as e:
    print(json.dumps({"error": f"Could not find 'Week' worksheet: {str(e)}"}))
    sys.exit(1)

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read worksheet: {str(e)}"}))
    sys.exit(1)

headers = all_values[0] if all_values else FALLBACK_HEADERS

# Generate workout rows
workout_rows = generate_rows(goal, level, days_pw, start_date, weight_lbs, age)

if not workout_rows:
    print(json.dumps({"error": "No workout rows generated — check start_date"}))
    sys.exit(1)

# Build 2D array aligned to actual sheet headers
output_rows = []
for row_dict in workout_rows:
    output_rows.append([row_dict.get(h.strip(), '') for h in headers])

# ── Build session groups for alternating color formatting ─────────────────────
# Count how many consecutive rows belong to each workout session (same Day label).
session_row_counts = []
current_day = None
current_count = 0
for row_dict in workout_rows:
    if row_dict['Day'] != current_day:
        if current_day is not None:
            session_row_counts.append(current_count)
        current_day = row_dict['Day']
        current_count = 1
    else:
        current_count += 1
if current_day is not None:
    session_row_counts.append(current_count)

# Row in the sheet where new data will begin (1-indexed).
# all_values was read above; its length = number of existing rows (incl. header).
data_start_row = len(all_values) + 1  # e.g. 2 if only header exists

# Append after any existing data (so existing entries are preserved)
try:
    ws.append_rows(
        output_rows,
        value_input_option='USER_ENTERED',
        insert_data_option='OVERWRITE',
        table_range='A1',
    )
except Exception as e:
    print(json.dumps({"error": f"Could not write rows: {str(e)}"}))
    sys.exit(1)

# ── Apply alternating background colors per workout day ───────────────────────
# Alternates between light blue and light amber so each session is visually distinct.
SESSION_COLORS = [
    {'red': 0.898, 'green': 0.937, 'blue': 0.992},   # #E5EFFD — light blue
    {'red': 1.000, 'green': 0.976, 'blue': 0.882},   # #FFF9E1 — light amber
]

col_count = len(headers)
format_requests = []
current_row = data_start_row
for i, count in enumerate(session_row_counts):
    format_requests.append({
        'repeatCell': {
            'range': {
                'sheetId':          ws.id,
                'startRowIndex':    current_row - 1,          # 0-indexed
                'endRowIndex':      current_row - 1 + count,  # exclusive
                'startColumnIndex': 0,
                'endColumnIndex':   col_count,
            },
            'cell': {
                'userEnteredFormat': {
                    'backgroundColor': SESSION_COLORS[i % 2],
                }
            },
            'fields': 'userEnteredFormat.backgroundColor',
        }
    })
    current_row += count

if format_requests:
    try:
        wb.batch_update({'requests': format_requests})
    except Exception:
        pass  # color formatting is best-effort; don't fail the whole operation

total_sessions = days_pw * 12
print(json.dumps({
    "ok":          True,
    "rows_written": len(output_rows),
    "sessions":    total_sessions,
    "weeks":       12,
    "goal":        goal,
    "level":       level,
    "days":        days_pw,
    "weight_lbs":  weight_lbs,
    "age":         age,
}))
