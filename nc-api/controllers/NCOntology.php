<?php

/**
 * Class handling requests for node and link classes
 * (e.g. define a new class of node or link)
 * 
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 * 
 */
class NCOntology extends NCLogger {

    // db connection and array of parameters are inherited from NCLogger        
    // some variables extracted from $_params, for convenience
    private $_network;
    private $_uid;
    private $_netid;

    /**
     * Constructor 
     * 
     * @param PDO $db
     * 
     * Connection to the NC database
     * 
     * @param array $params
     * 
     * array with parameters
     */
    public function __construct($db, $params) {
   
        $this->_db = $db;
        $this->_params = $params;
    
        // check for required parameters
        if (isset($params['network_name'])) {
            $this->_network = $this->_params['network_name'];
        } else {
            throw new Exception("NCOntology requires parameter network_name");
        }
        if (isset($params['user_id'])) {
            $this->_uid = $this->_params['user_id'];
        } else {
            throw new Exception("NCOntology requires parameter user_id");
        }

        // all function will need to know the network id code
        $this->_netid = $this->getNetworkId($this->_params['network_name']);
        if ($this->_netid=="") {
            throw new Exception("Network does not exist");
        }
        
        // check that the user has permissions to view this network        
        if ($this->getUserPermissionsNetID($this->_netid, $this->_uid) < NC_PERM_VIEW) {            
            throw new Exception("Insufficient permissions to query ontology");
        }
    }

    /**
     * Similar to listOntology with parameter ontology='nodes'
     */
    public function getNodeOntology() {
        $this->_params['ontology'] = 'nodes';
        return $this->getOntology();
    }

    /**
     * Similar to listOntology with parameter ontology='links'
     */
    public function getLinkOntology() {
        $this->_params['ontology'] = 'links';
        return $this->getOntology();
    }

    /**
     * Looks all the classes associated with nodes or links in a network
     * 
     * 
     * @return array of classes
     * 
     * @throws Exception
     * 
     */
    public function getOntology() {

        if (!isset($this->_params['ontology'])) {
            throw new Exception("Unspecified ontology");
        }

        $tc = "" . NC_TABLE_CLASSES;
        $tat = "" . NC_TABLE_ANNOTEXT;        

        // query the classes table for this network
        $sql = "SELECT class_id, $tc.parent_id AS parent_id, connector, directional, class_status,
            anno_text
            FROM $tc 
              JOIN $tat 
                  ON $tc.class_id=$tat.parent_id AND $tc.network_id=$tat.network_id              
                WHERE $tc.network_id = ? 
                  AND $tat.anno_status = ".NC_ACTIVE;                  
        if ($this->_params['ontology'] === "links") {
            $sql .= " AND connector=1";
        } else if ($this->_params['ontology'] === "nodes") {
            $sql .= " AND connector=0";
        } else {
            throw new Exception("Unrecognized ontology");
        }
        $sql .= " ORDER BY parent_id";
        
        $stmt = prepexec($this->_db, $sql, [$this->_netid]);
        $result = array();
        while ($row = $stmt->fetch()) {
            $result[$row['class_id']] = $row;
        }
        return $result;                
    }

}

?>
