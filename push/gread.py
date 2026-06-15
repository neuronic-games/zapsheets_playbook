# START

# Packages Used
import gspread
import sys, os
import json
from pathlib import Path 


# Credentials [Keys etc]
# Resolve credentials path relative to this script's location
credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

# Check credentials file exists before attempting to connect
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
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

# Case-insensitive worksheet lookup
all_worksheets = mGoogleSheet.worksheets()
mSelectedWorkSheet = next((ws for ws in all_worksheets if ws.title == sheetName), None)
if mSelectedWorkSheet is None:
    # Try case-insensitive match
    mSelectedWorkSheet = next((ws for ws in all_worksheets if ws.title.lower() == sheetName.lower()), None)
if mSelectedWorkSheet is None:
    print(json.dumps({"error": f"Worksheet '{sheetName}' not found. Available: {[ws.title for ws in all_worksheets]}"}))
    sys.exit(1)

# Creating .JSON files
if(sheetName == "settings"):
    path = "../sheets/" + mGoogleSheetId + "/settings.json"
elif (sheetName == "install"):
    path = "../sheets/" + mGoogleSheetId + "/install.json"
elif (sheetName.lower().startswith("game-")):
    path = "../sheets/" + mGoogleSheetId + "/game.json"
else:
    path = "../sheets/" + mGoogleSheetId + "/" + sheetName.lower() + ".json"

# Check for file exists (If not creates one)
if not os.path.exists(os.path.dirname(path)):
    os.makedirs(os.path.dirname(path))

# Fetch records once
records = mSelectedWorkSheet.get_all_records()
json_data = json.dumps(records, ensure_ascii=False)

# Write to the JSON file
with open(path, 'w', encoding='utf8') as json_file:
    json_file.write(json_data)

# Print the JSON to stdout so PHP can capture and relay it
print(json_data)

# DONE