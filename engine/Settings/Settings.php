<?php

namespace M12_Engine\Settings;

class Settings {

    private static $_instance = null;

    public $db_host = "";
    public $db_user = "u_updater";
    public $db_password = "123456";
    public $db_name = "db_updater";

    private function __construct(){
    }

    public static function getInstance(){
        if(empty(self::$_instance) ){
            self::$_instance = new Settings();
        }
        return self::$_instance;
    }
}
