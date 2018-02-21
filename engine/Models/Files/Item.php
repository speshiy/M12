<?php

namespace M12_Engine\Models\Files;

use M12_Engine\Core\Factory;
use M12_Engine\Models\Servers\Peer as ServerPeer;
use M12_Engine\Models\Files\Peer as FilePeer;


class Item {
    
    public function update($file){
        $server_peer = new ServerPeer();
        $servers = $server_peer->get(array(
            "filterby" => array(
                array(
                    "column" => "dev",
                    "condition" => "equals",
                    "rvalue" => 1,
                ),
            ),
        ));
        if(empty($servers) ){
            throw new \Exception("Cannot find development server.");
        }

        $server = $servers[0];
        $user = $server["ssh_login"];
        $host = $server["ip_address"];
        $basepath = $server["project_directory"];
        $constants = Factory::getConstants();
        $ssh_options = $constants->ssh_options_g; 
        $command = "ssh {$ssh_options} {$user}@{$host} 'if [ -d {$basepath}/{$file} ] ; then echo 'directory' ; elif [ -f {$basepath}/{$file} ] ; then echo 'file' ; fi'";
        $output = \system($command, $retval);

        switch($retval){
        case "directory":
            break;
        case "file":
            break;
        default:
            throw new \Exception("Invalid path: {$file}");
        }

        $item = array(
            "hash" => \md5($file),
            "path" => $file,
            "type" => $output,
        );
        $file_peer = new FilePeer();
        $files = $file_peer->get(array(
            "filterby" => array(
                array(
                    "column" => "hash",
                    "condition" => "equals",
                    "rvalue" => $item["hash"],
                ),
            ),
        ));

        $file_id = !empty($files[0]["id"]) ? $files[0]["id"] : null;
        if($file_id){
            if($item["type"] != $files[0]["type"]){
                throw new \Exception("Unexpected type for file {$file}");
            }
        }
        else {
            $db = Factory::getDatabase();
            $query = "INSERT INTO `files` SET";
            $query .= " `hash` = '{$item["hash"]}',";
            $query .= " `path` = '" . $db->escape($item["path"]) . "',";
            $query .= " `type` = '{$item["type"]}'";
            $file_id = $db->insert($query);
        }

        return $file_id;
    }
}
