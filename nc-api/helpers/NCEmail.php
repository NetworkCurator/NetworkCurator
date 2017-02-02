<?php

include_once "GeneralEmailSender.php";

//include_once "../controllers/NCUsers.php";

/*
 * Prepare/send emails relevant for the NetworkCurator 
 *  
 * This uses GeneralEmailSender to send emails. For example, this class can send update 
 * emails to all curators associated with a network. 
 * 
 */

class NCEmail extends NCDB {

    // instance of GeneralEMailSender
    private $_ges;

    /**
     * Constructor. Creates instance of a generic email-sending utility.
     *      
     * @param path $templatedir
     * 
     * path of data directory holding email template files
     * 
     * @param string $senderaddress
     * 
     * email address of the email sender
     * 
     * @throws Exception
     * 
     * when data directory is mis-specified
     * 
     */
    public function __construct($db) {
        parent::__construct($db);
        $this->_ges = new GeneralEmailSender(dirname(__FILE__) . "/../templates",
                        '"' . NC_SITE_DOMAIN . '" <admin@' . NC_SITE_DOMAIN . '>');
    }

    /**
     * Workhorse of the class. Prepares and send an email.
     * 
     * @param string $template
     * 
     * Name of file holding the email template
     * 
     * @param array $params
     * 
     * Array of parameters used to fill-in the email template.
     * Parameters FIRSTNAME, USERID, SITENAME, SITEURL are filled in by the function 
     * and do not need to be specified.
     * 
     * @param array $targetusers
     * 
     * Target email address
     * 
     * 
     */
    public function sendEmailToUsers($template, $params, $targetusers) {

        // find all target users and email addresses
        $sql = "SELECT user_id, user_firstname, user_email FROM " . NC_TABLE_USERS . " WHERE ";
        $sqltargets = [];
        $sqldata = [];
        for ($i = 0; $i < count($targetusers); $i++) {
            $x = sprintf("%'.06d", $i);
            $sqltargets[] = " user_id=:field_$x ";
            $sqldata["field_$x"] = $targetusers[$i];
        }
        $sql .= implode("OR", $sqltargets);
        $stmt = $this->qPE($sql, $sqldata);

        // loop through results and send email to each target user
        while ($row = $stmt->fetch()) {
            $emailparams = $params;
            $emailparams['FIRSTNAME'] = $row['user_firstname'];
            $emailparams['USERID'] = $row['user_id'];
            $emailparams['SITENAME'] = NC_SITE_NAME;
            $emailparams['SITEURL'] = NC_SITE_URL;
            if (strlen($row['user_email']) > 2) {
                // send an email (only if the field has three or more characters)
                $this->_ges->sendEmail($template, $emailparams, $row['user_email']);
            }
        }
    }

    /**
     * Send email to a group of users determined by the 
     * 
     * @param string $template
     * 
     * Name of file with email template
     * 
     * @param array $params
     * 
     * Set of parameters to fill-in email template
     * 
     * @param string $netid
     * 
     * id for a network, e.g. Wxxxxx
     * 
     * $param array $users
     * 
     * ids of additional users to send to 
     * (e.g. if email should go to curators and a set of users affected by an event)
     * 
     */
    public function sendEmailToCurators($template, $params, $netid, $users = []) {

        // find all the curators associated with the network id
        $sql = "SELECT user_id FROM " . NC_TABLE_PERMISSIONS . " WHERE network_id = ? AND
            permissions = " . NC_PERM_CURATE;
        $stmt = $this->qPE($sql, [$netid]);
        $targets = [];
        while ($row = $stmt->fetch()) {
            $targets[] = $row['user_id'];
        }

        // add additional targets into the $curators array
        foreach ($users as $val) {
            if (!in_array($val, $targets)) {
                $targets[] = $val;
            }
        }

        // send email to users
        $this->sendEmailToUsers($template, $params, $targets);
    }

}

?>
