<?php
/*
 * Element showing one network on the splash page
 */
?>

<?php
$nowlink = "?page=network&network=$networkname";
if ($_SESSION['uid'] == "admin") {
    $nowlink = "?page=admin&network=$networkname";
}
?>
<div class="media nc-mt-20">    
    <div class="media-body">
        <h3 class="media-heading">
            <a href='<?php echo $nowlink; ?>'>
                <?php echo $networktitle ?>
            </a>
        </h3>
        <?php echo $networkdesc; ?>
    </div>
</div>

<?php
unset($nowlink);
?>
