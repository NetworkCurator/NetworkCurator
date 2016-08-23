<?php

/**
 * Class handling requests for node and link classes
 * (e.g. define a new class of node or link)
 * 
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 * 
 */
class NCOntology {

    // db connection and array of parameters
    private $_conn;
    private $_params;
    // some variables extracted from $_params, for convenience
    private $_network;
    private $_uid;

    /**
     * Constructor 
     * 
     * @param type $conn
     * 
     * Connection to the NC database
     * 
     * @param type $params
     * 
     * array with parameters
     */
    public function __construct($conn, $params) {

        $this->_conn = $conn;
        $this->_params = $params;

        // make parameters SQL-safe
        foreach ($params as $key => $value) {
            $this->_params[$key] = addslashes(stripslashes($value));
        }

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

        // check that the user has permissions to view this network
        $NCAccess = new NCAccess($this->_conn);
        if ($NCAccess->getUserPermissions($this->_network, $this->_uid) < 1) {
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
        $tn = "" . NC_TABLE_NETWORKS;

        // query the classes table for this network
        $sql = "SELECT class_id, class_name, parent_id, connector, directional, 
            class_score, class_status FROM " . $tn . " 
                JOIN " . $tc . " ON $tc.network_id=$tn.network_id 
                WHERE $tn.network_name='$this->_network'";
        if ($this->_params['ontology'] === "links") {
            $sql .= " AND connector=1";
        } else if ($this->_params['ontology'] === "nodes") {
            $sql .= " AND connector=0";
        } else {
            throw new Exception("Unrecognized ontology");
        }
        $sql .= " ORDER BY parent_id";
        
        $sqlresult = mysqli_query($this->_conn, $sql);
        if (!$sqlresult) {
            throw new Exception("Failed ontology lookup");
        }
        $ans = array();
        while ($row = mysqli_fetch_assoc($sqlresult)) {
            $ans[$row['class_id']] = $row;
        }
        return $ans;                
    }

}

?>
