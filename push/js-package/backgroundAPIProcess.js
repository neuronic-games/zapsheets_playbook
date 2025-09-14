// Creating worker to do all process in the background
if (typeof(Worker) !== "undefined") {
    console.log('Yes! Web worker support!')

    // For test
    /* function timedCount() {
        postMessage('Data from worker');
    }
    timedCount(); */

    //console.log('---', navigator)
    let sheet_Id = '';
    let pollTime = '';
    let localDB = null;

    onmessage = function(event) {
        //console.log(event.data.poll, " in worker ")
        sheet_Id = event.data.sheetID
        pollTime = event.data.poll;
        cacheVersion = event.data.versionNum

        //console.log(event.data)
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////
    function fetchSheetDetailsPeriodically() {
        console.log(sheet_Id, " --- ", pollTime, '--- ')
        //return;
        //console.log(JSON.stringify({'jsonPath' : ('sheets/' + sheet_Id.toString() + '/version.json')}))
        const periodicTimer = setTimeout(function() {
            clearTimeout(periodicTimer)
            if(navigator.onLine == true) {
                console.log("INTERNET ACTIVE - ")
                // Get the app version

                /* const options = {
                    method: 'POST',
                    headers: {
                        "Content-Type": "application/json; charset=UTF-8"
                    },
                    body:JSON.stringify({'jsonPath' : ('sheets/' + sheet_Id.toString() + '/version.json')}), 
                }; */

                let jsonURL = {
                    jsonPath : 'sheets/' + sheet_Id.toString() + '/version.json'
                }

               /*  var formData = new FormData();
                formData.append('jsonPath', JSON.stringify(jsonURL)); */

                /* let sheet_vol {
                    jsonPath : ""
                } */

                //fetch('../get_version.php', formData)
                fetch('../get_version_cache.php', {
                    "method": "POST", 
                    "headers": {
                        "Content-Type": "application/json; charset=utf-8"
                    },
                    "body": JSON.stringify(jsonURL)})
                .then(response => {
                    // Check if the request was successful
                    //console.log(response, " ----- ")
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    // Parse the response as JSON
                    return response.text();
                    //console.log(response.json(), " VALUES");
                })
                .then(data => {
                    // Handle the JSON data
                    console.log(data, " data");
                    console.log(JSON.stringify(cacheVersion), " = Version = ", JSON.stringify(data))

                    //if(JSON.stringify(value) != JSON.stringify(response)){
                    if(parseFloat(cacheVersion).toFixed(1) != parseFloat(data).toFixed(1)) {
                        console.log('Different values')
                        //window.ldb.set('zapMap_VersionNum', response)
                        // update the cache value with the new one.
                        //window.ldb.set(sheet_Id.toString() + '_VersionNum', response)
                        cacheVersion = data;
                    } else {
                        console.log("same value")
                        //fetchSettingDataFromSpreadsheet()
                        return
                    }
                    
                    //return
                })
                .catch(error => {
                    // Handle any errors that occurred during the fetch
                    console.error('Fetch error:', error);
                });
               
                /* $.ajax({
                    url: 'get_version.php', 
                    type:'POST', 
                    data:{'jsonPath' : ('sheets/' + sheet_Id + '/version.json')}, 
                    cache: false, 
                    async: false,
                    success: function (response) {
                        console.log(response, " data")
                        // Check prev and new version
                        //window.ldb.get('zapMap_VersionNum', function (value) {
                        //ldb.get(sheet_Id.toString() + '_VersionNum', function (value) {
                            //////////////////////////////////////////////////////////////////////////////////////////////////////////////
                            console.log(JSON.stringify(value), " = Version = ", JSON.stringify(response))

                            //if(JSON.stringify(value) != JSON.stringify(response)){
                            if(parseFloat(value).toFixed(1) != parseFloat(response).toFixed(1)) {
                                console.log('Different values')
                                //window.ldb.set('zapMap_VersionNum', response)
                                ldb.set(sheet_Id.toString() + '_VersionNum', response)
                            } else {
                                console.log("same value")
                            }
                           setTimeout(function() {
                                fetchSheetDetailsPeriodically()
                            }, 100)
                           // return;

                            //if(JSON.stringify(value) != response) {
                            if(parseFloat(value).toFixed(1) != parseFloat(response).toFixed(1)) {
                                //console.log("ENTER HERE NEW")
                                //window.ldb.set('zapMap_VersionNum', response)
                                window.ldb.set(sheet_Id.toString() + '_VersionNum', response)
                                currentVersion = "New"
                                //return
                            } else {
                                currentVersion = "Same"
                            }

                            if(JSON.stringify(response) == '"null"') {
                                //console.log("ENTER HERE NEW 3")
                                currentVersion = "Same"
                            } else if(JSON.stringify(value) != JSON.stringify(response) || value == null){
                                //console.log("ENTER HERE NEW")
                                window.ldb.set('zapMap_VersionNum', response)
                                currentVersion = "New"
                                //return
                            } else if((JSON.stringify(value) == JSON.stringify(response)) || JSON.stringify(value) == null) {
                                //console.log("ENTER HERE NEW 2")
                                currentVersion = "Same"
                            }
                            

                                if(JSON.stringify(response) == '"null"') {
                                    console.log("ENTER HERE NEW 3")
                                    currentVersion = "Same"
                                }



                            //console.log(currentVersion, " >>>>>>>>>>>>>>>")
                            
                            fetchSheetDetailsPeriodically()
                            return


                            if(currentVersion == 'New') {
                                // Reset All Values
                                setTimeout(function() {
                                    // Change loadType here for refreshing the loaded content
                                    //window.ldb.set(sheet_Id.toString() + '_loadType', 'refresh')
                                    //window.location.reload()
                                }, 100)
                            } else {
                                fetchSheetDetailsPeriodically()
                                
                            }
                        //});
                    }
                }) */
                    fetchSheetDetailsPeriodically()
            } else {
                //console.log("NO INTERNET")
               /*  fetchSheetDetailsPeriodically() */
            }
        }, pollTime * 1000)
    }
    //////////////////////////////////////////////////////////////////////////////////////////////////////
    function fetchSettingDataFromSpreadsheet() {
        console.log('Sheet ID',  sheet_Id)
        /* $.ajax({ 
            //url: apiurl, 
            url: 'private_data.php', 
            type:'POST', 
            data:{'sheetId' : sheet_Id, 'sheet' : 'Settings'}, 
            cache: false, 
            async: false,
            success: function (response) {
                console.log(response, " --- " )
            }
        }); */
        let jsonURL = {
            sheetId : sheet_Id,
            sheet : 'Settings'
        }

       /*  var formData = new FormData();
        formData.append('jsonPath', JSON.stringify(jsonURL)); */

        /* let sheet_vol {
            jsonPath : ""
        } */

        //fetch('../get_version.php', formData)
        fetch('../private_data_cache.php', {
            "method": "POST", 
            "headers": {
                "Content-Type": "application/json; charset=utf-8"
            },
            "body": JSON.stringify(jsonURL)})
        .then(response => {
            // Check if the request was successful
            //console.log(response, " ----- ")
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            // Parse the response as JSON
            return response.text();
            //console.log(response.json(), " VALUES");
        })
        .then(data => {
            // Handle the JSON data
            console.log(data, " data");
        })
        .catch(error => {
            // Handle any errors that occurred during the fetch
            console.error('Fetch error:', error);
        });
    }
    //////////////////////////////////////////////////////////////////////////////////////////////////////


    //////////////////////////////////////////////////////////////////////////////////////////////////////
    // function to look for new update and fetch the data from spread sheet
    // Recursive
    setTimeout(function() {
        fetchSheetDetailsPeriodically()
    },2000)
    //////////////////////////////////////////////////////////////////////////////////////////////////////


} else {
    console.log('Sorry! No Web Worker support.')
}




  