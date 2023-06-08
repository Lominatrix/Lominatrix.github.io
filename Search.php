<?php
    include 'FileHelper.php';
    include 'Request.php';
    include 'Session.php';
    include 'SpotifyWebAPI.php';
    include 'Debugger.php';
    include 'Database.php';

    $text = $_REQUEST["text"];	
    $api = new SpotifyWebAPI\SpotifyWebAPI();
    $api->setReturnType(SpotifyWebAPI\SpotifyWebAPI::RETURN_OBJECT);
    $accessToken = Database::getAccessToken();
    $api->setAccessToken($accessToken);

    $options = [
        'limit' => '50',
        'market' => 'from_token'
    ];

    $tracks = $api->search($text . "*", 'track', $options);

    echo json_encode($tracks);
?>