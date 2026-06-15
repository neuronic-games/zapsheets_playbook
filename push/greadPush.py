import gspread
import sys, os, json
from datetime import datetime

# Credentials [Keys etc]
# Resolve credentials path relative to this script's location
credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

# Check credentials file exists before attempting to connect
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

mServiceAccount = gspread.service_account(filename=credFileName)
mGoogleSheetId = sys.argv[1].split('sheetname')[0]

#Open the sheet based on sheet id passed
mGoogleSheet = mServiceAccount.open_by_key(mGoogleSheetId)

# Checking if variable is None
if sys.argv[1].split('sheetname')[1] == "null":
    sheetName = mGoogleSheet.worksheets()[0].title
else:
    sheetName = sys.argv[1].split('sheetname')[1].split('dateString')[0]

# Case-insensitive worksheet lookup
all_worksheets = mGoogleSheet.worksheets()
mSelectedWorkSheet = next((ws for ws in all_worksheets if ws.title == sheetName), None)
if mSelectedWorkSheet is None:
    # Try case-insensitive match
    mSelectedWorkSheet = next((ws for ws in all_worksheets if ws.title.lower() == sheetName.lower()), None)
if mSelectedWorkSheet is None:
    print(json.dumps({"error": f"Worksheet '{sheetName}' not found. Available: {[ws.title for ws in all_worksheets]}"}))
    sys.exit(1)

# Storing "Version" cell Number
cellNumVersion = mSelectedWorkSheet.find('Version')
# Get machine postion from the google sheet
versionIndex = cellNumVersion.row

# Storing datetime for cell "PublishedOn"
cellNumPublishedOn = mSelectedWorkSheet.find('PublishedOn')
# Get machine postion from the google sheet
publishedOnIndex = cellNumPublishedOn.row
##############################################################
# New Date string from app local system time
dt_string = sys.argv[1].split('sheetname')[1].split('dateString')[1].replace('-', ' ')
##############################################################

# Converting Data to Required JSON
jsonObj = mSelectedWorkSheet.get_all_records()
for items in jsonObj: 
    if items['Name'] == 'Version':
        versionValue = int(items['Value']) + 1
        mSelectedWorkSheet.update_acell(('B' + str(versionIndex)), versionValue)
    if items['Name'] == 'PublishedOn':
        mSelectedWorkSheet.update_acell(('B' + str(publishedOnIndex)), dt_string)

# print back the value
print(versionValue)

