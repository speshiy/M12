<?php

namespace M12_Engine\Controllers;

use M12_Engine\Core\Factory;
use M12_Engine\Models\Files\Peer as FilePeer;
use M12_Engine\Models\Releases\Peer as ReleasePeer;
use M12_Engine\Models\Servers\Peer as ServerPeer;
use M12_Engine\Models\Servers\Item as ServerModel;
use M12_Engine\Models\Logs\Server\Item as ServerErrorLog;

class Worker2 {

    public $server_id = null;

    public function runJob(){
        $release_peer = new ReleasePeer();
        $releases = $release_peer->get(array(
            "filterby" => array(
                array(
                    "column" => "production_update",
                    "condition" => "equals",
                    "rvalue" => 1,
                ),
                array(
                    "column" => "production_error",
                    "condition" => "equals",
                    "rvalue" => 0,
                ),
                array(
                    "column" => "production_completed_at",
                    "condition" => "is null",
                ),
            ),
        ));

        if(empty($releases[0]["id"]) ){
            throw new \Exception("Release is not found.");
        }
        $release_id = $releases[0]["id"];

        $file_peer = new FilePeer();
        $files = $file_peer->get(array(
            "filterby" => array(
                array(
                    "table" => "t_map",
                    "column" => "release_id",
                    "condition" => "equals",
                    "rvalue" => $release_id,
                ),
            ),
        ));
        if(empty($files) ){
            throw new \Exception("Cannot get release files.");
        }

        $server_peer = new ServerPeer();
        $servers = $server_peer->get(array(
            "filterby" => array(
                array(
                    "column" => "beta",
                    "condition" => "equals",
                    "rvalue" => 1,
                ),
            ),
        ));
        if(empty($servers[0]) ){
            throw new \Exception("Cannot get beta server.");
        }
        $beta_server = $servers[0];

        $servers = $server_peer->get(array(
            "filterby" => array(
                array(
                    "column" => "id",
                    "condition" => "equals",
                    "rvalue" => $this->server_id,
                ),
            ),
        ));
        if(empty($servers[0]) ){
            throw new \Exception("Cannot get target server.");
        }
        $target_server = $servers[0];

        $server_model = new ServerModel();
        $success = $server_model->replication(array(
            "source" => $beta_server,
            "target" => $target_server,
            "files" => $files,
        ));

        if(!$success){
            $error_log = new ServerErrorLog();
            $error_log->write(array(
                "server_id" => $this->server_id,
                "message" => $server_model->error,
            ));

            $server_model->setReplicationError($this->server_id);
        }
        else {
            $server_model->completeReplication($this->server_id);
        }
    }

    public function hasJob(){
        return $this->server_id ? true : false;
    }

    public function holdReplicatedServer(){
        $server_model = new ServerModel();
        $server_model->block($this->server_id);
    }

    public function pickReplicatedServer(){
        $server_peer = new ServerPeer();
        $servers = $server_peer->get(array(
            "filterby" => array(
                array(
                    "column" => "replication",
                    "condition" => "equals",
                    "rvalue" => 1,
                ),
                array(
                    "column" => "blocked",
                    "condition" => "equals",
                    "rvalue" => 0,
                ),
                array(
                    "column" => "error",
                    "condition" => "equals",
                    "rvalue" => 0,
                ),
            ),
        ));
        if(!empty($servers[0]["id"]) ){
            $this->server_id = $servers[0]["id"];
            return true;
        }

        return false;
    }

    public function getLock(){
        $db = Factory::getDatabase();
        $query = "LOCK TABLES `servers` WRITE, `servers` AS `t_servers` WRITE";
        $db->execute($query);
    }

    public function releaseLock(){
        $db = Factory::getDatabase();
        $query = "UNLOCK TABLES";
        $db->execute($query);
    }
}
