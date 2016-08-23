<?php
/*
 * Navigation bar at the top of each page
 */
?>

<nav class="navbar navbar-default nc-navbar navbar-fixed-top">
    <div class="container">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header">      
            <a class="navbar-brand" href="?page=front"><?php echo ncSiteName(); ?></a>        
            <p class="navbar-text"><a href="#" id="nc-nav-network-title"></a></p>            
        </div>
        <!-- Collect the nav links, forms, and other content for toggling -->
        <div class="collapse navbar-collapse">            
            <ul class="nav navbar-nav navbar-right">   
                <p class="navbar-text"><b><?php echo ncUserFullname(); ?></b></p>
                <li>
                    <?php
                    if (ncIsUserSignedIn()) {
                        echo "<a href='?page=logout'>Log out</a>";
                    } else {
                        echo "<a href='?page=login'>Log in</a>";
                    }
                    ?>            
                </li>         
            </ul>
        </div>
    </div>
</nav>
