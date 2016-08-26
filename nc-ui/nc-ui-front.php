<?php
/**
 * Main/Front page
 * 
 */
include_once "nc-ui/nc-components/ui-front-jumbo.php";
?>


<div class="row">   
    <div class="container">
        <div class="col-sm-8">
            <h1>Existing Networks</h1>
<?php
// get info on existing and viewable networks
// display each using the code in ui-network-card            
//echo "A";
$mynetworks = $NCapi->listNetworks();
//echo "B";
//print_r($mynetworks);

for ($x = 0; $x < count($mynetworks); $x++) {    
    $networkid = $mynetworks[$x]['network_id'];    
    $networkname = $mynetworks[$x]['network_name'];    
    $networktitle = $mynetworks[$x]['network_title'];
    $networkabstract = $mynetworks[$x]['network_abstract'];
    include "nc-components/ui-network-card.php";
}
?>

        </div>
    </div>
</div>


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
