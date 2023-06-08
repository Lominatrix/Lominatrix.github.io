<?php
    include 'Database.php';

    $ip = $_SERVER['REMOTE_ADDR'];
    $cd = Database::getCooldownForClient($ip);

    echo $cd;
?>