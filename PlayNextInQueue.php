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
    $song = json_decode(Database::getFirstSongFromQueue());

    if ($song != null) {        
        Database::setIsPlaylistPlaying(false);
        
        $track = $api->getTrack($song->song_id);
        
        $options = [
            'uris' => [$track->uri],
        ];
    
        $api->play(null, $options);
        $duration = (int)($track->duration_ms) / 1000;
        
        Database::removeFirstSongFromQueue();

        $command =  'sleep '. $duration . ' > /dev/null & echo $!; ';
        $pid = exec($command, $output);

        while(file_exists("/proc/" . $pid)) {
            sleep(1);
        }
        
        shell_exec('php ./PlayNextInQueue.php');
    }
    else {
        // Queue is empty - move to playlist with shuffle
        Database::setIsPlaylistPlaying(true);
        $shuffleOpts = [
            'state' => true,
        ];
        $api->shuffle($shuffleOpts);
        
        $playlistUri = "spotify:playlist:2jF7p8SmudDRLCSrfewcrT";
        $options = [
            'context_uri' => $playlistUri,
        ];
        $api->play(null, $options);
    }
?>