# START

# Packages Used
import gspread
import sys, os
import json
from pathlib import Path 


# Credentials [Keys etc]
credFileName = "credentials.json"

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

# Getting the date from the mentioned sheet name
mSelectedWorkSheet = mGoogleSheet.worksheet(sheetName)

# Creating .JSON files
if(sheetName == "Settings"):
    path = "../sheets/" + mGoogleSheetId + "/settings.json"
elif (sheetName == "Install"):
    path = "../sheets/" + mGoogleSheetId + "/install.json"
else:
    #path = "../sheets/" + mGoogleSheetId + "/steps_" + sheetName.lower() + ".json"
    path = "../sheets/" + mGoogleSheetId + "/" + sheetName.lower() + ".json"

# Check for file exists (If not creates one)
if not os.path.exists(os.path.dirname(path)):
    os.mkdirs(os.path.dirname(path))

# Writing the spreadsheet data to the .JSON files
with open(path, 'w', encoding='utf8') as json_file:
    json.dump(mSelectedWorkSheet.get_all_records(), json_file, ensure_ascii=False)
    print('JSON SAVED')

# DONE