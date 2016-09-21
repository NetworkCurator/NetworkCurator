<?php

/**
 * Class provides an object that is capable of sending requests to the nc-api
 * 
 * 
 */
class NCApiCaller {

    private $_caller;
    private $_uname;
    private $_upw;
    // _p will be initialized as array with userid and userextpwd pre-filled
    private $_p;

    // constructor; creates an instance of the GeneralApiCaller 
    // with the app id, key, and path defined in the config
    public function __construct($uname, $upw) {
        $this->_caller = new GeneralApiCaller(NC_APP_ID, NC_APP_KEY, NC_API_PATH);
        $this->_uname = $uname;
        $this->_upw = $upw;
        $this->_p = array('user_name' => $uname, 'user_extpwd' => $upw);
    }

    /**
     * Use the api to get a list of networks visibile to the user
     *      
     * @return array
     * 
     */
    function listNetworks() {
        $params = $this->_p;
        $params['controller'] = 'NCNetworks';
        $params['action'] = 'listNetworks';
        return $this->_caller->sendRequest($params);
    }

    /**
     * Use the api to get a list of users with permissions on a network
     * 
     * @param type $NCapi
     * @return array
     */
    function listNetworkUsers($network) {
        $params = $this->_p;
        $params['controller'] = 'NCNetworks';
        $params['action'] = 'listNetworkUsers';
        $params['network_name'] = $network;
        return $this->_caller->sendRequest($params);
    }

    /**
     * check public status of a network
     * 
     * @param type $NCapi
     * @param type $network
     * @return type
     */
    function isNetworkPublic($network) {
        $params = $this->_p;
        $params['controller'] = 'NCNetworks';
        $params['action'] = 'isPublic';
        $params['network_name'] = $network;
        return $this->_caller->sendRequest($params);
    }

    /**
     * Check if the user has already previously logged in, and a session with the
     * user has already been established. 
     * 
     * Also checks to see if user has been remembered.
     * 
     * If so, the database is queried to make sure of the user's 
     * authenticity. Returns true if the session user is logged in.
     * 
     * @param $NCapi - an instance of the NC api caller
     * 
     */
    function checkLogin() {
        
        // default state is "guest"
        $g = "guest";
        $tim = time() + (3600 * 24 * 7);
        $firstname = $uname = $upw = $g;
        $lastname = $middlename = "";

        // check if a user has already been remembered
        if (isset($_SESSION['uname']) && isset($_SESSION['upw'])) {
            // Username and password have been set.        
            $uname = $_SESSION['uname'];
            $upw = $_SESSION['upw'];
            $firstname = $_SESSION['firstname'];
            $middlename = $_SESSION['middlename'];
            $lastname = $_SESSION['lastname'];
        } else {
            if (isset($_COOKIE['nc_uname']) && isset($_COOKIE['nc_upw'])) {
                $uname = $_COOKIE['nc_uname'];
                $upw = $_COOKIE['nc_upw'];
                $firstname = $_COOKIE['nc_firstname'];
                $middlename = $_COOKIE['nc_middlename'];
                $lastname = $_COOKIE['nc_lastname'];
            } else {
                $_SESSION['uname'] = $g;
                $_SESSION['upw'] = $g;
                $_SESSION['firstname'] = $g;
                $_SESSION['middlename'] = "";
                $_SESSION['lastname'] = "";
                $uname = $upw = $g;
                return false;
            }
        }
        
        // confirm the existence of the user using an API call        
        $apiparams = array('user_extpwd' => $upw);
        $userconfirmed = $this->_caller->sendReq($uname, "NCUsers", "confirm", $apiparams);        

        // update the cookies for logged-in users
        if ($userconfirmed) {
            $_SESSION['uname'] = $uname;
            $_SESSION['upw'] = $upw;
            $_SESSION['firstname'] = $firstname;
            $_SESSION['middlename'] = $middlename;
            $_SESSION['lastname'] = $lastname;
            setcookie("nc_uname", $uname, $tim, "/");
            setcookie("nc_firstname", $firstname, $tim, "/");
            setcookie("nc_middlename", $middlename, $tim, "/");
            setcookie("nc_lastname", $lastname, $tim, "/");
            if ((isset($_SESSION['remember']) && $_SESSION['remember'] == 1) || (isset($_COOKIE['nc_remember']) && $_COOKIE['nc_remember'] == 1)) {
                setcookie("nc_upw", $upw, $tim, "/");
                $_SESSION['remember'] = 1;
            }
            return true;
        }
        
        return false;
    }

    /**
     * check permission status of a user/network combination
     * 
     * @param type $network
     * @return type
     */
    function querySelfPermissions($network) {
        $params = $this->_p;
        $params['controller'] = 'NCUsers';
        $params['action'] = 'queryPermissions';
        $params['network_name'] = $network;
        $params['target_name'] = $this->_uname;
        return $this->_caller->sendRequest($params);
    }

    /**
     * get Network title and abstract from annotations
     * 
     * @param type $network
     * @return type
     */
    function getNetworkMetadata($network) {
        $params = $this->_p;
        $params['controller'] = 'NCNetworks';
        $params['action'] = 'getNetworkMetadata';
        $params['network_name'] = $network;
        return $this->_caller->sendRequest($params);
    }
    
    /**
     * get Network title and abstract from annotations
     * 
     * @param type $network
     * @return type
     */
    function getNetworkTitle($network) {
        $params = $this->_p;
        $params['controller'] = 'NCNetworks';
        $params['action'] = 'getNetworkTitle';
        $params['network_name'] = $network;
        return $this->_caller->sendRequest($params);
    }
       
    /**
     * get an array of all the classes defined for nodes in the network
     * 
     * @param type $network
     * @return type
     */
    function getNodeClasses($network) {
        $params = $this->_p;
        $params['controller'] = 'NCOntology';
        $params['action'] = 'getNodeOntology';
        $params['network_name'] = $network;
        return $this->_caller->sendRequest($params);
    }

    /**
     * get an array of all the class defined for links in the network 
     * 
     * @param type $network
     * @return type
     */
    function getLinkClasses($network) {
        $params = $this->_p;
        $params['controller'] = 'NCOntology';
        $params['action'] = 'getLinkOntology';
        $params['network_name'] = $network;
        return $this->_caller->sendRequest($params);
    }

    
    function getNetworkActivityLogSize($network) {
        $params = $this->_p;
        $params['controller'] = 'NCNetworks';
        $params['action'] = 'getActivityLogSize';
        $params['network_name'] = $network;
        return $this->_caller->sendRequest($params);
    }
    
    function getComments($network, $rootid) {
        $params = $this->_p;
        $params['controller'] = 'NCAnnotations';
        $params['action'] = 'getComments';
        $params['network_name'] = $network;
        $params['root_id'] = $rootid;
        return $this->_caller->sendRequest($params);
    }
    
    function getAllNodes($network) {
        $params = $this->_p;
        $params['controller'] = 'NCGraphs';
        $params['action'] = 'getAllNodes';
        $params['network_name'] = $network;
        return $this->_caller->sendRequest($params);
    }
    
    function getAllLinks($network) {        
        $params = $this->_p;        
        $params['controller'] = 'NCGraphs';        
        $params['action'] = 'getAllLinks';        
        $params['network_name'] = $network;        
        return $this->_caller->sendRequest($params);
    }
}

?>
