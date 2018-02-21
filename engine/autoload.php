<?php

if(!defined("_M12_PATH_ENGINE") ){
    define("_M12_PATH_ENGINE", dirname(__FILE__) );
}

spl_autoload_register(function($class){
    $segments = explode("\\", $class);
    if(!empty($segments[0]) && ($segments[0] == "M12_Engine") ){
        $first = 0;
        $last = count($segments) - 1;
        $filename = "";
        for($i = 0; $i < count($segments); $i++){
            if($i == $first){
                $filename .= _M12_PATH_ENGINE;
                continue;
            }
            $filename .= "/" . $segments[$i];
            $filename .= $i == $last ? ".php" : "";
        }
        if(is_file($filename) ){
            include $filename;
        }
    }
});
