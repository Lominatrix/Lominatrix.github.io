<?php
    include 'Request.php';
    include 'Session.php';
    include 'SpotifyWebAPI.php';
    include 'Debugger.php';
    include 'Database.php';

    function getRealIpAddr()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
        {
        $ip=$_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
        {
        $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else
        {
        $ip=$_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    $ip = getRealIpAddr(); //$_SERVER['REMOTE_ADDR'];
    $cd = Database::getCooldownForClient($ip);

    //if ($cd > 0) return; TODO: Uncomment this to enable cooldown

    $api = new SpotifyWebAPI\SpotifyWebAPI();
    $api->setReturnType(SpotifyWebAPI\SpotifyWebAPI::RETURN_OBJECT);
    $accessToken = Database::getAccessToken();
    $api->setAccessToken($accessToken);

    $refresh_token = Database::getRefreshToken();
    $session = Database::restoreSessionObject();
    $success = $session->refreshAccessToken($refresh_token);
    
    $ip = getUserIpAddr();
    $songId = $_REQUEST["songId"];
    $message = $_REQUEST["message"];

    $track = $api->getTrack($songId);
    $artist = $track->artists[0]->name;
    $name = $track->name;
    $duration = $track->duration_ms;
    $cd_expiration = $duration / 1000 + time();    
    
    // If no song is queued, wait until  current song finishes, then play next in queue
    if (Database::isPlaylistPlaying()) {
        Database::setIsPlaylistPlaying(false);
        Database::addSongToQueue($songId, $name, $artist, $message, $ip, $cd_expiration);

        $currentPlaybackInfo = $api->getMyCurrentPlaybackInfo();
        $currentSongProgress = $currentPlaybackInfo->progress_ms;
        
        $track = $currentPlaybackInfo->item;
        
        $duration = ((int)($track->duration_ms) - (int)($currentSongProgress)) / 1000;
        
        shell_exec('sleep '. $duration);
        shell_exec('php ./PlayNextInQueue.php');
    }
    else {
        Database::addSongToQueue($songId, $name, $artist, $message, $ip, $cd_expiration);
    }

    function getUserIpAddr(){
        if(!empty($_SERVER['HTTP_CLIENT_IP'])){
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }else{
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
?>