<?php
/*
 * Element showing one network on the main/index/front page.
 * It's like a card or media element. (new name?)
 * 
 */
?>

<?php
$nowlink = "?page=network&network=$netname";
?>
<div class="media nc-mt-20">    
    <div class="media-body">
        <h3 class="media-heading">
            <a href='<?php echo $nowlink; ?>' class="nc-md" val="<?php echo $nettitleid; ?>"></a>            
        </h3>
        <div class="nc-md" val="<?php echo $netabstractid; ?>"></div>        
    </div>
</div>

<?php
unset($nowlink);
?>
