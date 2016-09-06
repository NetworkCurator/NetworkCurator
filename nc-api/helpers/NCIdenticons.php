<?php

/*
 * Class to create identicon images (square images with some blotches)
 * 
 */

class NCIdenticons {

    private $_colors = [
        0 => [234, 248, 191],
        1 => [0, 105, 146],
        2 => [242, 67, 51],
        3 => [114, 155, 121],
        4 => [247, 179, 43]];
    private $_size = 48;
    private $_bwidth = 6;
    private $_smalladjust = [0.8, 1.0, 1.2];
    private $_largeadjust = [0.4, 0.6, 0.8, 1.0, 1.2, 1.4];

    public function __construct() {
        
    }

    public function getIdenticon() {

        // create a square image with a fixed background
        $img = imagecreate($this->_size, $this->_size);
        $col = $this->_colors[rand(0, count($this->_colors) - 1)];
        $imgbg = imagecolorallocate($img, 255, 255, 255);

        // pick two colors for the border
        //$bcol = $this->adjustCol($img, $col, $this->_largeadjust);
        $bcols = [];
        for ($i = 0; $i < 4; $i++) {
            $temp = $this->adjustCol($img, $col, $this->_smalladjust);
            $bcols[$i] = imagecolorallocate($img, $temp[0], $temp[1], $temp[2]);
        }
        // pick four colors for the inner boxes
        //$icol = $this->adjustCol($img, $col, $this->_largeadjust);
        $icols = [];
        for ($i = 0; $i < 4; $i++) {
            $temp = $this->adjustCol($img, $col, $this->_largeadjust);
            $icols[$i] = imagecolorallocate($img, $temp[0], $temp[1], $temp[2]);
        }

        // draw boxes in the middle
        // choose a random box, make the next box equal
        $ianchor = rand(0, 3);
        $inext = ($ianchor+1)%4;        
        $icols[$inext] = $icols[$ianchor];
        for ($i = 0; $i < 4; $i++) {
            $img = $this->drawBox($img, $icols[$i], $i);
        }
        // draw border lines on the outside
        $sides = [0,1,2,3];
        shuffle($sides);
        for ($i=0; $i<4; $i++) {
            $img = $this->drawBorder($img, $bcols[$i], $sides[$i]);
        }
                
        return $img;
    }

    /**
     * adjust a color by a given ratio
     * 
     * @param type $col
     * @param type $ratio
     * an array of adjustment ratios. (One will be chosen at random)
     * 
     */
    private function adjustCol($img, $col, $ratio) {
        // choose a ratio from the given options
        $ratio = $ratio[rand(0, count($ratio) - 1)];
        for ($i = 0; $i < 3; $i++) {
            $col[$i] = min([255, (int) ($col[$i] * $ratio)]);
        }
        return $col;
    }

    /**
     * draws two horizontal bars at the edges of the icon
     * 
     * @param type $img
     * @param type $col
     */
    private function drawBorder($img, $col, $side) {
        $s = $this->_size;
        $w = $this->_bwidth;
        if ($side == 0) {
            imagefilledrectangle($img, 0, 0, $s, $w, $col);
        } else if ($side == 1) {
            imagefilledrectangle($img, 0, $s - $w, $s, $s, $col);
        } else if ($side == 2) {
            imagefilledrectangle($img, 0, 0, $w, $s, $col);
        } else {
            imagefilledrectangle($img, $s - $w, 0, $s, $s, $col);
        }
        return $img;
    }

    /**
     * 
     * @param image $img
     * @param color $col
     * @param integer $corner
     */
    private function drawBox($img, $col, $corner) {
        $s = $this->_size;
        $h = $s / 2;
        if ($corner == 0) {
            imagefilledrectangle($img, 0, 0, $h, $h, $col);
        } else if ($corner == 1) {
            imagefilledrectangle($img, $h + 1, 0, $s, $h, $col);
        } else if ($corner == 2) {
            imagefilledrectangle($img, $h + 1, $h + 1, $s, $s, $col);
        } else {
            imagefilledrectangle($img, 0, $h + 1, $h, $s, $col);            
        }
        return $img;
    }

}

?>
