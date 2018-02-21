<?php

namespace M12_Engine\Models\Files;

use M12_Engine\Core\Factory;

class Peer {

    public function get($options=array() ){
        $db = Factory::getDatabase();
        $filterby = !empty($options["filterby"]) ? $options["filterby"] : array();

        $columns = "*";
        $table_references = "`files` AS `t_files`";
        $where = "";

        $joins = array(
            "t_map" => array(
                "table" => "file_release_map",
                "alias" => "t_map",
                "type" => "INNER",
                "on" => "`t_map`.`file_id` = `t_files`.`id`",
                "enabled" => false,
            ),
        );

        if(!empty($filterby) ){
            foreach($filterby as $filter){
                $table = !empty($filter["table"]) ? $filter["table"] : "t_files";
                if($table == "t_map"){
                    $joins["t_map"]["enabled"] = true;
                }

                $column = $filter["column"];
                $condition = $filter["condition"];
                $rvalue = isset($filter["rvalue"]) ? $db->escape($filter["rvalue"]) : "";
                $clause = isset($filter["strict"]) && !$filter["strict"] ? "OR" : "AND";
                $where .= !empty($where) ? " {$clause}" : "";
                
                if($condition == "is null"){
                    $where .= "`{$table}`.`{$column}` IS NULL";
                }
                elseif($condition == "equals"){
                    $where .= "`{$table}`.`{$column}` = '{$rvalue}'";
                }
            }
        }

        foreach($joins as $join){
            if($join["enabled"]){
                $table_references .= " {$join["type"]} JOIN `{$join["table"]}` AS `{$join["alias"]}` ON {$join["on"]}";
            }
        }

        $query = "SELECT {$columns} FROM {$table_references}";
        $query .= !empty($where) ? " WHERE {$where}" : "";
        return $db->getRows($query);
    }
}
