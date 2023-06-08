<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    include 'Request.php';
    include 'Session.php';
    include 'SpotifyWebAPI.php';
    include 'Database.php';

    $session = Database::restoreSessionObject();

    if ($session == null) echo 'Session is null';

    $session->requestAccessToken($_GET['code']);
    
    $accessToken = $session->getAccessToken();
    $refreshToken = $session->getRefreshToken();
    $session->refreshAccessToken($refreshToken);

    Database::saveAccessToken($accessToken);
    Database::saveRefreshToken($refreshToken);

    header('Location: main.html');
    die();
?>
