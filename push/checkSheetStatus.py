# START
import sys, os, json

try:
    import gspread
except ImportError as e:
    print(json.dumps({"exists": "no", "error": "gspread not installed: " + str(e)}))
    sys.exit(1)

try:
    # Resolve credentials path relative to this script's location
    credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

    if not os.path.exists(credFileName):
        print(json.dumps({"exists": "no", "error": "credentials.json not found at: " + credFileName}))
        sys.exit(1)

    # Read service account email from credentials for helpful error messages
    serviceEmail = ''
    try:
        with open(credFileName, 'r') as f:
            creds = json.load(f)
            serviceEmail = creds.get('client_email', '')
    except Exception:
        pass

    # Connect to Google Sheets
    try:
        mServiceAccount = gspread.service_account(filename=credFileName)
        mGoogleSheetId  = sys.argv[1].split('sheetname')[0]
        sheetName       = sys.argv[1].split('sheetname')[1]
        mGoogleSheet    = mServiceAccount.open_by_key(mGoogleSheetId)
    except gspread.exceptions.APIError as e:
        status = getattr(e, 'response', None)
        code   = status.status_code if status else 0
        if code == 403 or 'PERMISSION_DENIED' in str(e):
            msg = 'Permission denied. Share the spreadsheet with: ' + serviceEmail if serviceEmail else 'Permission denied. Share the spreadsheet with the service account in credentials.json.'
        elif code == 404:
            msg = 'Spreadsheet not found. Check the sheet ID is correct.'
        else:
            msg = 'Google API error (' + str(code) + '): ' + str(e)
        print(json.dumps({"exists": "no", "error": msg}))
        sys.exit(1)
    except PermissionError:
        msg = 'Permission denied. Share the spreadsheet with: ' + serviceEmail if serviceEmail else 'Permission denied. Share the spreadsheet with the service account in credentials.json.'
        print(json.dumps({"exists": "no", "error": msg}))
        sys.exit(1)

    all_worksheets = mGoogleSheet.worksheets()

    # Case-insensitive match
    matched = next((ws for ws in all_worksheets if ws.title.lower() == sheetName.lower()), None)

    if matched:
        print(json.dumps({"exists": "yes", "sheet": matched.title}))
    else:
        available = [ws.title for ws in all_worksheets]
        print(json.dumps({"exists": "no", "requested": sheetName, "available": available}))

except Exception as e:
    print(json.dumps({"exists": "no", "error": type(e).__name__ + ': ' + str(e)}))
    sys.exit(1)

# DONE
