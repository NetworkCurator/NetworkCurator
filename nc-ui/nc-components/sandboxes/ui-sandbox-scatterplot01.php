<?php
/**
 * Sandbox
 * 
 * Helps to create mdalive code for bar plots
 */
?>


<div class="row">
    <div class="container">
        <h1>Sandbox: scatterplot01</h1>

        <p>This sandbox helps generate simple scatter plots from raw data. </p>                   

        <h4 class="nc-mt-15">Required parameters</h4>
        <div id="nc-sandbox-required" class="nc-parameters form-horizontal" val="scatterplot01">
            <?php
            include_once "sandbox-components/ui-sandbox-titlelabs.php";
            ?>             
            <div class="form-group" val="data" colnames="x y name">                
                <label class="col-sm-2 control-label">Data<br/><br/>x<br/>y<br/>name</label>                
                <div class="col-sm-7">                    
                    <textarea class="form-control" rows="8"></textarea>                     
                </div>
                <div class="col-sm-3 nc-tips">
                    <p>You can <b>paste-in data</b> from a spreadsheet here. Columns should contain x coordinates, y coordinates, and names.</p>
                    <p>To enter data manually, press the <b>space-bar twice</b> to generate a tab between columns.</p>                    
                </div>
                <div id="nc-temp"></div>
            </div>
        </div> 

        <h4 class="nc-sandbox-optional">Optional parameters <span><span class="caret"></span></span></h4>
        <div id="nc-sandbox-optional" class="nc-parameters form-horizontal">
            <?php
            $defaults = [200, 200, 40, 20, 60, 40];
            include_once "sandbox-components/ui-sandbox-sizemargin.php";
            $defaults = ["-1.5em", "2.5em", "-2em"];
            include_once "sandbox-components/ui-sandbox-offsets.php";
            ?>                         

            <div class="form-group" val="radius">                
                <label class="col-sm-2 control-label">Radius</label>                
                <div class="col-sm-1">
                    <input type="text" class="form-control" value="3">                        
                </div>                            
            </div>
            <div class="form-group" val="color">                
                <label class="col-sm-2 control-label">Color</label>                
                <div class="col-sm-2">
                    <input type="text" class="form-control" value="#0000dd">                        
                </div>                            
            </div>
            
        </div>                        

    </div>
</div>


<?php
$mdaside = "(Use this code in an abstract, description, or comment)";
include_once "ui-sandbox-md.php";
?>


