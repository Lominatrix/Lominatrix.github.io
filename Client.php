<?php
    class Client {

        const REMOTE_ADDR = 'REMOTE_ADDR';
    
        public static function getIpAddress() {
            return $_SERVER[self::REMOTE_ADDR];
        }

        // TODO: Check client cooldown (5 min checked from the unix timestamp)
    }
?>