<?php

use M12_Engine\Controllers\Worker2;

require_once realpath(dirname(__FILE__) . "/../engine/autoload.php");

try {
    $worker = new Worker2();

    while(true){
        $worker->getLock();
        if($worker->pickReplicatedServer() ){
            $worker->holdReplicatedServer();
        }
        $worker->releaseLock();
        if($worker->hasJob() ){
            $worker->runJob();
        }
 
        sleep(3);
    }
}
catch(Exception $e){
    $output = "Error:\n";
    $output .= "Message: " . $e->getMessage() . "\n";
    $output .= "File: " . $e->getFile() . "\n";
    $output .= "Line: " . $e->getLine() . "\n";
    $output .= "Trace:\n" . $e->getTraceAsString() . "\n";
    echo $output;
}
