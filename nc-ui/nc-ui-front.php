<?php
/**
 * Main/Front page
 * 
 */
include_once "nc-ui/nc-components/ui-front-jumbo.php";

// get info on existing and viewable networks
// display each using the code in ui-network-card            
$mynetworks = $NCapi->listNetworks();
//print_r($mynetworks);
// get a set of elements that need markdown treatment
$md = [];
?>

<div class="row nc-mt-20">
    <div class="container">
        <div class="col-sm-12">
            <?php
            if ($uid === "admin") {
                echo "<h4>Admin links</h4>";
                echo "<div class='btn-toolbar'>";
                echo "<a class='btn btn-success' role='button' href='?page=admin&new=network'>Create New Network</a>";
                echo "<a class='btn btn-success' role='button' href='?page=admin&new=user'>Create New User</a>";
            } echo "</div>";
            ?>
        </div>
    </div>
</div>


<div class="row">   
    <div class="container">
        <div class="col-sm-8">
            <h1>Existing Networks</h1>
            <?php
            for ($x = 0; $x < count($mynetworks); $x++) {
                $netid = $mynetworks[$x]['id'];
                $netname = $mynetworks[$x]['name'];
                $nettitle = $mynetworks[$x]['title'];
                $nettitleid = $mynetworks[$x]['title_id'];
                $netabstract = $mynetworks[$x]['abstract'];
                $netabstractid = $mynetworks[$x]['abstract_id'];
                include "nc-components/ui-network-card.php";
                $md[$netabstractid] = $netabstract;
                $md[$nettitleid] = $nettitle;
            }
            ?>

        </div>
    </div>
</div>




<?php    
echo ncScriptObject("nc.md", $md); 
?>


