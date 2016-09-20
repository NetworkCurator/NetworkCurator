<?php

/*
 * Class provides simple timing/profiling functions
 *  
 * 
 */

class NCTimer {

    private $_times = [];
    private $_tlabs = [];

    public function __construct() {
        
    }
    
    /**
     * record a time
     */
    public function recordTime($lab) {
        $this->_times[] = microtime(true);
        $this->_tlabs[] = $lab;
    }

    /**
     * get a string with a log of time intervals between time points
     */
    public function showTimes() {        
        $ans = "\n";
        for ($i = 1; $i < count($this->_times); $i++) {
            $t2 = $this->_times[$i];
            $t1 = $this->_times[$i - 1];
            $l2 = $this->_tlabs[$i];
            $l1 = $this->_tlabs[$i - 1];
            $ans .= "[$l1] to [$l2] ...\t" . round(($t2 - $t1) * 1000, 2) . "\n";
        }
        $t0 = $this->_times[0];
        $t9 = $this->_times[count($this->_times) - 1];
        $ans .= "[total time] ...\t" . round(($t9 - $t0) * 1000, 2) . "\n";
        $ans .= "\n";
        $this->_times = [];
        $this->_tlabs = [];
        return $ans;
    }


}

?>
