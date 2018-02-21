<?php

namespace M12_Engine\Controllers;

use M12_Engine\Models\Releases\Peer as ReleasePeer;
use M12_Engine\Models\Releases\Item as ReleaseModel;
use M12_Engine\Models\Files\Peer as FilePeer;
use M12_Engine\Models\Servers\Peer as ServerPeer;
use M12_Engine\Models\Servers\Item as ServerModel;
use M12_Engine\Models\Logs\Release\Item as ReleaseErrorLog;

class Worker1 {

    public $release_id = null;

    public function pickProductionRelease(){
        $release_peer = new ReleasePeer();
        $releases = $release_peer->get(array(
            "filterby" => array(
                array(
                    "column" => "production_replication",
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

        if(!empty($releases[0]["id"]) ){
            $this->release_id = $releases[0]["id"];
            return true;
        }

        return false;
    }

    public function runProductionRelease(){
        $release_model = new ReleaseModel();
        $release_model->beginProductionBackup($this->release_id);

        $server_peer = new ServerPeer();
        $servers = $server_peer->get(array(
            "filterby" => array(
                array(
                    "column" => "backup_file",
                    "condition" => "is not null",
                ),
            ),
        ));
        if(empty($servers[0]) ){
            throw new \Exception("Cannot get backup server.");
        }
        $backup_server = $servers[0];

        $server_model = new ServerModel();
        if(!$server_model->makeBackup($backup_server) ){
            $error_log = new ReleaseErrorLog();
            $error_log->write(array(
                "release_id" => $this->release_id,
                "message" => $server_model->error,
            ));

            $release_model->setProductionError($this->release_id);
            return;
        }

        $release_model->completeProductionBackup($this->release_id);
        $release_model->beginProductionUpdate($this->release_id);

        $done = false;
        while(!$done){
            $servers = $server_peer->get(array(
                "filterby" => array(
                    array(
                        "column" => "error",
                        "condition" => "equals",
                        "rvalue" => 1,
                    ),
                ),
            ));
            if(!empty($servers[0]) ){
                $server = $servers[0];
                $error_log = new ReleaseErrorLog();
                $error_log->write(array(
                    "release_id" => $this->release_id,
                    "message" => "Server replication error: {$server["ip_address"]}",
                ));

                $release_model->setProductionError($this->release_id);
                return;
            }

            $servers = $server_peer->get(array(
                "filterby" => array(
                    array(
                        "column" => "replication",
                        "condition" => "equals",
                        "rvalue" => 1,
                    ),
                ),
            ));
            if(\count($servers) == 0){
                $done = true;
            }

            \sleep(3);
        }

        $release_model->completeProductionUpdate($this->release_id);
    }

    public function pickBetaRelease(){
        $release_peer = new ReleasePeer();
        $releases = $release_peer->get(array(
            "filterby" => array(
                array(
                    "column" => "beta_replication",
                    "condition" => "equals",
                    "rvalue" => 1,
                ),
                array(
                    "column" => "beta_error",
                    "condition" => "equals",
                    "rvalue" => 0,
                ),
                array(
                    "column" => "beta_completed_at",
                    "condition" => "is null",
                ),
            ),
        ));

        if(!empty($releases[0]["id"]) ){
            $this->release_id = $releases[0]["id"];
            return true;
        }

        return false;
    }

    public function runBetaRelease(){
        $file_peer = new FilePeer();
        $files = $file_peer->get(array(
            "filterby" => array(
                array(
                    "table" => "t_map",
                    "column" => "release_id",
                    "condition" => "equals",
                    "rvalue" => $this->release_id,
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
                    "column" => "dev",
                    "condition" => "equals",
                    "rvalue" => 1,
                ),
            ),
        ));
        if(empty($servers[0]) ){
            throw new \Exception("Cannot get developer server.");
        }
        $dev_server = $servers[0];

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

        $server_model = new ServerModel();
        $success = $server_model->replication(array(
            "source" => $dev_server,
            "target" => $beta_server,
            "files" => $files,
        ));

        $release_model = new ReleaseModel();
        if(!$success){
            $error_log = new ReleaseErrorLog();
            $error_log->write(array(
                "release_id" => $this->release_id,
                "message" => $server_model->error,
            ));

            $release_model->setBetaError($this->release_id);
        }
        else {
            $release_model->completeBetaReplication($this->release_id);
            $server_model->completeReplication($beta_server["id"]);
        }
    }
}
