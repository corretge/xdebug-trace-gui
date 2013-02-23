<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Xdebug Trace File Statistics <?php if (isset($_GET['file']))
  echo ' - ' . htmlentities($_GET['file']); ?></title>
    <LINK href="trace.css" rel="stylesheet" type="text/css">
    <!-- <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7/jquery.min.js"></script> -->
  </head>
  <body>

    <?php
    @session_start();
    require 'graph.class.php';
    $xdb = new graph();
    $xdb->setParams();
    ?>

    <h1>Xdebug Trace File Statistics</h1>
    <h2>Settings <?php echo $xdb->logDirectory ?> (<?php echo $xdb->traceFormat ?>)</h2>
    <form method="get" action="graph.php">
      <label>File
        <select name="file">
          <option value="" selected="selected"> -- Select -- </option>
<?php echo $xdb->rtvFiles(); ?>
        </select>
      </label>

      <input type="submit" value="parse" />

    </form>

    <?php
    echo "<a href=\"{$_SERVER['SCRIPT_NAME']}\">Refresh files</a>";
    
    /**
     * Si no hi ha arxium, sortim.
     */
    if (empty($_GET['file']))
    {
      exit;
    }

    echo "<h2>Statistics {$xdb->file}</h2>";
    echo number_format($xdb->filesize, 0) . " bytes <br />";


    $xdb->trace();
    ?>


  </body>
</html>