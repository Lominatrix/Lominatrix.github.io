<?php
    include 'Request.php';
    include 'Session.php';
    include 'SpotifyWebAPI.php';
    include 'Debugger.php';
    include 'Database.php';
    
    $api = new SpotifyWebAPI\SpotifyWebAPI();
    $accessToken = Database::getAccessToken();
    $api->setAccessToken($accessToken);
    
    $track = $api->getMyCurrentTrack();

    $song = json_decode(Database::getFirstSongFromQueue());
    $message = json_decode(Database::getCurrentSongMessage())->message;

    $array = array(
        "message" => $message,
        "track" => $track
    );

    echo json_encode($array);
?>