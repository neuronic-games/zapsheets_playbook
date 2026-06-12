<?php
    error_reporting(0);

    $user_name     = $_REQUEST['bggUsername'];
    $gameId        = $_REQUEST['bggGameId'];
    $spreadsheetId = $_REQUEST['Id'];

    if (!isset($user_name) || empty($user_name)) {
        echo json_encode(['status' => 404, 'error' => "Username is missing."]); exit;
    }

    $statsJSONFile = "../sheets/" . $spreadsheetId . "/stats.json";

    // BGG XML API v2
    $collectionURL = 'https://boardgamegeek.com/xmlapi2/collection';
    $gameURL       = 'https://boardgamegeek.com/xmlapi2/thing';

    // BGG Bearer token
    $bgg_bearer_token = 'a4e2e2f3-a6fa-4eea-83b3-503508dfe06e';

    //---------------------------------------------------------------------------
    // Helper: single curl GET with timeouts
    //---------------------------------------------------------------------------
    function bgg_get($url, $bearer_token) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Zapsheets/1.0)');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $bearer_token,
            'Accept: application/xml',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $body];
    }

    //---------------------------------------------------------------------------
    // Normalize a v2 <items> chunk to the v1 boardgame structure the JS expects:
    //   data.boardgame[0].boardgame['@attributes']['objectid']
    //   data.boardgame[0].boardgame.yearpublished  (plain string)
    //   data.boardgame[0].boardgame.image
    //   data.boardgame[0].boardgame.minplayers / maxplayers
    //   data.boardgame[0].boardgame.minplaytime / maxplaytime
    //   data.boardgame[0].boardgame.age
    //   data.boardgame[0].boardgame.description
    //   data.boardgame[0].boardgame.boardgamemechanic   (string or array)
    //   data.boardgame[0].boardgame.boardgamedesigner   (string or array)
    //   data.boardgame[0].boardgame.boardgameartist     (string or array)
    //   data.boardgame[0].boardgame.boardgamepublisher  (string or array)
    //   data.boardgame[0].boardgame.boardgamecategory   (string or array)
    //---------------------------------------------------------------------------
    function normalize_to_v1($v2_chunk) {
        $item = $v2_chunk['item'] ?? [];

        // If multiple items were returned, take the first
        if (!isset($item['@attributes'])) {
            $item = reset($item);
        }

        $id = $item['@attributes']['id'] ?? '';

        // v2 scalar fields are wrapped: <yearpublished value="2024"/>
        // → PHP array: ['@attributes' => ['value' => '2024']]
        function attr_val($node) {
            if (is_array($node)) return $node['@attributes']['value'] ?? '';
            return (string)$node;
        }

        // v2 links: flat array of <link type="boardgamemechanic" value="..."/>
        $mechanics  = [];
        $designers  = [];
        $artists    = [];
        $publishers = [];
        $categories = [];
        $links = $item['link'] ?? [];
        if (isset($links['@attributes'])) $links = [$links]; // single link
        foreach ($links as $link) {
            $type  = $link['@attributes']['type']  ?? '';
            $value = $link['@attributes']['value'] ?? '';
            $lid   = $link['@attributes']['id']    ?? '';
            if ($type === 'boardgamemechanic')  $mechanics[]  = $value;
            if ($type === 'boardgamedesigner')  $designers[]  = ['name' => $value, 'id' => $lid];
            if ($type === 'boardgameartist')    $artists[]    = ['name' => $value, 'id' => $lid];
            if ($type === 'boardgamepublisher') $publishers[] = $value;
            if ($type === 'boardgamecategory')  $categories[] = $value;
        }

        $boardgame = [
            '@attributes'        => ['objectid' => $id],
            'yearpublished'      => attr_val($item['yearpublished']  ?? ''),
            'image'              => trim($item['image'] ?? ''),
            'minplayers'         => attr_val($item['minplayers']     ?? ''),
            'maxplayers'         => attr_val($item['maxplayers']     ?? ''),
            'minplaytime'        => attr_val($item['minplaytime']    ?? ''),
            'maxplaytime'        => attr_val($item['maxplaytime']    ?? ''),
            'age'                => attr_val($item['minage']         ?? ''), // v2 uses minage
            'description'        => trim($item['description']        ?? ''),
            'boardgamemechanic'  => count($mechanics)  === 1 ? $mechanics[0]  : $mechanics,
            'boardgamedesigner'  => $designers,   // always array of {name, id}
            'boardgameartist'    => $artists,      // always array of {name, id}
            'boardgamepublisher' => count($publishers) === 1 ? $publishers[0] : $publishers,
            'boardgamecategory'  => count($categories) === 1 ? $categories[0] : $categories,
        ];

        return ['boardgame' => $boardgame];
    }

    //---------------------------------------------------------------------------
    // Request 1: user collection — single attempt, return 202 if not ready yet
    //---------------------------------------------------------------------------
    $collURL = $collectionURL . '?username=' . urlencode($user_name) . '&own=1&stats=1';
    list($code, $body) = bgg_get($collURL, $bgg_bearer_token);

    if ($code === 202) {
        echo json_encode(['status' => 202, 'error' => 'BGG collection is being prepared. Please wait...']);
        exit;
    }

    if ($code === 401) {
        echo json_encode(['status' => 401, 'error' => 'BGG returned 401 Unauthorized. Check that the BGG username "' . $user_name . '" is correct and the collection is set to Public on boardgamegeek.com.']);
        exit;
    }

    if ($code !== 200 || empty($body)) {
        echo json_encode(['status' => $code, 'error' => 'BGG collection request failed (HTTP ' . $code . ').']);
        exit;
    }

    $responsearray = json_decode(json_encode((array)simplexml_load_string($body)), true);

    if (isset($responsearray['error'])) {
        echo json_encode(['status' => 404, 'error' => $responsearray['error']['message']]); exit;
    }

    //---------------------------------------------------------------------------
    // Find the requested game in the collection
    //---------------------------------------------------------------------------
    $objectsIds         = [];
    $boardgameBasicData = [];

    $items = isset($responsearray['item']) ? $responsearray['item'] : [];
    if (isset($items['@attributes'])) $items = [$items]; // single-item collection

    foreach ($items as $value) {
        if (isset($value['@attributes']['objectid']) && $value['@attributes']['objectid'] == $gameId) {
            $oid = $value['@attributes']['objectid'];
            $objectsIds[] = $oid;

            // name in v2 collection: <name sortindex="1">Game Name</name>
            // simplexml gives ['@attributes'=>['sortindex'=>'1'], '0'=>'Game Name']
            $rawName = $value['name'] ?? '';
            $boardgameBasicData[$oid]['name'] = is_array($rawName)
                ? ($rawName[0] ?? reset($rawName))
                : (string)$rawName;

            $boardgameBasicData[$oid]['rating'] = '';
            $boardgameBasicData[$oid]['status'] = $value['status'] ?? '';
            if (isset($value['stats']['rating']['average']['@attributes']['value'])) {
                $boardgameBasicData[$oid]['rating'] = $value['stats']['rating']['average']['@attributes']['value'];
            }
        }
    }

    if (empty($objectsIds)) {
        echo json_encode(['status' => 404, 'error' => 'Game ID ' . $gameId . ' not found in collection for user "' . $user_name . '". Make sure the game is marked as Owned in your BGG collection.']);
        exit;
    }

    //---------------------------------------------------------------------------
    // Request 2: game details — normalize v2 response to v1 shape for the JS
    //---------------------------------------------------------------------------
    $responseData = [];
    foreach (array_chunk($objectsIds, 20) as $chunk) {
        $gameDetailURL = $gameURL . '?id=' . implode(',', $chunk) . '&stats=1';
        list($code, $body) = bgg_get($gameDetailURL, $bgg_bearer_token);

        if ($code !== 200 || empty($body)) {
            echo json_encode(['status' => $code, 'error' => 'BGG game details request failed (HTTP ' . $code . ').']);
            exit;
        }

        $v2_chunk = json_decode(json_encode((array)simplexml_load_string($body)), true);
        array_push($responseData, normalize_to_v1($v2_chunk));
    }

    //---------------------------------------------------------------------------
    // Save and return (same shape as v1 so the JS needs no changes)
    //---------------------------------------------------------------------------
    $output_data = [
        'boardgame'          => $responseData,
        'status'             => 200,
        'boardgameBasicData' => $boardgameBasicData,
    ];

    file_put_contents($statsJSONFile, json_encode($output_data));
    echo json_encode($output_data);
    exit;

    function pr($array = []) {
        echo '<pre>'; print_r($array); echo '</pre>';
    }
?>
