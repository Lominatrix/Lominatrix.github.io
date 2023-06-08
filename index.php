<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
        <meta http-equiv="Pragma" content="no-cache" />
        <meta http-equiv="Expires" content="0" />
        <title>Devatus Party Player</title>
    </head>
    <body>
        <?php
            error_reporting(E_ALL); 
            ini_set('display_errors', 1);

            include 'Request.php';
            include 'Session.php';
            include 'SpotifyWebAPI.php';
            include 'Debugger.php';
            include 'Database.php';

            $api = new SpotifyWebAPI\SpotifyWebAPI();
            $api->setAccessToken(Database::getAccessToken());
            $isValidToken = $api->validateAccessToken();
            
            if ($isValidToken) {
                header('Location: main.html');
            }
            else {
                $session = new SpotifyWebAPI\Session(
                    '6fc74202ab1c435587ef9f57551db9bf',
                    '01f8ac7d263144d8bc419748ae2b63c7',
                    'http://192.168.1.12/Auth.php'
                );
                
                Database::saveSessionObject($session);
                
                $api = new SpotifyWebAPI\SpotifyWebAPI();
            
                if (isset($_GET['code'])) {
                    $session->requestAccessToken($_GET['code']);
                    $api->setAccessToken($session->getAccessToken());
                    Database::saveAccessToken($session->getAccessToken());
                    Database::saveRefreshToken($session->getRefreshToken());
                
                    print_r($api->me());
                
                } else {
                    $options = [
                        'scope' => [
                            'user-read-email',
                            'user-read-private',
                            'user-read-birthdate',
                            'user-read-currently-playing',
                            'playlist-read-private',
                            'playlist-modify-public',
                            'playlist-modify-private',
                            'playlist-read-collaborative',
                            'user-read-playback-state',
                            'user-modify-playback-state',
                            'user-read-currently-playing',
                            'app-remote-control',
                            'streaming'
                        ],
                    ];        
                    
                    Database::saveSessionObject($session);
                    header('Location: ' . $session->getAuthorizeUrl($options));
                    die();
                }
            }
        ?>
    </body>
</html>
