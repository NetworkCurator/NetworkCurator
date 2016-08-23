<?php
/*
 * Element showing one network on the main/index/front page.
 * It's like a card or media element. (new name?)
 * 
 */
?>

<?php
$nowlink = "?page=network&network=$networkname";
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
