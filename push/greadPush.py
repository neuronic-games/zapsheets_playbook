import gspread
import sys
from datetime import datetime

# Credentials [Keys etc]
credFileName = "credentials.json"
mServiceAccount = gspread.service_account(filename=credFileName)
mGoogleSheetId = sys.argv[1].split('sheetname')[0]

#Open the sheet based on sheet id passed
mGoogleSheet = mServiceAccount.open_by_key(mGoogleSheetId)

#checking if variable is None
if sys.argv[1].split('sheetname')[1] == "null":                   
    sheetName = mGoogleSheet.worksheets()[0].title
else :
    sheetName = sys.argv[1].split('sheetname')[1].split('dateString')[0]

# Getting the date from the mentioned sheet name
mSelectedWorkSheet = mGoogleSheet.worksheet(sheetName)
#mSelectedWorkSheet = mGoogleSheet.worksheet('Settings')

# Converting Data to Required JSON
#print(mSelectedWorkSheet.get_all_records())
#listLength = len(mSelectedWorkSheet.get_all_records())

""" mSelectedWorkSheet.update(('B' + str(machinIndex)), host_name)
mSelectedWorkSheet.update(('C' + str(machinIndex)), host_ip) """

# Storing "Version" cell Number
cellNumVersion = mSelectedWorkSheet.find('Version')
# Get machine postion from the google sheet
versionIndex = cellNumVersion.row

# Storing datetime for cell "PublishedOn"
cellNumPublishedOn = mSelectedWorkSheet.find('PublishedOn')
# Get machine postion from the google sheet
publishedOnIndex = cellNumPublishedOn.row

##############################################################
# Previous
#now = datetime.now(LOCAL)
# dd/mm/YY H:M:S
#dt_string = now.strftime("%m/%d/%Y %H:%M:%S")
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
        #print(items['Value'])
        #print(versionValue)
        #break
    if items['Name'] == 'PublishedOn':
        mSelectedWorkSheet.update_acell(('B' + str(publishedOnIndex)), dt_string)
        #break

# print back the value
print(versionValue)

