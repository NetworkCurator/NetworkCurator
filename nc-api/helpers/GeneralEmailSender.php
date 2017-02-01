<?php

/*
 * Simple utilities to prepare/send emails
 *  
 * The general idea is for the class to have access to a directory with email templates.
 * The class then provides means to read a template, fill in missing values, and send
 * an email to a target address.
 * 
 */

class GeneralEmailSender {

// directory holding email templates
    private $_tdir;
    private $_sender;

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
    public function __construct($templatedir, $senderaddress) {
        if (!file_exists($templatedir)) {
            throw new Exception("Template directory does not exist");
        }
        $this->_tdir = $templatedir;
        $this->_sender = $senderaddress;
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
     * Array of parameters used to fill-in the email template
     * 
     * @param type $address
     * 
     * Target email address
     * 
     * 
     */
    public function sendEmail($template, $params, $address) {

        // fetch data from template
        $tfile = $this->_tdir . "/" . $template . ".txt";
        if (!file_exists($tfile)) {
            throw new Exception("Email template file does not exist");
        }
        $content = file_get_contents($tfile);

        // update the contents of the email usign the data from the $params
        foreach ($params as $key => $value) {
            $content = str_replace("[" . $key . "]", $value, $content);
        }

        // extract the subject line and email body
        $content = explode("\n", $content);        
        $subject = substr(array_shift($content), 1);         
        $content = trim(implode("\n", $content))."\n";        

        echo "\n";
        echo "Sending to: $address \n";
        echo "Sending from: ". $this->_sender."\n";
        echo "Subject: " . $subject . "\n";
        echo "Content: " . $content;

        // prepare email headers
        $headers = 'From: '. $this->_sender. " \r\n" .
                'Reply-To: '.$this->_sender . " \r\n" .
                'X-Mailer: PHP/' . phpversion();
        print_r($headers);
        $result = mail($address, $subject, $content, $headers);
        
        echo "result was: ".$result."\n";
    }

}

?>
