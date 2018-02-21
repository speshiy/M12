<?php

namespace M12_Engine\Models\Logs\Release;

use M12_Engine\Core\Factory;

class Item {
    
    public function write($record){
        $db = Factory::getDatabase();
        $query = "INSERT INTO `release_error_log` (`release_id`, `message`) VALUES";
        $query .= " ({$record["release_id"]}, '" . $db->escape($record["message"]) . "')";
        $db->insert($query);
    }
}
