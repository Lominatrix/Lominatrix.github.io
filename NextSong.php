<?php
    include 'Request.php';
    include 'Session.php';
    include 'SpotifyWebAPI.php';
    include 'Debugger.php';
    include 'Database.php';

    $api = new SpotifyWebAPI\SpotifyWebAPI();
    $api->setReturnType(SpotifyWebAPI\SpotifyWebAPI::RETURN_OBJECT);
    $accessToken = Database::getAccessToken();
    $api->setAccessToken($accessToken);
    $api->next();
?>