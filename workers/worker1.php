<?php

use M12_Engine\Controllers\Worker1;

require_once realpath(dirname(__FILE__) . "/../engine/autoload.php");

try {
    $worker = new Worker1();

    while(true){
        if($worker->pickBetaRelease() ){
            $worker->runBetaRelease();
        }
        elseif($worker->pickProductionRelease() ){
            $worker->runProductionRelease();
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
