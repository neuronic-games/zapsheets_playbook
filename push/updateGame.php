<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId       = trim($_POST['id']             ?? '');
$origName      = trim($_POST['orig_name']      ?? '');
$name          = trim($_POST['name']           ?? '');
$tagline       = trim($_POST['tagline']        ?? '');
$description   = trim($_POST['description']    ?? '');
$status        = trim($_POST['status']         ?? '');
$dateStarted   = trim($_POST['date_started']   ?? '');
$dateSigned    = trim($_POST['date_signed']    ?? '');
$datePublished = trim($_POST['date_published'] ?? '');
$designer1     = trim($_POST['designer1']      ?? '');
$designer2     = trim($_POST['designer2']      ?? '');
$designer3     = trim($_POST['designer3']      ?? '');
$designer4     = trim($_POST['designer4']      ?? '');
$artist1       = trim($_POST['artist1']        ?? '');
$artist2       = trim($_POST['artist2']        ?? '');
$artist3       = trim($_POST['artist3']        ?? '');
$artist4       = trim($_POST['artist4']        ?? '');
$rules         = trim($_POST['rules']          ?? '');
$play          = trim($_POST['play']           ?? '');
$print         = trim($_POST['print']          ?? '');
$sellsheet     = trim($_POST['sellsheet']      ?? '');
$view          = trim($_POST['view']           ?? '');
$video         = trim($_POST['video']          ?? '');
$price         = isset($_POST['price']) ? trim($_POST['price']) : null;
$imageRaw      = trim($_POST['image']          ?? '');
$image         = $imageRaw ? '=IMAGE("' . $imageRaw . '")' : '';
$headerImageRaw = trim($_POST['header_image_url'] ?? '');
$headerImage    = $headerImageRaw ? '=IMAGE("' . $headerImageRaw . '")' : '';
$coverRaw      = trim($_POST['cover_url']      ?? '');
$cover         = $coverRaw ? '=IMAGE("' . $coverRaw . '")' : '';
$availability  = trim($_POST['availability']   ?? '');
$minPlayers    = trim($_POST['min_players']    ?? '');
$maxPlayers    = trim($_POST['max_players']    ?? '');
$minPlaytime   = trim($_POST['min_playtime']   ?? '');
$maxPlaytime   = trim($_POST['max_playtime']   ?? '');
$weight        = trim($_POST['weight']         ?? '');
$galleryAddRaw = trim($_POST['gallery_add']    ?? '');
$galleryAdd    = $galleryAddRaw ? (json_decode($galleryAddRaw, true) ?: []) : [];

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

if (!$origName) {
    echo json_encode(['error' => 'Missing original game name']);
    exit;
}

$data = [
    'orig_name'      => $origName,
    'name'           => $name          ?: $origName,
    'tagline'        => $tagline,
    'description'    => $description,
    'status'         => $status,
    'date_started'   => $dateStarted,
    'date_signed'    => $dateSigned,
    'date_published' => $datePublished,
    'designer1'      => $designer1,
    'designer2'      => $designer2,
    'designer3'      => $designer3,
    'designer4'      => $designer4,
    'rules'          => $rules,
    'play'           => $play,
    'print'          => $print,
    'sellsheet'      => $sellsheet,
    'view'           => $view,
    'video'          => $video,
    'image'          => $image,
    'cover'          => $cover,
    'availability'   => $availability,
];

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$errFile = sys_get_temp_dir() . '/gupdategame_err_' . $sheetId . '.txt';
$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gupdategame.py') . ' '
     . escapeshellarg($arg) . ' 2>' . escapeshellarg($errFile);

$output = trim((string) shell_exec($cmd));
$stderr = file_exists($errFile) ? trim(file_get_contents($errFile)) : '';

// Write debug log so we can diagnose failures without browser console
$logDir = dirname(__DIR__) . '/sheets/' . $sheetId;
if (is_dir($logDir)) {
    $logEntry = date('Y-m-d H:i:s') . "\n"
        . 'orig_name=' . $data['orig_name'] . ' | name=' . $data['name'] . "\n"
        . 'stdout=' . ($output ?: '(empty)') . "\n"
        . 'stderr=' . ($stderr ?: '(empty)') . "\n---\n";
    file_put_contents($logDir . '/debug_updategame.log', $logEntry, FILE_APPEND);
}

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script', 'stderr' => $stderr]);
    exit;
}

$result = json_decode($output, true);
if ($result !== null && !empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, 'games');

    // The Sheets API sometimes returns stale data immediately after a write.
    // Patch games.json in-place with the values we actually sent so the cache
    // is always correct on redirect, regardless of the re-read timing.
    $cacheFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/games.json';
    if (file_exists($cacheFile)) {
        $gamesCache = json_decode(file_get_contents($cacheFile), true);
        if (is_array($gamesCache)) {
            $lookupName = $origName ?: $name;
            foreach ($gamesCache as &$_g) {
                if (isset($_g['Name']) && $_g['Name'] === $lookupName) {
                    if ($name        !== '') $_g['Name']           = $name;
                    if ($tagline     !== '') $_g['Tagline']        = $tagline;
                    if ($status      !== '') $_g['Status']         = $status;
                    if ($coverRaw    !== '') $_g['Cover URL']      = $cover;
                    if ($imageRaw    !== '') {
                        if (array_key_exists('Image URL', $_g)) $_g['Image URL'] = $imageRaw;
                        else $_g['Image'] = $imageRaw;
                    }
                    break;
                }
            }
            unset($_g);
            file_put_contents($cacheFile, json_encode($gamesCache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    // Create [GameName] tab if it doesn't exist, then publish its JSON cache
    $tabGameName = $name ?: $origName;
    $tabCmd = escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/gcreategameboardtab.py') . ' '
            . escapeshellarg($sheetId . '|' . $tabGameName) . ' 2>&1';
    $tabOut = trim((string) shell_exec($tabCmd));
    $tabResult = $tabOut ? json_decode($tabOut, true) : null;
    if ($tabResult === null && $tabOut) {
        $result['tab_error'] = substr($tabOut, 0, 300);
    }
    // Run patch regardless of whether tab-create succeeded — tab may already exist.
    $tabKnown = !empty($tabResult['tab']) || !empty($tabResult['ok']);
    if (!empty($tabResult['tab'])) {
        $result['game_tab'] = $tabResult['tab'];
        $result['game_tab_created'] = !($tabResult['existed'] ?? true);
    }
    if ($tabKnown || $tabGameName) {
        // Patch fields in the [GameName] tab — runs for both new and existing tabs.
        $patchFields = [];
        if ($description     !== '') $patchFields['Description']   = $description;
        if ($headerImageRaw  !== '') $patchFields['HeaderImage']   = $headerImage;
        if ($coverRaw        !== '') $patchFields['ProductImage']  = $cover;
        if ($rules       !== '') $patchFields['Rules PDF Url'] = $rules;
        if ($sellsheet   !== '') $patchFields['Sellsheet URL'] = $sellsheet;
        if ($view        !== '') $patchFields['Page URL']      = $view;
        // Stat rows live in [GameName] tab, not in Games sheet columns
        if ($minPlayers  !== '') $patchFields['MinPlayers']    = $minPlayers;
        if ($maxPlayers  !== '') $patchFields['MaxPlayers']    = $maxPlayers;
        if ($minPlaytime !== '') $patchFields['MinPlaytime']   = $minPlaytime;
        if ($maxPlaytime !== '') $patchFields['MaxPlaytime']   = $maxPlaytime;
        if ($weight      !== '') $patchFields['Weight']        = $weight;
        // Price goes through gupdategametab atomically (see $extraRows below)
        if ($patchFields) {
            $patchData    = ['game' => $tabGameName, 'fields' => $patchFields];
            $patchEncoded = base64_encode(json_encode($patchData, JSON_UNESCAPED_UNICODE));
            $patchCmd     = escapeshellarg($pythonPath) . ' '
                          . escapeshellarg(__DIR__ . '/gpatchtabfields.py') . ' '
                          . escapeshellarg($sheetId . '|' . $patchEncoded) . ' 2>&1';
            $patchOut = trim((string) shell_exec($patchCmd));
            if ($patchOut) {
                $patchResult = json_decode($patchOut, true);
                if (!empty($patchResult['patched']) || !empty($patchResult['appended'])) {
                    $result['game_tab_patched'] = ($patchResult['patched'] ?? 0) + ($patchResult['appended'] ?? 0);
                }
                if (!empty($patchResult['error'])) {
                    $result['patch_error'] = $patchResult['error'];
                } elseif ($patchResult === null && $patchOut) {
                    $result['patch_error'] = substr($patchOut, 0, 300);
                }
            }
        }

        // Publish the tab as game-{name}-en.json using gpublishgame.py
        // (gread.py would treat row 1 as headers — wrong for key-value tabs)
        $pubCmd = escapeshellarg($pythonPath) . ' '
                . escapeshellarg(__DIR__ . '/gpublishgame.py') . ' '
                . escapeshellarg($sheetId . '|' . $tabGameName) . ' 2>/dev/null';
        $pubOut = trim((string) shell_exec($pubCmd));
        if ($pubOut && !empty(json_decode($pubOut, true)['ok'])) {
            $result['game_tab_cached'] = true;
        }
    }

    // Append any uploaded gallery images to the [GameName] tab as ProductImage rows
    if (!empty($galleryAdd) && is_array($galleryAdd)) {
        $appendData    = ['game' => $tabGameName, 'rows' => []];
        foreach ($galleryAdd as $imgUrl) {
            $imgUrl = trim((string)$imgUrl);
            if ($imgUrl) $appendData['rows'][] = ['name' => 'ProductImage', 'value' => $imgUrl, 'extra' => ''];
        }
        if ($appendData['rows']) {
            $appendEncoded = base64_encode(json_encode($appendData, JSON_UNESCAPED_UNICODE));
            $appendCmd = escapeshellarg($pythonPath) . ' '
                       . escapeshellarg(__DIR__ . '/gappendtabrows.py') . ' '
                       . escapeshellarg($sheetId . '|' . $appendEncoded) . ' 2>/dev/null';
            $appendOut = trim((string) shell_exec($appendCmd));
            if ($appendOut) {
                $appendResult = json_decode($appendOut, true);
                if (!empty($appendResult['ok'])) {
                    $result['gallery_added'] = $appendResult['rows_added'] ?? count($galleryAdd);
                    // Re-publish so the page cache reflects the new images
                    shell_exec(escapeshellarg($pythonPath) . ' '
                        . escapeshellarg(__DIR__ . '/gpublishgame.py') . ' '
                        . escapeshellarg($sheetId . '|' . $tabGameName) . ' 2>/dev/null');
                }
            }
        }
    }

    // Update Component / Review / FAQ / BuyUrl rows in the [GameName] tab
    $componentsRaw  = trim($_POST['components']   ?? '');
    $reviewsRaw     = trim($_POST['reviews']      ?? '');
    $videosRaw      = trim($_POST['videos']       ?? '');
    $faqsRaw        = trim($_POST['faqs']         ?? '');
    $artistsRaw     = trim($_POST['artists']      ?? '');
    $buyUrlsRaw          = trim($_POST['buy_urls']          ?? '');
    $resourceLinksRaw    = trim($_POST['resource_links']    ?? '');
    $galleryUrlsRaw = isset($_POST['gallery_urls']) ? trim($_POST['gallery_urls']) : null;
    // Convert gallery_urls to ProductImage rows for gupdategametab.py
    $galleryRows    = [];
    $galleryDelete  = false;
    if ($galleryUrlsRaw !== null) {
        $galleryUrlsParsed = $galleryUrlsRaw !== '' ? (json_decode($galleryUrlsRaw, true) ?: []) : [];
        foreach ($galleryUrlsParsed as $gUrl) {
            $gUrl = trim((string)$gUrl);
            if ($gUrl) {
                $galleryRows[] = ['name' => 'ProductImage', 'value' => '=IMAGE("' . $gUrl . '")', 'extra' => ''];
            }
        }
        if (empty($galleryRows)) $galleryDelete = true; // gallery cleared — remove all ProductImage rows
    }
    // Convert artists/buy_urls/components/reviews/videos/faqs to rows format
    // (gupdategametab.py only reads 'rows' and 'delete_names').
    $extraRows    = [];
    $deleteNames  = $galleryDelete ? ['ProductImage'] : [];

    // HeaderImage — write atomically to avoid API eventual-consistency race
    if ($headerImageRaw !== '') {
        $extraRows[] = ['name' => 'HeaderImage', 'value' => $headerImage, 'extra' => ''];
    }

    // Price — write atomically. Prefix with ' so Google Sheets stores it as text,
    // not as a number (USER_ENTERED interprets "$20" as currency → reads back as 20).
    if ($price !== null) {
        $priceSheet = $price !== '' ? "'" . $price : '';
        $extraRows[] = ['name' => 'Price', 'value' => $priceSheet, 'extra' => ''];
    }

    // Artists
    if ($artistsRaw !== '') {
        $artistsParsed = json_decode($artistsRaw, true) ?: [];
        foreach ($artistsParsed as $a) {
            $a = trim((string)$a);
            if ($a) $extraRows[] = ['name' => 'Artist', 'value' => $a, 'extra' => ''];
        }
        if (empty($artistsParsed)) $deleteNames[] = 'Artist';
    }

    // BuyUrls
    if ($buyUrlsRaw !== '') {
        $buyUrlsParsed = json_decode($buyUrlsRaw, true) ?: [];
        foreach ($buyUrlsParsed as $b) {
            $bUrl   = trim((string)($b['url']   ?? ''));
            $bLabel = trim((string)($b['label'] ?? ''));
            if ($bUrl || $bLabel) $extraRows[] = ['name' => 'BuyUrl', 'value' => $bUrl, 'extra' => $bLabel];
        }
        if (empty($buyUrlsParsed)) $deleteNames[] = 'BuyUrl';
    }

    // ResourceLinks
    if ($resourceLinksRaw !== '') {
        $resourceLinksParsed = json_decode($resourceLinksRaw, true) ?: [];
        foreach ($resourceLinksParsed as $rl) {
            $rlUrl   = trim((string)($rl['url']   ?? ''));
            $rlLabel = trim((string)($rl['label'] ?? ''));
            if ($rlUrl || $rlLabel) $extraRows[] = ['name' => 'ResourceLink', 'value' => $rlUrl, 'extra' => $rlLabel];
        }
        if (empty($resourceLinksParsed)) $deleteNames[] = 'ResourceLink';
    }

    // Components
    if ($componentsRaw !== '') {
        $componentsParsed = json_decode($componentsRaw, true) ?: [];
        foreach ($componentsParsed as $c) {
            $c = trim((string)$c);
            if ($c !== '') $extraRows[] = ['name' => 'Component', 'value' => $c, 'extra' => ''];
        }
        if (empty($componentsParsed)) $deleteNames[] = 'Component';
    }

    // Reviews
    if ($reviewsRaw !== '') {
        $reviewsParsed = json_decode($reviewsRaw, true) ?: [];
        foreach ($reviewsParsed as $rv) {
            $rvAttr  = trim((string)($rv['attr']  ?? ''));
            $rvQuote = trim((string)($rv['quote'] ?? ''));
            if ($rvAttr !== '') $extraRows[] = ['name' => 'Review', 'value' => $rvAttr, 'extra' => $rvQuote];
        }
        if (empty($reviewsParsed)) $deleteNames[] = 'Review';
    }

    // Videos
    if ($videosRaw !== '') {
        $videosParsed = json_decode($videosRaw, true) ?: [];
        foreach ($videosParsed as $vd) {
            $vdUrl  = trim((string)($vd['url']  ?? ''));
            $vdName = trim((string)($vd['name'] ?? ''));
            if ($vdUrl !== '') $extraRows[] = ['name' => 'Video', 'value' => $vdUrl, 'extra' => $vdName];
        }
        if (empty($videosParsed)) $deleteNames[] = 'Video';
    }

    // FAQs
    if ($faqsRaw !== '') {
        $faqsParsed = json_decode($faqsRaw, true) ?: [];
        foreach ($faqsParsed as $fq) {
            $fqQ = trim((string)($fq['q'] ?? ''));
            $fqA = trim((string)($fq['a'] ?? ''));
            if ($fqQ !== '') $extraRows[] = ['name' => 'FAQ', 'value' => $fqQ, 'extra' => $fqA];
        }
        if (empty($faqsParsed)) $deleteNames[] = 'FAQ';
    }

    if ($price !== null || $componentsRaw !== '' || $reviewsRaw !== '' || $videosRaw !== '' || $faqsRaw !== '' || $artistsRaw !== '' || $buyUrlsRaw !== '' || $resourceLinksRaw !== '' || $galleryUrlsRaw !== null || $extraRows) {
        $tabContent = [
            'game'         => $tabGameName,
            'rows'         => array_merge($extraRows, $galleryRows),
            'delete_names' => $deleteNames,
        ];
        $tabContentEncoded = base64_encode(json_encode($tabContent, JSON_UNESCAPED_UNICODE));
        $tabContentCmd = escapeshellarg($pythonPath) . ' '
                       . escapeshellarg(__DIR__ . '/gupdategametab.py') . ' '
                       . escapeshellarg($sheetId . '|' . $tabContentEncoded) . ' 2>/dev/null';
        $tabContentOut = trim((string) shell_exec($tabContentCmd));
        if ($tabContentOut) {
            $tcr = json_decode($tabContentOut, true);
            if (!empty($tcr['ok'])) {
                $result['tab_content_updated'] = true;
            } elseif (!empty($tcr['error'])) {
                $result['tab_content_error'] = $tcr['error'];
            }
        }
        // Re-publish the tab cache so the page reflects the new content
        shell_exec(escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/gpublishgame.py') . ' '
            . escapeshellarg($sheetId . '|' . $tabGameName) . ' 2>/dev/null');
    }

    // If the game was renamed, also refresh pitches, rename local JSON, and update share files
    $isRename = $name && $origName && $name !== $origName;
    if ($isRename) {
        refreshJson($pythonPath, $sheetId, 'pitches');

        // Rename the per-game JSON file
        $sheetsDir   = dirname(__DIR__) . '/sheets/' . $sheetId;
        $oldSafe     = str_replace(['/', '\\'], '-', $origName);
        $newSafe     = str_replace(['/', '\\'], '-', $name);
        $oldJsonFile = $sheetsDir . '/game-' . $oldSafe . '-en.json';
        $newJsonFile = $sheetsDir . '/game-' . $newSafe . '-en.json';
        if (file_exists($oldJsonFile)) {
            rename($oldJsonFile, $newJsonFile);
            $result['game_json_renamed'] = true;
        }

        // Update any share files that reference the old game name.
        // Covers both shares/*.json (collab snapshots) and
        // shares/pitch-game-view/*.json (game page tokens).
        $sharesDir     = dirname(__DIR__) . '/shares';
        $sharesUpdated = 0;
        $shareGlobs    = [
            $sharesDir . '/*.json',
            $sharesDir . '/pitch-game-view/*.json',
        ];
        foreach ($shareGlobs as $globPattern) {
            foreach (glob($globPattern) as $shareFile) {
                $raw  = file_get_contents($shareFile);
                $data = json_decode($raw, true);
                if (!is_array($data)) continue;
                $changed = false;
                // pitch-game-view format: "game" is a plain string
                if (isset($data['game']) && is_string($data['game']) && $data['game'] === $origName) {
                    $data['game'] = $name;
                    $changed = true;
                }
                // Collab snapshot format: game is an array with a Name key
                if (isset($data['game']['Name']) && is_array($data['game']) && $data['game']['Name'] === $origName) {
                    $data['game']['Name'] = $name;
                    $changed = true;
                }
                // pitches[].Game
                if (!empty($data['pitches']) && is_array($data['pitches'])) {
                    foreach ($data['pitches'] as &$pitch) {
                        if (isset($pitch['Game']) && $pitch['Game'] === $origName) {
                            $pitch['Game'] = $name;
                            $changed = true;
                        }
                    }
                    unset($pitch);
                }
                if ($changed) {
                    file_put_contents($shareFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $sharesUpdated++;
                }
            }
        }
        if ($sharesUpdated > 0) $result['shares_updated'] = $sharesUpdated;

        // Delete old bracket JSON files for the old game name ([OldName].json, etc.)
        $oldBracketPrefix = strtolower('[' . $origName . ']');
        $sheetsDir2 = dirname(__DIR__) . '/sheets/' . $sheetId;
        if (is_dir($sheetsDir2)) {
            foreach (glob($sheetsDir2 . '/[[]*.json') as $f) {
                $base = strtolower(basename($f));
                if (strpos($base, $oldBracketPrefix) === 0) {
                    unlink($f);
                }
            }
        }

        // Refresh the new game tabs ([NewName], [NewName] dev, etc.) so their
        // JSON cache files are created / updated after the rename.
        $listCmd = escapeshellarg($pythonPath) . ' '
                 . escapeshellarg(__DIR__ . '/glisttabs.py') . ' '
                 . escapeshellarg($sheetId . '|[' . $name . ']') . ' 2>/dev/null';
        $listOut = trim((string) shell_exec($listCmd));
        if ($listOut) {
            $listResult = json_decode($listOut, true);
            if (!empty($listResult['tabs'])) {
                foreach ($listResult['tabs'] as $tabName) {
                    refreshJson($pythonPath, $sheetId, $tabName);
                }
                $result['game_tabs_refreshed'] = $listResult['tabs'];
            }
        }
    }
}
echo $result !== null ? json_encode($result) : json_encode(['error' => $output, 'stderr' => $stderr]);
?>
