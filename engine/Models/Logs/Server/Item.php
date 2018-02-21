<?php

namespace M12_Engine\Models\Logs\Server;

use M12_Engine\Core\Factory;

class Item {
    
    public function write($record){
        $db = Factory::getDatabase();
        $query = "INSERT INTO `server_error_log` (`server_id`, `message`) VALUES";
        $query .= " ({$record["server_id"]}, '" . $db->escape($record["message"]) . "')";
        $db->insert($query);
    }
}
