<?php
/**
 * Sandbox 
 * 
 * Helps to create mdalive code for bar plots
 * 
 */
?>



<div class="row">
    <div class="container">
        <h1>Sandbox: barplot02</h1>

        <p>This sandbox helps generate simple bar charts from raw data.</p>                   
        
        <h4 class="nc-mt-15">Required parameters</h4>
        <div id="nc-sandbox-required" class="nc-parameters form-horizontal" val="barplot02">
            <?php
            include_once "sandbox-components/ui-sandbox-titlelabs.php";
            ?>           
            <div class="form-group" val="data" colnames="name value fill">                
                <label class="col-sm-2 control-label">Data<br/><br/>name<br/>value<br/>color</label>                
                <div class="col-sm-7">                    
                    <textarea class="form-control" rows="8"></textarea>                     
                </div>
                <div class="col-sm-3 nc-tips">
                    <p>You can <b>paste-in data</b> from a spreadsheet here. Columns should contain names, values, and fill colors.</p>
                    <p>To enter data manually, press the <b>space-bar twice</b> to generate a tab between columns.</p>
                </div>
                <div id="nc-temp"></div>
            </div>
        </div> 

        <h4 class="nc-sandbox-optional">Optional parameters <span><span class="caret"></span></span></h4>
        <div id="nc-sandbox-optional" class="nc-parameters form-horizontal">
            <?php
            $defaults = [300, 180, 70, 20, 20, 70];
            include_once "sandbox-components/ui-sandbox-sizemargin.php";
            $defaults = ["-3.7em", "-2.2em", "-3em"];
            include_once "sandbox-components/ui-sandbox-offsets.php";
            include_once "sandbox-components/ui-sandbox-barpadding.php";
            ?>            
        </div>                        

    </div>
</div>

<?php
$mdaside = "(Use this code in an abstract, description, or comment)";
include_once "ui-sandbox-md.php";
?>

