<?php
    error_reporting(0);

    

    // For Live
    $user_name = $_REQUEST['bggUsername'];
    $gameId = $_REQUEST['bggGameId'];
    $spreadsheetId = $_REQUEST['Id'];

    // Sheet To Store
    $statsJSONFile = "../sheets/" . $spreadsheetId . "/stats.json";

    // BGG Endpoint URLs
    $collectionURL = 'https://boardgamegeek.com/xmlapi/collection/';
    $gameURL = 'https://boardgamegeek.com/xmlapi/boardgame/';

    // BGG Bearer token 
    $bgg_bearer_token = 'a4e2e2f3-a6fa-4eea-83b3-503508dfe06e';

    if(!isset($_REQUEST['bggUsername']) || empty($_REQUEST['bggUsername'])) {
        echo json_encode(['status' => 404, 'error' => "Username is missing. Please add valid Username to get the data."]); exit;
    }

    // URL To Call BGG user collection
	$url = $collectionURL.$user_name .'?own=1';

    $get_user_name = explode('/', $url);
    $user_name = end($get_user_name);
    $user_data = ['user_name' => $user_name ];

    $crl = curl_init();
    curl_setopt($crl, CURLOPT_URL, $url);
    curl_setopt($crl, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);

    // Bearer Token [NEW API Changes]
    curl_setopt($crl, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $bgg_bearer_token
    ));

    $response = curl_exec($crl);
    if(!$response) {
        die('Error: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch));
    } 

    curl_close($crl);
    $responsearray = json_decode(json_encode((array)simplexml_load_string($response)),true);

    if(count($responsearray) == 1) {
        echo json_encode($responsearray); exit;
    }

    if(isset($responsearray['error'])) {
        echo json_encode(['status' => 404, 'error' => $responsearray['error']['message']]); exit;
    }

    // Storing Game Ids
    $objectsIds = [];
    $boardgameBasicData = [];
    foreach($responsearray['item'] as $index=>$value) {
        if($value['@attributes']['objectid'] == $gameId) {
            $objectsIds[] = $value['@attributes']['objectid'];
            $boardgameBasicData[$value['@attributes']['objectid']]['name'] =  $value['name'];
            $boardgameBasicData[$value['@attributes']['objectid']]['rating'] = '';
            $boardgameBasicData[$value['@attributes']['objectid']]['status'] =  $value['status'];
            if(isset($value['stats']['rating'])){
                $boardgameBasicData[$value['@attributes']['objectid']]['rating'] =  ($value['stats']['rating']['average']['@attributes']['value']);
            }
        }
    }

    $responseData = [];
    if(!empty($objectsIds)):
        $gameIds = array_chunk($objectsIds, 20);
        foreach($gameIds as $indexList=>$valueList) {

            // URL to call BGG game details
            $url = $gameURL . implode(',',$gameIds[$indexList]).'?';

            $crl = curl_init();
            curl_setopt($crl, CURLOPT_URL, $url);
            curl_setopt($crl, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
            
            // Bearer Token [NEW API Changes]
            curl_setopt($crl, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $bgg_bearer_token
            ));

            $response = curl_exec($crl);
            if(!$response) {
                die('Error: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch));
            }

            curl_close($crl);

            $boardgames = json_decode(json_encode((array)simplexml_load_string($response)),true);
            array_push($responseData, $boardgames);  
        }

        // Store the game details to respective params
        $boardgames['boardgame'] = $responseData;
        $boardgames['status'] = 200;
        $boardgames['boardgameBasicData'] = $boardgameBasicData;

        // Save data to stats.json
        $output = file_put_contents($statsJSONFile, json_encode($boardgames));

        // Returns the bgg game data back
        echo json_encode($boardgames); exit;
    endif;

    function pr($array = []){
        echo '<pre>'; print_r($array); echo '</pre>';
    }
?>