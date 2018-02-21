<?php

namespace M12_Engine\Models\Servers;

use M12_Engine\Core\Factory;

class Peer {

    public function get($options=array() ){
        $db = Factory::getDatabase();
        $filterby = !empty($options["filterby"]) ? $options["filterby"] : array();

        $columns = "*";
        $table_references = "`servers` AS `t_servers`";
        $where = "";

        if(!empty($filterby) ){
            foreach($filterby as $filter){
                $table = !empty($filter["table"]) ? $filter["table"] : "t_servers";
                $column = $filter["column"];
                $condition = $filter["condition"];
                $rvalue = isset($filter["rvalue"]) ? $db->escape($filter["rvalue"]) : "";
                $clause = isset($filter["strict"]) && !$filter["strict"] ? "OR" : "AND";
                $where .= !empty($where) ? " {$clause}" : "";
                
                if($condition == "is null"){
                    $where .= "`{$table}`.`{$column}` IS NULL";
                }                
                if($condition == "is not null"){
                    $where .= "`{$table}`.`{$column}` IS NOT NULL";
                }
                elseif($condition == "equals"){
                    $where .= "`{$table}`.`{$column}` = '{$rvalue}'";
                }
            }
        }

        $query = "SELECT {$columns} FROM {$table_references}";
        $query .= !empty($where) ? " WHERE {$where}" : "";
        return $db->getRows($query);
    }
}
