<?php
/*
 * Display a list of sandbox pages, or go directly to a particular one.
 * 
 */

// is the request for a particular sandbox?
if ($sandbox != "index") {
    $sandfile = "nc-ui/nc-components/ui-sandbox-$sandbox.php";    
    if (file_exists($sandfile)) {
        include_once $sandfile;
        exit();
    }
}

?>

<div class="row">
    <div class="col-sm-8">
        <h1>Sandboxes</h1>

        <p>Sandboxes are safe areas to experiment with creating content.</p>

        <h2>Markdown</h2>
        
        <p>Markdown is a markup language. Use it on the network pages to 
           convert plain text into styled html.</p>
        
        <div class="list-group nc-mt-10">
            <a href="?page=sandbox&sandbox=markdown" class="list-group-item list-group-item-action">
                <h5 class="list-group-item-heading">markdown</h5>
                <p class="list-group-item-text">Practice using markdown to format descriptions and comments.</p>
            </a>            
        </div>
        
        <h2>Markdown-alive</h2>

        <p>Markdown-alive is an extension of markdown. Use it to create rich content, for example
            data charts.</p>
                
        <div class="list-group nc-mt-10">
            <a href="?page=sandbox&sandbox=barplot001" class="list-group-item list-group-item-action">
                <h5 class="list-group-item-heading">barplot001</h5>
                <p class="list-group-item-text">Create simple bar plots.</p>
            </a>
            <a href="?page=sandbox&sandbox=scatterplot001" class="list-group-item list-group-item-action">
                <h5 class="list-group-item-heading">scatterplot001</h5>
                <p class="list-group-item-text">Create simple scatter plots.</p>
            </a>           
        </div>



    </div>
</div>
