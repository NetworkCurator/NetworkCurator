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
     * @param string $sql
     * 
     * SQL statement to execute
     * 
     * @param array $arr
     * 
     * parameters to bind into the SQL statement
     * 
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
       
    /**
     * Simpler version of qPE where the statement is executed without binding and
     * without providing any output/return object.
     * 
     * @param string $sql
     * 
     * simple SQL statement
     */
    protected function q($sql) {
        $this->_db->query($sql);
    }
}
?>