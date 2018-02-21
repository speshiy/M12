<?php

namespace M12_Engine\Core;

use M12_Engine\Core\Factory;

class Database {

    private static $_instance = null;

    private $_connection = null;

    public function delete($query){
        return $this->update($query);
    }

    public function update($query){
        $this->execute($query);
        return \mysqli_affected_rows($this->_connection);
    }

    public function insert($query){
        $this->execute($query);
        return \mysqli_insert_id($this->_connection);
    }

    public function escape($string){
        return \mysqli_real_escape_string($this->_connection, $string);
    }

    public function execute($query){
        $result = \mysqli_query($this->_connection, $query);
        if($result === false){
            $message = \mysqli_error($this->_connection);
            throw new \Exception($message);
        }
    }

    public function getRow($query){
        $result = \mysqli_query($this->_connection, $query);
        if($result === false){
            $message = \mysqli_error($this->_connection);
            throw new \Exception($message);
        }
        $row = \mysqli_fetch_array($result);
        \mysqli_free_result($result);
        return $row;
    }

    public function getField($query, $index=0){
        $row = $this->getRow($query);
        return isset($row[$index]) ? $row[$index] : null;
    }

    public function getRows($query){
        $result = \mysqli_query($this->_connection, $query);
        if($result === false){
            $message = \mysqli_error($this->_connection);
            throw new \Exception($message);
        }
        $rows = array();
        while ($row = \mysqli_fetch_array($result)) {
            $rows[] = $row;
        }
        \mysqli_free_result($result);
        return $rows;
    }

    public function getColumn($query, $index=0){
        $output = array();
        $rows = $this->getRows($query);
        if(!empty($rows) ){
            foreach($rows as $row){
                if(isset($row[$index]) ){
                    $output[] = $row[$index];
                }
            }
        }
        return $output;
    }

    private function __construct(){
        $settings = Factory::getSettings();
        $this->_connection = mysqli_connect(
            $settings->db_host,
            $settings->db_user,
            $settings->db_password,
            $settings->db_name
        );
        if(!$this->_connection){
            throw new \Exception("Connect Error (" . mysqli_connect_errno() . ") "
                . mysqli_connect_error() );
        }
    }

    public static function getInstance(){
        if(empty(self::$_instance) ){
            self::$_instance = new Database();
        }
        return self::$_instance;
    }
}
