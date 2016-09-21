<?php

/**
 * Multi-purpose api-caller.
 * 
 * The caller remembers an application id/key and api url.
 * It provides an encrypt-and-send function to ask the api for some answer.
 * 
 * This code is modeled on a tutorial here: (with modifications)
 * http://code.tutsplus.com/tutorials/creating-an-api-centric-web-application--net-23417
 * 
 */
class GeneralApiCaller {

    private $_app_id;
    private $_app_key;
    private $_api_url;

    // constructor; sets the private variables in the class
    public function __construct($app_id, $app_key, $api_url) {
        $this->_app_id = $app_id;
        $this->_app_key = $app_key;
        $this->_api_url = $api_url;
    }

    /**
     * Generic function to send a request to the api
     * 
     * @param type $params
     * 
     * array of parameters. The parameters must include "controller" and "action"
     * 
     * @return type
     * 
     * returns the answer from the api
     * 
     * @throws Exception 
     *      
     */
    public function sendRequest($params) {
        
        // encrypt the request                        
        $request = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $this->_app_key, json_encode($params), MCRYPT_MODE_ECB));

        // redefine the parameters array so that it will be accepted by the api        
        $params = array();
        $params['request'] = $request;
        $params['app_id'] = $this->_app_id;

        //initialize and setup the curl handler
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $this->_api_url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handle, CURLOPT_POST, count($params));
        curl_setopt($handle, CURLOPT_POSTFIELDS, $params);        
        $ans = curl_exec($handle);        
        if ($ans === false) {
            printf("cUrl error (#%d): %s<br>\n", curl_errno($handle), htmlspecialchars(curl_error($handle)));
        } 
        
        //echo "GAC: ".$ans."\n";
        $rawans = $ans;
        $ans = json_decode($ans, true);        
        //echo "ABC: ".$ans."\n";
                
        //check if we're able to json_decode the result correctly
        if ($ans == false || isset($ans['success']) == false) {            
            throw new Exception('Request was not correct in GeneralApiCaller: '.$rawans);
        }
        
        // if there was an error in the request, throw an exception
        if ($ans['success'] == false) {
            throw new Exception($ans['errormsg']);
        }
        
        // if reached here, the data component should have the result
        return $ans['data'];
    }

    /**
     * Send a request to the api. Also see sendRequest($params)
     * 
     * @param type $controller
     * 
     * string, specification of the controller in the API
     * 
     * @param type $action
     * 
     * string, specification of the action to be performed by the API
     * 
     * @param type $params
     * 
     * array, additional parameters to be sent to the API
     * 
     * @return type 
     * 
     */
    public function sendReq($user_id, $controller, $action, $params) {
        $params['user_id'] = $user_id;
        $params['controller'] = $controller;
        $params['action'] = $action;        
        return $this->sendRequest($params);
    }

}

?>
