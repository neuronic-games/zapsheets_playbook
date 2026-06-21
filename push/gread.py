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
    # Write an empty array so downstream JSON reads don't 404, and print nothing
    # so pushSheetUpdate.php skips the file_put_contents guard (empty trim check).
    # The JS error handler in getSheetLanguage will skip the sheet gracefully.
    available = [ws.title for ws in all_worksheets]
    sys.stderr.write(json.dumps({"error": f"Worksheet '{sheetName}' not found. Available: {available}"}) + "\n")
    sys.exit(1)

# Creating .JSON files
if(sheetName == "settings"):
    path = "../sheets/" + mGoogleSheetId + "/settings.json"
elif (sheetName == "install"):
    path = "../sheets/" + mGoogleSheetId + "/install.json"
else:
    path = "../sheets/" + mGoogleSheetId + "/" + sheetName.lower() + ".json"

# Check for file exists (If not creates one)
if not os.path.exists(os.path.dirname(path)):
    os.makedirs(os.path.dirname(path))

# Fetch records using get_all_values() so duplicate or empty headers don't crash us.
# get_all_records() throws GSpreadException when headers contain duplicates (e.g. blank columns).
try:
    all_values = mSelectedWorkSheet.get_all_values()
except Exception as e:
    err = json.dumps({"error": f"get_all_values failed for '{sheetName}': {type(e).__name__}: {str(e)}"})
    print(err)
    sys.exit(1)

try:
    if not all_values:
        records = []
    else:
        headers = all_values[0]
        records = []
        for row in all_values[1:]:
            # Skip rows that are entirely empty
            if not any(cell.strip() for cell in row):
                continue
            record = {}
            for i, header in enumerate(headers):
                if header.strip():          # skip blank-header columns
                    record[header.strip()] = row[i] if i < len(row) else ''
            records.append(record)
    json_data = json.dumps(records, ensure_ascii=False)
except Exception as e:
    err = json.dumps({"error": f"Record build failed for '{sheetName}': {type(e).__name__}: {str(e)}"})
    print(err)
    sys.exit(1)

# Write to the JSON file
try:
    with open(path, 'w', encoding='utf8') as json_file:
        json_file.write(json_data)
except Exception as e:
    err = json.dumps({"error": f"File write failed for '{path}': {type(e).__name__}: {str(e)}"})
    print(err)
    sys.exit(1)

# Print the JSON to stdout so PHP can capture and relay it
print(json_data)

# DONE