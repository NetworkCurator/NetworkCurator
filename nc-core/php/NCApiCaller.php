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
    
    // constructor; creates an instance of the GeneralApiCaller 
    // with the app id, key, and path defined in the config
    public function __construct($uid, $upw) {
        $this->_caller = new GeneralApiCaller(NC_APP_ID, NC_APP_KEY, NC_API_PATH);
        $this->_uid = $uid;
        $this->_upw = $upw;
    }

    /**
     * Use the api to get a list of networks visibile to the user
     *      
     * @return array
     * 
     */
    function listNetworks() {
        $params = array('user_id' => $this->_uid, 'user_extpwd' => $this->_upw,
            'controller' => 'NCNetworks', 'action' => 'listNetworks');
        return $this->_caller->sendRequest($params);
    }

    /**
     * Use the api to get a list of users with permissions on a network
     * 
     * @param type $NCapi
     * @return array
     */
    function listNetworkUsers($network) {
        $params = array('user_id' => $this->_uid, 'user_extpwd' => $this->_upw,
            'controller' => 'NCNetworks', 'action' => 'listNetworkUsers',
            'network_name' => $network);
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
        $params = array('user_id' => $this->_uid, 'user_extpwd' => $this->_upw,
            'controller' => 'NCNetworks', 'action' => 'isPublic',
            'network_name' => $network);
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
            if ($userconfirmed['success'] === true) {
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
        }

        return false;
    }

    /**
     * check permission status of a user/network combination
     * 
     * @param type $network
     * @return type
     */
    function queryPermissions($network) {        
        $params = array('user_id' => $this->_uid, 'user_extpwd'=> $this->_upw,
            'controller' => 'NCUsers', 'action' => 'queryPermissions',
            'network_name' => $network, 'target_id' => $this->_uid);
        return $this->_caller->sendRequest($params);
    }

    
    /**
     * get Network title and abstract from annotations
     * 
     * @param type $network
     * @return type
     */
    function getNetworkMetadata($network) {        
        $params = array('user_id' => $this->_uid, 'user_extpwd'=> $this->_upw,
            'controller' => 'NCNetworks', 'action' => 'getNetworkMetadata',
            'network_name' => $network);      
        return $this->_caller->sendRequest($params);
    }
       
}

?>
