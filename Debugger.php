<?php

class Debugger {

    public static function log($text) {
        echo "<script>console.log( 'Debug Objects: " . $text . "' );</script>";
    }
}
?>