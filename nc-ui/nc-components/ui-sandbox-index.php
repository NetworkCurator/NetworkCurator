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

        <p>Sandboxes are areas to experiment with creating content.</p>

        <h2>Networks</h2>

        <p>NetworkCurator can create networks through the graph page or from data files. 
            Use this sandbox to create compatible data files from spreadsheet tables.</p>

        <div class="thumbnail nc-thumbnail">
            <a href="?page=sandbox&sandbox=preparenetwork">        
                <h5 class="list-group-item-heading">Network prep</h5>
                <p class="list-group-item-text">Prepare a minimal network definition from lists of nodes and links.</p>
            </a>            
        </div>


        <h2>Markdown</h2>

        <p>Markdown is a markup language. Use it on the network pages to 
            convert plain text into styled html.</p>

        <div class="thumbnail nc-thumbnail">
            <a href="?page=sandbox&sandbox=markdown">
                <h5>markdown</h5>
                <p>Practice using markdown to format descriptions and comments.</p>
            </a>            
        </div>                

        <h2>Makealive</h2>

        <p>Makealive is an extension of markdown. Use it to create rich content, for example
            data charts.</p>                

        <p>Each of the sandbox pages below contains a form. Input your data into the form and 
            the page will generate makealive code along a visual representation. 
            Use the code within any content box (e.g. abstract or comment) on the NetworkCurator site.</p>

        <div class="row">
            <div class="col-sm-6">
                <div class="thumbnail nc-thumbnail">      
                    <a href="?page=sandbox&sandbox=barplot01">
                        <h5>barplot01</h5>
                        <p>Create a bar plot with vertical bars.</p>                    
                    </a>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="thumbnail nc-thumbnail"> 
                    <a href="?page=sandbox&sandbox=barplot02'">
                        <h5>barplot02</h5>
                        <p>Create a bar plot with horizontal bars.</p>                    
                    </a>
                </div>
            </div>            
            <div class="col-sm-6">
                <div class="thumbnail nc-thumbnail"">       
                    <a href="?page=sandbox&sandbox=scatterplot01">
                        <h5>scatterplot01</h5>
                        <p>Create a scatter plot.</p>
                    </a>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="thumbnail nc-thumbnail"">       
                    <a href="?page=sandbox&sandbox=venn01">
                        <h5>venn01</h5>
                        <p>Create a venn diagram.</p>
                    </a>           
                </div>
            </div>
            <div class="col-sm-6">
                <div class="thumbnail nc-thumbnail"">       
                    <a href="?page=sandbox&sandbox=venn02">
                        <h5>venn02</h5>
                        <p >Create a venn diagram with a custom set.</p>
                    </a>           
                </div>
            </div>
        </div>      

    </div>
</div>
