<?php
    include 'Request.php';
    include 'Session.php';
    include 'SpotifyWebAPI.php';
    include 'Database.php';

    $refresh_token = Database::getRefreshToken();
    $session = Database::restoreSessionObject();
    $success = $session->refreshAccessToken($refresh_token);
    
    Database::saveAccessToken($session->getAccessToken());
    Database::saveRefreshToken($session->getRefreshToken());

    // $duration = 2700;
    // $command =  'sleep '. $duration . ' > /dev/null & echo $!; ';
    // $pid = exec($command, $output);

    // while(file_exists("/proc/" . $pid)) {
    //     sleep(1);
    // }
    
    // shell_exec('sleep 2700');
    // shell_exec('php ./RefreshTokens.php');
?>