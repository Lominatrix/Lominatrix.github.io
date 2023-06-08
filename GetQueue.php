<?php

include "Database.php";

$queue = Database::getSongQueue();
echo $queue;

?>