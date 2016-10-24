<?php
/*
 * Display a list of sandbox pages, or go directly to a particular one.
 * 
 */

// is the request for a particular sandbox?
if ($sandbox != "index" && $sandbox != '') {
    $sandfile = "nc-ui/nc-components/ui-sandbox-generic.php";        
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
        
        <h2>Makealive</h2>

        <p><code>Makealive</code> is an extension of markdown. Use it to create rich content, for example
            data charts.</p>                
        
        <p>Each of the sandbox pages below contains a form. Input your data into the form and 
           the page will generate <code>makealive</code> code along a visual representation. 
           Use the code within any content box (e.g. abstract or comment) on the NetworkCurator site.</p>
                
        <div class="list-group nc-mt-10">
            <a href="?page=sandbox&sandbox=barplot01" class="list-group-item list-group-item-action">
                <h5 class="list-group-item-heading">barplot01</h5>
                <p class="list-group-item-text">Create bar plots with vertical bars.</p>
            </a>
            <a href="?page=sandbox&sandbox=barplot02" class="list-group-item list-group-item-action">
                <h5 class="list-group-item-heading">barplot02</h5>
                <p class="list-group-item-text">Create bar plots with horizontal bars.</p>
            </a>
            <a href="?page=sandbox&sandbox=scatterplot01" class="list-group-item list-group-item-action">
                <h5 class="list-group-item-heading">scatterplot01</h5>
                <p class="list-group-item-text">Create simple scatter plots.</p>
            </a>           
            <a href="?page=sandbox&sandbox=venn01" class="list-group-item list-group-item-action">
                <h5 class="list-group-item-heading">venn01</h5>
                <p class="list-group-item-text">Create a venn diagram.</p>
            </a>           
            <a href="?page=sandbox&sandbox=venn02" class="list-group-item list-group-item-action">
                <h5 class="list-group-item-heading">venn02</h5>
                <p class="list-group-item-text">Create a venn diagram with a custom set.</p>
            </a>           
        </div>



    </div>
</div>
