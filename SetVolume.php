<?php
    include 'Request.php';
    include 'Session.php';
    include 'SpotifyWebAPI.php';
    include 'Debugger.php';
    include 'Database.php';
    
    // TODO: Not working on iOS devices (probably not Android either) since there is no volume control inside app
    $volume = $_REQUEST["volume"];
    
    $api = new SpotifyWebAPI\SpotifyWebAPI();
    $accessToken = Database::getAccessToken();
    $api->setAccessToken($accessToken);

    $options = [
        'volume_percent' => volume,
    ];

    $response = $api->changeVolume($options);
    echo $response;
?>