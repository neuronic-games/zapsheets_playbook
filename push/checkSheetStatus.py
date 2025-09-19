# START

# Packages Used
import gspread
import sys, os
from gspread.exceptions import WorksheetNotFound

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

#print(mGoogleSheet.worksheets())
#sheetName = 'En'

for sheet in mGoogleSheet.worksheets():
        if(sheet.title.lower() == sheetName.lower()):
            #mSelectedWorkSheet = mGoogleSheet.worksheet(sheetName.lower())
            sheetName = sheet.title
        #else:
            #print('{"exists": "no"}')

try:
    # Getting the date from the mentioned sheet name
    mGoogleSheet = mServiceAccount.open_by_key(mGoogleSheetId)
    mSelectedWorkSheet = mGoogleSheet.worksheet(sheetName)
    print('{"exists": "yes", "sheet": "' + sheetName + '"}')
    #for sheet in mGoogleSheet.worksheets():
        #print(sheet.title.lower() == sheetName.lower())
        #if(sheet.title.lower() == sheetName.lower()):
            #print('AAAA')
            #mSelectedWorkSheet = mGoogleSheet.worksheet(sheetName.lower())
            #print('{"exists": "yes"}')
        #else:
            #print('{"exists": "no"}')

except WorksheetNotFound:
    print('{"exists": "no"}')
    #print("--")

# DONE