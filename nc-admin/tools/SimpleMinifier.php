<?php

/*
 * Simple utility to "minify" code
 *  
 * This uses regex to remove some of the redundant characters in js and css files.
 * It is not a full-featured minifier. But it does not have any dependencies and 
 * it can remove bulky comments and spurious line breaks. 
 * So it is useful if one can't rely on a proper minifier being installed.
 * 
 */

class SimpleMinifier {

    /**
     * Constructor. Nothing happens     
     */
    public function __construct() {
        
    }

    /**
     * Matches a regex and replaces with an empty string
     * @param type $data
     * @param type $pattern
     * @return type
     */
    private function removePattern($data, $pattern, $replacement) {
        return preg_replace($pattern, $replacement, $data);
    }

    /**
     * removes inline comments from a code string
     * @param type $data
     * @return string
     */
    public function removeInlineComments($data) {
        // regex for two slashes follow by anything except a new line        
        return $this->removePattern($data, '~//[^\n\r]*~', '');
    }

    /**
     * removes comment blocks from a code string
     * @param string $data
     * @return string
     */
    public function removeCommentBlocks($data) {
        // regex for startings slash-asterisk, middle section, and ending asterisk-slash
        return $this->removePattern($data, '~/\*.*?\*/~s', '');
    }

    /**
     * Remove some of the redundant whitespace in a string. That includes two spaces 
     * in a row, line breaks in a row, line breaks after an open brace, etc.
     * 
     * @param string $data
     * 
     * input string
     * 
     * @return string
     * 
     * a new string with multi-spaces turned into a single space
     */
    public function removeWhitespace($data) {
        // regex to remove several spaces in a row with just one
        $temp = $data;
        $temp = $this->removePattern($temp, '~[ ]+~', ' ');
        $temp = $this->removePattern($temp, '~\n ~', "\n");        
        $temp = $this->removePattern($temp, '~ \n~', "\n");
        $temp = $this->removePattern($temp, '~\n+~', "\n");
        $temp = $this->removePattern($temp, '~\n \n~', "\n");
        $temp = $this->removePattern($temp, '~^\n~', "");
        $temp = $this->removePattern($temp, '~}\n}~', "}}");
        $temp = $this->removePattern($temp, '~{\n~', "{");
        return $temp;
    }

    /**
     * Remove the linebreak character after semicolons.
     * 
     * @param string $data
     * 
     * input string
     * 
     * @return string
     * 
     * a new string 
     */
    public function removeSemicolonBreak($data) {
        // regex to remove a linebreak after a semicolor-linebreak
        return $this->removePattern($data, '~;\n~', ';');
    }
    
    /**
     * perform all the minifier actions on the input data
     * 
     * @param string $data
     */
    public function minify($data) {
        $data = $this->removeCommentBlocks($data);
        $data = $this->removeInlineComments($data);
        $data = $this->removeWhitespace($data);
        $data = $this->removeSemicolonBreak($data);
        return $data;
    }

}

?>
