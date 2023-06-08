<?php

class Database {

    const SERVER_ADDRESS = "localhost";
    const USERNAME = "spotifyer";
    const PASSWORD = "sp0tifyer";
    const DATABASE_NAME = "spotifyer_db";

    const COOLDOWN_DISABLED = false; // Change manually

    public static function saveSessionObject($session) {
        self::saveProperty("session_object", serialize($session));
    }

    public static function restoreSessionObject() {
        $properties = self::getProperties();
        return unserialize($properties['session_object']);
    }

    public static function saveAccessToken($access_token) {
        self::saveProperty("access_token", $access_token);
    }

    public static function getAccessToken() {
        $properties = self::getProperties();
        return $properties['access_token'];
    }

    public static function saveRefreshToken($refresh_token) {
        self::saveProperty("refresh_token", $refresh_token);
    }

    public static function getRefreshToken() {
        $properties = self::getProperties();
        return $properties['refresh_token'];
    }

    public static function setIsPlaylistPlaying($is_playlist_playing) {
        self::saveProperty("is_playlist_playing", $is_playlist_playing);
    }

    public static function isPlaylistPlaying() {
        $properties = self::getProperties();
        return $properties['is_playlist_playing'];
    }

    public static function addSongToQueue($song_id, $song_name, $artist, $message, $user_ip_address, $cooldown_exp) {
	$cooldown_exp = 0;
        $user_id = self::getUserIdByIpAddress($user_ip_address);

        $conn = self::openConnection();

        $stmt_cooldown = $conn->prepare("UPDATE users SET cooldown_exp = ? WHERE users.id = ?");

        if (self::COOLDOWN_DISABLED) {
            $cooldown_exp = time();
        }
        $stmt_cooldown->bind_param("si", $cooldown_exp, $user_id);
        $stmt_cooldown->execute();

        $stmt_cooldown->close();

        $stmt_song_gueue = $conn->prepare("INSERT INTO song_queue (user_id, song_id, song_name, artist, message) VALUES (?, ?, ?, ?, ?)");
        $stmt_song_gueue->bind_param("issss", $user_id, $song_id, $song_name, $artist, $message);
        $stmt_song_gueue->execute();

        $stmt_song_gueue->close();

        $stmt_song_history = $conn->prepare("INSERT INTO song_history (user_id, song_order_index, song_name, artist, message) VALUES (?, (SELECT MAX(id) FROM song_queue LIMIT 1), ?, ?, ?)");
        $stmt_song_history->bind_param("isss", $user_id, $song_name, $artist, $message);
        $stmt_song_history->execute();

        $stmt_song_history->close();

        mysqli_close($conn);
    }

    public static function getFirstSongFromQueue() {
        $conn = self::openConnection();

        $stmt = $conn->prepare("SELECT * FROM song_queue LIMIT 1");
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $stmt->close();

        mysqli_close($conn);

        return json_encode($row);
    }

    public static function removeFirstSongFromQueue() {
        $conn = self::openConnection();

        $stmt = $conn->prepare("DELETE FROM song_queue LIMIT 1");
        $stmt->execute();

        $stmt->close();

        mysqli_close($conn);
    }

    public static function getSongQueue() {
        $conn = self::openConnection();

        $stmt = $conn->prepare("SELECT artist, song_name FROM song_queue");
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $stmt->close();

        $result_array[] = $row;
        while($row = $result->fetch_assoc()) {
            $result_array[] = $row;
        }

        mysqli_close($conn);

        return json_encode($result_array);
    }

    public static function getCurrentSongMessage() {
        $song = json_decode(self::getFirstSongFromQueue());
        $selector = "";

        if ($song->id == null) {
            $selector = "SELECT message FROM song_history ORDER BY id DESC LIMIT 1";
        } else {
            $selector = "SELECT message FROM song_history WHERE song_order_index = " . ($song->id - 1);
        }

        $conn = self::openConnection();

        $stmt = $conn->prepare($selector);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        mysqli_close($conn);
        
        return json_encode($row);
    }

    public static function getCooldownForClient($ip_address) {
        $cooldown_exp = 0;
        
        $conn = self::openConnection();
        
        $stmt = $conn->prepare("SELECT cooldown_exp FROM users WHERE ip_address = ?");
        $stmt->bind_param("s", $ip_address);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $stmt->close();

        if (count($row) != 0) {
            $cooldown_exp = $row['cooldown_exp'];
        }

        mysqli_close($conn);

        return $cooldown_exp - time();
    }

    static function getUserIdByIpAddress($ip_address) {
        $user_id = 0;

        $conn = self::openConnection();

        $stmt_select = $conn->prepare("SELECT id FROM users WHERE ip_address = ?");
        $stmt_select->bind_param("s", $ip_address);
        $stmt_select->execute();

        $result = $stmt_select->get_result();
        $row = $result->fetch_assoc();

        $stmt_select->close();

        if (count($row) != 0) {
            $user_id = $row['id'];
        }
        else {
            // Add new user and get its user_id
            $stmt_insert = $conn->prepare("INSERT INTO users (ip_address) VALUES (?)");
            $stmt_insert->bind_param("s", $ip_address);
            $stmt_insert->execute();

            $stmt_insert->close();

            $user_id = self::getUserIdByIpAddress($ip_address);
        }

        mysqli_close($conn);

        return $user_id;
    }

    static function openConnection() {
        $conn = mysqli_connect(self::SERVER_ADDRESS, self::USERNAME, self::PASSWORD, self::DATABASE_NAME);
        if (!$conn) {
            die("SQL connection failed: " . $conn->connect_error);
        }
        
        return $conn;
    }

    static function saveProperty($key, $value) {
        $conn = self::openConnection();

        $stmt = $conn->prepare("UPDATE properties SET " . $key . " = ? WHERE properties.id = 1");
        $stmt->bind_param("s", $value);
        $stmt->execute();

        $stmt->close();

        mysqli_close($conn);
    }

    static function getProperties() {
        $conn = self::openConnection();

        $stmt = $conn->prepare("SELECT * FROM properties WHERE properties.id = 1");
        $stmt->execute();

        $result = $stmt->get_result();
        $properties = $result->fetch_assoc();

        $stmt->close();

        mysqli_close($conn);

        return $properties;
    }
}

?>
