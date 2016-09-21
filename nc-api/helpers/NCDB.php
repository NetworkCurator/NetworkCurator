<?php

/*
 * Class handling db querying, including query caching.
 * 
 * Functions assume that the NC configuration definitions are already loaded
 * 
 */

class NCDB {

    // general connection
    protected $_db;    
    
    /**
     * Constructor with connection to database
     * 
     * @param PDO $db 
     * 
     */
    public function __construct($db) {
        $this->_db = $db;
    }

    protected function dblock($tables) {        
        //$this->_db->beginTransaction();        
        $sql = "LOCK TABLES " . implode(" WRITE, ", $tables)." WRITE ";        
        $this->_db->exec($sql);                
        $this->_db->beginTransaction();        
    }
    
    protected function dbunlock() {
        $this->_db->commit();
        $this->_db->exec('UNLOCK TABLES');
    }
    
    /**
     * Preps and executes a query.
     * 
     * @param type $sql
     * @param type $bind
     * @return PDOStatement
     * 
     * The output can be used to fetch() results of the query
     * 
     */
    protected function qPE($sql, $arr) {
        $stmt = $this->_db->prepare($sql);
        $stmt->execute($arr);
        return $stmt;
    }
       
    protected function q($sql) {
        $stmt = $this->_db->query($sql);
        return $stmt;
    }
    
    /**
     * get the last inserted id
     * 
     * @return type
     */
    protected function lID() {
        return $this->_db->lastInsertId();
    }
    
}
?>