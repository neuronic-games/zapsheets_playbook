#!/usr/bin/env python3
"""
genPdfReport.py — generate a DevBoard report PDF using reportlab.

Arg: "{app_root}|{base64_json}"
JSON keys:
  game       — game name
  tagline    — game tagline
  imgUrl     — game hero image URL (may be empty)
  today      — formatted date string
  sessions   — list of session objects {testnum, date, location, length, testers, obs:[{obs,sol}]}
  tmByPerson — list of {person, mins, cost}
  tmTotal    — {mins, cost}
  outPath    — absolute path to write the PDF to

Returns: {"ok": true} or {"error": "..."}
"""

import sys, os, json, base64, re, io, urllib.request, urllib.error

try:
    from reportlab.lib.pagesizes import letter
    from reportlab.platypus import (
        SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
        HRFlowable, Image as RLImage, KeepTogether
    )
    from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
    from reportlab.lib.units import inch
    from reportlab.lib.colors import HexColor, white
    from reportlab.lib.enums import TA_LEFT
except ImportError:
    print(json.dumps({"error": "reportlab not installed — run: pip install reportlab --break-system-packages"}))
    sys.exit(1)

# ── Argument parsing ──────────────────────────────────────────────────────────
arg = sys.argv[1] if len(sys.argv) > 1 else ''
if '|' not in arg:
    print(json.dumps({"error": "invalid argument"}))
    sys.exit(1)

pipe = arg.index('|')
app_root = arg[:pipe]
data     = json.loads(base64.b64decode(arg[pipe + 1:]).decode('utf-8'))

game_name   = data.get('game',   '')
tagline     = data.get('tagline','')
img_url     = data.get('imgUrl', '')
today       = data.get('today',  '')
sessions    = data.get('sessions',    [])
tm_by_person = data.get('tmByPerson', [])
tm_total    = data.get('tmTotal',    {})
out_path    = data.get('outPath', '')

if not out_path:
    print(json.dumps({"error": "outPath not provided"}))
    sys.exit(1)

# ── Colour palette ────────────────────────────────────────────────────────────
TEAL   = HexColor('#1a5f7a')
TEAL_L = HexColor('#e8f4f8')
GREY   = HexColor('#888888')
GREY_L = HexColor('#f5f8fb')
LINE   = HexColor('#d0e4ee')
AMBER  = HexColor('#a16207')
BLACK  = HexColor('#111111')

# ── Styles ────────────────────────────────────────────────────────────────────
SS = getSampleStyleSheet()

def _ps(name, parent='Normal', **kw):
    try:
        return SS[name]
    except KeyError:
        pass
    base = SS[parent]
    s = ParagraphStyle(name, parent=base, **kw)
    SS.add(s)
    return s

sTitle    = _ps('rTitle',    'Normal',  fontName='Helvetica-Bold',   fontSize=20, leading=24, textColor=BLACK, spaceAfter=3)
sTagline  = _ps('rTagline',  'Normal',  fontName='Helvetica-Oblique',fontSize=10, leading=13, textColor=GREY, spaceAfter=2)
sMeta     = _ps('rMeta',     'Normal',  fontName='Helvetica',         fontSize=8,  leading=11, textColor=GREY)
sSection  = _ps('rSection',  'Normal',  fontName='Helvetica-Bold',    fontSize=8,  leading=10, textColor=TEAL, spaceAfter=3, spaceBefore=14, letterSpacing=0.8)
sSession  = _ps('rSession',  'Normal',  fontName='Helvetica-Bold',    fontSize=10, leading=13, textColor=BLACK, spaceBefore=8, spaceAfter=1)
sTesters  = _ps('rTesters',  'Normal',  fontName='Helvetica-Oblique', fontSize=8.5, leading=11, textColor=GREY, spaceAfter=2)
sCell     = _ps('rCell',     'Normal',  fontName='Helvetica',         fontSize=9,  leading=12, textColor=BLACK)
sCellSol  = _ps('rCellSol',  'Normal',  fontName='Helvetica',         fontSize=9,  leading=12, textColor=HexColor('#444444'))
sNoData   = _ps('rNoData',   'Normal',  fontName='Helvetica-Oblique', fontSize=9,  leading=12, textColor=GREY)
sTMPerson = _ps('rTMPerson', 'Normal',  fontName='Helvetica',         fontSize=9,  leading=12, textColor=BLACK)
sTMBold   = _ps('rTMBold',   'Normal',  fontName='Helvetica-Bold',    fontSize=9,  leading=12, textColor=BLACK)

W, H = letter
MARGIN = 0.65 * inch
CONTENT_W = W - 2 * MARGIN

# ── Image helper ──────────────────────────────────────────────────────────────
def _fetch_image(url, max_w, max_h):
    """Download image and return an RLImage scaled to fit, or None on failure."""
    if not url:
        return None
    try:
        # Convert zapsheets.com/app/ URLs to local file paths
        local = None
        m = re.match(r'https?://[^/]+/app/(.+)', url)
        if m:
            local = os.path.join(app_root, m.group(1))
        if local and os.path.exists(local):
            data_bytes = open(local, 'rb').read()
        else:
            req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
            data_bytes = urllib.request.urlopen(req, timeout=8).read()
        img = RLImage(io.BytesIO(data_bytes))
        iw, ih = img.drawWidth, img.drawHeight
        if iw > max_w or ih > max_h:
            scale = min(max_w / iw, max_h / ih)
            img.drawWidth  = iw * scale
            img.drawHeight = ih * scale
        return img
    except Exception:
        return None

def _is_image_formula(s):
    return bool(re.match(r'^=IMAGE\s*\(', s or '', re.I))

def _extract_formula_url(s):
    m = re.match(r'^=IMAGE\s*\(\s*["\']([^"\']+)', s or '', re.I)
    return m.group(1) if m else ''

def _cell_content(text, style):
    """Return Paragraph or image flowable for a cell value."""
    if _is_image_formula(text):
        url = _extract_formula_url(text)
        img = _fetch_image(url, CONTENT_W / 2 - 8, 2.5 * inch)
        if img:
            return img
        return Paragraph('(image)', sNoData)
    return Paragraph(str(text or ''), style)

# ── Build document ────────────────────────────────────────────────────────────
story = []

# ── Header ────────────────────────────────────────────────────────────────────
hero_img = _fetch_image(img_url, 1.3 * inch, 1.3 * inch)

header_text = [
    Paragraph(game_name, sTitle),
]
if tagline:
    header_text.append(Paragraph(tagline, sTagline))
header_text.append(Paragraph('DevBoard Report  ·  ' + today, sMeta))

if hero_img:
    hero_img.drawWidth  = min(hero_img.drawWidth,  1.3 * inch)
    hero_img.drawHeight = min(hero_img.drawHeight, 1.3 * inch)
    hdr_table = Table(
        [[header_text, hero_img]],
        colWidths=[CONTENT_W - 1.4 * inch, 1.4 * inch],
        style=[
            ('VALIGN',  (0,0), (-1,-1), 'TOP'),
            ('ALIGN',   (1,0), (1,0),   'RIGHT'),
            ('LEFTPADDING',  (0,0), (-1,-1), 0),
            ('RIGHTPADDING', (0,0), (-1,-1), 0),
        ]
    )
    story.append(hdr_table)
else:
    for p in header_text:
        story.append(p)

story.append(Spacer(1, 6))
story.append(HRFlowable(width='100%', thickness=1.5, color=TEAL, spaceAfter=4))

# ── Sessions ──────────────────────────────────────────────────────────────────
story.append(Paragraph(f'SESSIONS ({len(sessions)})', sSection))

if not sessions:
    story.append(Paragraph('No sessions logged yet.', sNoData))
else:
    COL_W = (CONTENT_W - 2) / 2  # equal halves

    OBS_HEADER_STYLE = ParagraphStyle('ohdr', fontName='Helvetica-Bold', fontSize=7.5,
                                      textColor=TEAL, leading=10)
    SOL_HEADER_STYLE = ParagraphStyle('shdr', fontName='Helvetica-Bold', fontSize=7.5,
                                      textColor=GREY, leading=10)

    for s in sessions:
        # Session label row
        parts = [s.get('testnum','')]
        if s.get('date'):     parts.append(s['date'])
        if s.get('location'): parts.append(s['location'])
        if s.get('length'):   parts.append(s['length'])
        label_text = '  ·  '.join(parts)
        session_label = Paragraph(label_text, sSession)

        testers = s.get('testers', [])
        testers_p = Paragraph(', '.join(testers), sTesters) if testers else None

        obs_rows = s.get('obs', [])
        if obs_rows:
            table_data = [[Paragraph('Observation', OBS_HEADER_STYLE),
                           Paragraph('Note / Solution', SOL_HEADER_STYLE)]]
            for o in obs_rows:
                table_data.append([
                    _cell_content(o.get('obs', ''), sCell),
                    _cell_content(o.get('sol', ''), sCellSol),
                ])

            ts = TableStyle([
                ('BACKGROUND', (0,0), (-1,0), TEAL_L),
                ('ROWBACKGROUNDS', (0,1), (-1,-1), [white, GREY_L]),
                ('GRID',       (0,0), (-1,-1), 0.4, LINE),
                ('VALIGN',     (0,0), (-1,-1), 'TOP'),
                ('LEFTPADDING',  (0,0), (-1,-1), 5),
                ('RIGHTPADDING', (0,0), (-1,-1), 5),
                ('TOPPADDING',   (0,0), (-1,-1), 4),
                ('BOTTOMPADDING',(0,0), (-1,-1), 4),
            ])
            obs_table = Table(table_data, colWidths=[COL_W, COL_W], style=ts,
                              repeatRows=1, hAlign='LEFT')

            block = [session_label]
            if testers_p: block.append(testers_p)
            block.append(obs_table)
            story.append(KeepTogether(block))
        else:
            story.append(session_label)
            if testers_p: story.append(testers_p)

# ── T&M Summary ───────────────────────────────────────────────────────────────
story.append(Spacer(1, 4))
story.append(Paragraph('TIME & MATERIALS', sSection))

if not tm_by_person:
    story.append(Paragraph('No time or materials logged.', sNoData))
else:
    def fmt_time(mins):
        if not mins: return '—'
        h, m = divmod(int(mins), 60)
        if h and m: return f'{h}h {m}m'
        return f'{h}h' if h else f'{m}m'

    def fmt_cost(cost):
        return f'${float(cost):.2f}' if cost else '—'

    HDR = ParagraphStyle('tmhdr', fontName='Helvetica-Bold', fontSize=7.5, textColor=GREY, leading=10)
    tm_data = [[Paragraph('Person', HDR), Paragraph('Time', HDR), Paragraph('Materials', HDR)]]
    for row in tm_by_person:
        tm_data.append([
            Paragraph(str(row.get('person','')), sTMPerson),
            Paragraph(fmt_time(row.get('mins',0)), sTMPerson),
            Paragraph(fmt_cost(row.get('cost',0)), sTMPerson),
        ])
    if len(tm_by_person) > 1 and tm_total:
        tm_data.append([
            Paragraph('Total', sTMBold),
            Paragraph(fmt_time(tm_total.get('mins',0)), sTMBold),
            Paragraph(fmt_cost(tm_total.get('cost',0)), sTMBold),
        ])

    tm_ts = TableStyle([
        ('BACKGROUND',   (0,0), (-1,0), TEAL_L),
        ('ROWBACKGROUNDS',(0,1), (-1,-2), [white, GREY_L]),
        ('BACKGROUND',   (0,-1),(-1,-1), HexColor('#fef9c3')) if len(tm_by_person) > 1 else ('SPAN',(0,0),(0,0)),
        ('GRID',         (0,0), (-1,-1), 0.4, LINE),
        ('VALIGN',       (0,0), (-1,-1), 'MIDDLE'),
        ('LEFTPADDING',  (0,0), (-1,-1), 6),
        ('RIGHTPADDING', (0,0), (-1,-1), 6),
        ('TOPPADDING',   (0,0), (-1,-1), 4),
        ('BOTTOMPADDING',(0,0), (-1,-1), 4),
    ])
    TM_COLS = [CONTENT_W * 0.5, CONTENT_W * 0.25, CONTENT_W * 0.25]
    story.append(Table(tm_data, colWidths=TM_COLS, style=tm_ts, hAlign='LEFT'))

# ── Write PDF ─────────────────────────────────────────────────────────────────
try:
    doc = SimpleDocTemplate(
        out_path,
        pagesize=letter,
        leftMargin=MARGIN, rightMargin=MARGIN,
        topMargin=MARGIN,  bottomMargin=MARGIN,
        title=game_name + ' — DevBoard Report',
        author='DevBoard',
    )
    doc.build(story)
    print(json.dumps({"ok": True}))
except Exception as e:
    print(json.dumps({"error": str(e)}))
    sys.exit(1)
