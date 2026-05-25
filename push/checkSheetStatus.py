# START

# Packages Used
import gspread
import sys, os, json
from gspread.exceptions import WorksheetNotFound

# Credentials [Keys etc]
# Resolve credentials path relative to this script's location
credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

# Check credentials file exists before attempting to connect
if not os.path.exists(credFileName):
    print(json.dumps({"exists": "no", "error": "credentials.json not found"}))
    sys.exit(1)

# Accessing Google service account using credentials
mServiceAccount = gspread.service_account(filename=credFileName)
# Storing Google Sheet Id passed
mGoogleSheetId = sys.argv[1].split('sheetname')[0]

# Open the sheet based on sheet id passed
mGoogleSheet = mServiceAccount.open_by_key(mGoogleSheetId)

# Checking if variable is None
if sys.argv[1].split('sheetname')[1] == "null":                   
    sheetName = mGoogleSheet.worksheets()[0].title
else :
    sheetName = sys.argv[1].split('sheetname')[1]

for sheet in mGoogleSheet.worksheets():
        if(sheet.title.lower() == sheetName.lower()):
            sheetName = sheet.title
        #else:
            #print('{"exists": "no"}')

try:
    # Getting the date from the mentioned sheet name
    mGoogleSheet = mServiceAccount.open_by_key(mGoogleSheetId)
    mSelectedWorkSheet = mGoogleSheet.worksheet(sheetName)
    print('{"exists": "yes", "sheet": "' + sheetName + '"}')
except WorksheetNotFound:
    print('{"exists": "no"}')
    #print("--")

# DONE