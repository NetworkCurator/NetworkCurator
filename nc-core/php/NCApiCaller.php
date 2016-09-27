<?php

/**
 * Class provides an object that is capable of sending requests to the nc-api
 * 
 * 
 */
class NCApiCaller {

    private $_caller;
    private $_uid;
    private $_upw;
    // _p will be initialized as array with userid and userextpwd pre-filled
    private $_p;

    // constructor; creates an instance of the GeneralApiCaller 
    // with the app id, key, and path defined in the config
    public function __construct($uid, $upw) {
        $this->_caller = new GeneralApiCaller(NC_APP_ID, NC_APP_KEY, NC_API_PATH);
        $this->_uid = $uid;
        $this->_upw = $upw;
        $this->_p = array('user_id' => $uid, 'user_extpwd' => $upw);
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
        $params['network'] = $network;
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
        $params['network'] = $network;
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
        $firstname = $uid = $upw = $g;
        $lastname = $middlename = "";

        // check if a user has already been remembered
        if (isset($_SESSION['uid']) && isset($_SESSION['upw'])) {
            // Username and password have been set.        
            $uid = $_SESSION['uid'];
            $upw = $_SESSION['upw'];
            $firstname = $_SESSION['firstname'];
            $middlename = $_SESSION['middlename'];
            $lastname = $_SESSION['lastname'];
        } else {
            if (isset($_COOKIE['nc_uid']) && isset($_COOKIE['nc_upw'])) {
                $uid = $_COOKIE['nc_uid'];
                $upw = $_COOKIE['nc_upw'];
                $firstname = $_COOKIE['nc_firstname'];
                $middlename = $_COOKIE['nc_middlename'];
                $lastname = $_COOKIE['nc_lastname'];
            } else {
                $_SESSION['uid'] = $g;
                $_SESSION['upw'] = $g;
                $_SESSION['firstname'] = $g;
                $_SESSION['middlename'] = "";
                $_SESSION['lastname'] = "";
                $uid = $upw = $g;
                return false;
            }
        }
        
        // confirm the existence of the user using an API call        
        $apiparams = array('user_extpwd' => $upw);
        $userconfirmed = $this->_caller->sendReq($uid, "NCUsers", "confirm", $apiparams);        

        // update the cookies for logged-in users
        if ($userconfirmed) {
            $_SESSION['uid'] = $uid;
            $_SESSION['upw'] = $upw;
            $_SESSION['firstname'] = $firstname;
            $_SESSION['middlename'] = $middlename;
            $_SESSION['lastname'] = $lastname;
            setcookie("nc_uid", $uid, $tim, "/");
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
        $params['network'] = $network;
        $params['target'] = $this->_uid;
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
        $params['network'] = $network;
        return $this->_caller->sendRequest($params);
    }
    
    /**
     * get title, abstract, content associated with an object
     * 
     * @param type $network
     * @param type $object
     * @return type
     */
    function getSummary($network, $object) {
        $params = $this->_p;
        $params['controller'] = 'NCAnnotations';
        $params['action'] = 'getSummary';
        $params['network'] = $network;
        $params['root_id'] = $object;
        $params = array_merge($params, ['name'=>1, 'title'=>1, 'abstract'=>1, 'content'=>1]);
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
        $params['network'] = $network;
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
        $params['network'] = $network;
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
        $params['network'] = $network;
        return $this->_caller->sendRequest($params);
    }

    
    function getNetworkActivityLogSize($network) {
        $params = $this->_p;
        $params['controller'] = 'NCNetworks';
        $params['action'] = 'getActivityLogSize';
        $params['network'] = $network;
        return $this->_caller->sendRequest($params);
    }
    
    function getComments($network, $rootid) {
        $params = $this->_p;
        $params['controller'] = 'NCAnnotations';
        $params['action'] = 'getComments';
        $params['network'] = $network;
        $params['root_id'] = $rootid;
        return $this->_caller->sendRequest($params);
    }
    
    function getAllNodes($network) {
        $params = $this->_p;
        $params['controller'] = 'NCGraphs';
        $params['action'] = 'getAllNodes';
        $params['network'] = $network;
        return $this->_caller->sendRequest($params);
    }
    
    function getAllLinks($network) {        
        $params = $this->_p;        
        $params['controller'] = 'NCGraphs';        
        $params['action'] = 'getAllLinks';        
        $params['network'] = $network;        
        return $this->_caller->sendRequest($params);
    }
}

?>
