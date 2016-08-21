<?php
/**
 * Main page - splash
 * 
 */
?>

</div>
<div class="jumbotron">
    <div class="container">
        
    </div>
</div>

<div class="row">   
    <div class="container">
        <div class="col-sm-8">
            <h1>Existing Networks</h1>
            <?php
            // get info on existing and viewable networks
            // display each using the code in ui-network-splash            
            $mynetworks = $NCapi->listNetworks();                          
            //print_r($mynetworks);
            for ($x = 0; $x < count($mynetworks); $x++) {
                $thisn = $mynetworks[$x];
                $networkid = $mynetworks[$x]['network_id'];
                $networkname = $mynetworks[$x]['network_name'];
                $networktitle = $mynetworks[$x]['title'];
                $networkdesc = $mynetworks[$x]['description'];
                include "nc-components/ui-network-splash.php";
            }
            ?>

        </div>
    </div>
</div>

<div>

