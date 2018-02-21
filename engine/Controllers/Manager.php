<?php

namespace M12_Engine\Controllers;

use M12_Engine\Models\Releases\Peer as ReleasePeer;
use M12_Engine\Models\Releases\Item as ReleaseModel;
use M12_Engine\Models\Files\Item as FileModel;

class Manager {

    public function createRelease($files, $release_title){
        if(!is_array($files) || empty($files) ){
            throw new \Exception("Cannot create release without files.");
        }

        $release_peer = new ReleasePeer();
        $releases = $release_peer->get(array(
            "filterby" => array(
                array(
                    "column" => "production_completed_at",
                    "condition" => "is null",
                ),
            ),
        ));
        if(!empty($releases) ){
            throw new \Exception("Incomplete release.");
        }

        $file_model = new FileModel();
        $file_ids = array();
        foreach($files as $file){
            $file_ids[] = $file_model->update($file);
        }

        $release_model = new ReleaseModel();
        $release_id = $release_model->create(array(
            "label" => $release_title,
            "files" => $file_ids,
        ));

        $release_model->beginBetaReplication($release_id);
    }

    public function completeRelease($release_id){
        if(empty($release_id) || !is_numeric($release_id) ){
            throw new \Exception("Invalid release ID.");
        }

        $release_peer = new ReleasePeer();
        $releases = $release_peer->get(array(
            "filterby" => array(
                array(
                    "column" => "id",
                    "condition" => "equals",
                    "rvalue" => $release_id,
                ),
            ),
        ));
        if(empty($releases) ){
            throw new \Exception("Invalid release ID.");
        }

        $release = $releases[0];
        if($release["beta_error"] || !$release["beta_completed_at"]){
            throw new \Exception("Beta replication is incompleted.");
        }

        $release_model = new ReleaseModel();
        $release_model->beginProductionReplication($release["id"]);
    }
}
