<?php

namespace M12_Engine\Constants;

class Constants {

    private static $_instance = null;
    public $ssh_options_g = "-o 'StrictHostKeyChecking no' -o IdentityFile=/var/www/updater/updater_rsa";

    private function __construct() {
        
    }

    public static function getInstance() {
        if (empty(self::$_instance)) {
            self::$_instance = new Constants();
        }
        return self::$_instance;
    }

}
