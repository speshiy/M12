<?php

namespace M12_Engine\Core;

use M12_Engine\Core\Database;
use M12_Engine\Settings\Settings;
use M12_Engine\Constants\Constants;

abstract class Factory {

    public static function getDatabase(){
        return Database::getInstance();
    }

    public static function getSettings(){
        return Settings::getInstance();
    }
    
    public static function getConstants(){
        return Constants::getInstance();
    }
}
