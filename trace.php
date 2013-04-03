<?php
require 'trace.config.php';

//error_reporting(0);
?>
<html>
    <head>
        <style type="text/css">
            @import url('trace.css');
        </style>
        <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>
        <title>Xdebug Trace File Parser</title>
    </head>
    <body>
        <h1>Xdebug Trace File Parser</h1>
        <h2>Settings <?php echo $config['directory'] ?></h2>
        <form method="get" action="trace.php">
            <label>File
                <select name="file">
                    <option value="" selected="selected"> -- Select -- </option>
                    <?php
                    $files = new DirectoryIterator($config['directory']);
                    foreach ($files as $file)
                    {

                        if (substr_count($file->getFilename(), '.xt') == 0 || in_array($config['directory'] . '/' . $file->getFilename(),
                                                                                       $ownTraces))
                        {
                            continue;
                        }

                        $date = explode('.', $file->getFilename());
                        $date = date('Y-m-d H:i:s', $file->getCTime());


                        echo '<option value="' . $file->getFilename() . '"> ' . $date . ' - ' . $file->getFilename() . '- ' . number_format($file->getSize() / 1024,
                                                                                                                                            0,
                                                                                                                                            ',',
                                                                                                                                            '.') . ' KB</option>';
                    }
                    ?>
                </select>
            </label>

            <label>If the memory jumps <input type="text" name="memory" value="<?php echo XDEBUG_TRACE_GUI_MEMORY_TRIGGER ?>" style="text-align:right" size="5"/> MB, provide an alert</label>
            <label>If the execution time jumps <input type="text" name="time" value="<?php echo XDEBUG_TRACE_GUI_TIME_TRIGGER ?>" style="text-align:right" size="5"/> seconds, provide an alert</label>

            <input type="submit" value="parse" />

        </form>

        <br /><a href="#sumary">Resum</a>
        <?php
        if (!isset($_GET['file']))
        {
            exit;
        }
        ?>
        <h2>Output</h2>
        <?php
        /**
         * retrieve the xdebug.trace_format ini set.
         */
        $xdebug_trace_format = ini_get('xdebug.trace_format');
        $XDEBUG_TRACE_GUI_CUSTOM_NAMESPACE_LEN = strlen(XDEBUG_TRACE_GUI_CUSTOM_NAMESPACE);

        $traceFile = $config['directory'] . '/' . $_GET ['file'];


        $memJump = 1;
        if (isset($_GET['memory']))
        {
            $memJump = (float) $_GET['memory'];
        }

        $timeJump = 1;
        if (isset($_GET['time']))
        {
            $timeJump = (float) $_GET['time'];
        }

        if (!isset($_GET ['file']) || empty($_GET ['file']))
        {
            echo '<p>No file selected</p>';
        }
        else if (!file_exists($traceFile))
        {
            echo '<p>Invalid file</p>';
        }
        else
        {



            /**
             * és una manera molt poc eficient de llegir un arxiu.
             */
            //$trace = file_get_contents($traceFile);
            //$lines = explode("\n", $trace);


            $previousLevel = 0;
            $levelIds = array();
            $ids = 0;


            $defFn = get_defined_functions();
            /**
             * counter
             */
            $jCnt = 0;

            /**
             * Sumary
             */
            $aSumary = array();
            $aSumaryS = array();

            /**
             * Process all lines
             */
            //foreach ($lines as $line)
            $fh = fopen($traceFile, 'r');
            while ($jReadedLine = fgets($fh))
            {
                /**
                 * Add one to the counter
                 */
                $jCnt++;

                switch ($xdebug_trace_format)
                {
                    /**
                     * xdebug.trace_format = 1  Computerized
                     * @link http://www.xdebug.org/docs/all_settings#trace_format
                     */
                    case 1:
                        $data = explode("\t", $jReadedLine);
                        @list($level, $id, $point, $time, $memory, $function, $type, $file, $filename, $line, $numParms) = $data;

                        /**
                         * if there is params save it
                         */
                        if (isset($numParms) and $numParms > 0)
                        {
                            $valParms = '';
                            for ($i = 11; $i < (11 + $numParms); $i++)
                            {
                                $valParms .= "<li>" . str_replace('\n',
                                                                  '<br />',
                                                                  htmlentities($data[$i])) . "</li>\n";
                            }
                        }
                        elseif (!empty($file))
                        {
                            $valParms = "<li>{$file}</li>";
                        }
                        else
                        {
                            $valParms = '';
                        }

                        $memory = round($memory / (1024 * 1024), 4);

                        break;

                    /**
                     * Other xdebug.trace_format
                     * @todo code for all types
                     */
                    default:

                        $strippedLine = preg_replace('(([\s]{2,}))', ' ',
                                                     trim($line));
                        list ($time, $memory) = explode(" ", $strippedLine);

                        $memory = round($memory / (1024 * 1024), 4);

                        $level = round(((strpos($line, '->') - strpos($line,
                                                                      $memory) + strlen($memory))) / 2);

                        if ($level <= $previousLevel && isset($levelIds[$level]))
                        {
                            $fullTrace[$levelIds[$level]]['timeOnExit'] = $time;
                            $fullTrace[$levelIds[$level]]['memoryOnExit'] = $memory;
                        }
                        $id = ++$ids;
                        $levelIds[$level] = $id;
                        $previousLevel = $level;

                        $parts = array_map('trim', explode("->", $line, 2));
                        $parts = explode(" ", $parts[1]);
                        $function = $parts[0];
                        list($line, $file) = array_map('strrev',
                                                       explode(":",
                                                               strrev($parts[1]),
                                                                      2));
                        $filename = $file;
                        $point = 0;
                        $type = in_array(substr($function, 0,
                                                strpos($function, "(")),
                                                       $defFn['internal']) ? 0 : 1;

                        $valParms = '';

                        break;
                }

                if (empty($function))
                {
                    $fullTrace[$id]['timeOnExit'] = $time;
                    $fullTrace[$id]['memoryOnExit'] = $memory;
                    continue;
                }



//                if (!empty($filename) and strpos('eyeOS/Loader', $filename) > 0)
//                {
//                    continue;
//                }

                if ($point == 0)
                {
                    // starting function
                    $fullTrace[$id] = array('level' => $level,
                      'id' => $id,
                      'timeOnEntry' => $time,
                      'memoryOnEntry' => $memory,
                      'function' => $function,
                      'type' => $type,
                      'file' => $file,
                      'filename' => $filename,
                      'line' => $line,
                      'valParms' => $valParms);

                    if (isset($lastMemory) and ($memory - $lastMemory) > $memJump)
                    {
                        $fullTrace[$id]['memoryAlert'] = $memory - $lastMemory;
                    }
                    else
                    {
                        $fullTrace[$id]['memoryAlert'] = false;
                    }


                    if (isset($lastMemory) and ($time - $lastTime) > $timeJump)
                    {
                        $fullTrace[$id]['timeAlert'] = $time - $lastTime;
                    }
                    else
                    {
                        $fullTrace[$id]['timeAlert'] = false;
                    }

                    $lastMemory = $memory;
                    $lastTime = $time;
                }
                else
                {
                    $fullTrace[$id]['timeOnExit'] = $time;
                    $fullTrace[$id]['memoryOnExit'] = $memory;
                }
            }
            ?>
            <table>
                <tr>
                    <td>
                        <?php echo $traceFile; ?><br />
                        <strong><?php echo count($fullTrace); ?></strong> function calls in <strong><?php $l = end($fullTrace);
                    echo $l['timeOnEntry']; ?> seconds</strong>, using <strong><?php echo $l['memoryOnEntry'] ?> MB</strong> of memory.
                    </td>
                    <td></td>
                    <td style="vertical-align:bottom"><small>in = start func.<br />out = end func.</small></td>
                    <td style="vertical-align:bottom"><small>in = start func.<br />out = end func.</small></td>
                </tr>
                <tr>
                    <th style="max-width: 70%">Function / File</th>
                    <th style="min-width: 8em;">Line</th>
                    <th style="min-width: 8em;">Time</th>
                    <th style="min-width: 8em;">Memory</th>
                </tr>
                <?php
                foreach ($fullTrace as $trace)
                {
                    /**
                     * 
                     * 
                      echo "<pre>";
                      var_dump($trace);
                      echo "</pre><hr>";
                      /**
                     */
                    ?>
                    <tr>
                        <td style="padding-left:<?php
            if (isset($trace['level']))
            {
                echo $trace['level'] * 10;
            }
            else
            {
                echo '0';
            }
                    ?>px">
                            <?php if (isset($trace['type']) and $trace['type'] == 0)
                            { ?><a target="_blank" href="http://php.net/<?php echo $trace['function'] ?>"><span class="native" title="PHP doc <?php echo $trace['function'] ?>">&#x261b; </span></a><?php
                }
                else
                {
                    /**
                     * custom color identifier for ZendFramework methods
                     */
                    if (isset($trace['function']) and substr($trace['function'],
                                                             0, 5) == 'Zend_')
                    {
                        $userFunction = 'Zend';
                    }
                    /**
                     * And all Corretge namespace classes and methods
                     */
                    elseif (isset($trace['function']) and substr($trace['function'],
                                                                 0,
                                                                 $XDEBUG_TRACE_GUI_CUSTOM_NAMESPACE_LEN) == XDEBUG_TRACE_GUI_CUSTOM_NAMESPACE)
                    {
                        $userFunction = 'Corretge';
                    }
                    else
                    {
                        $userFunction = 'user';
                    }
                                ?><span class="<?php echo $userFunction ?>" title="UDF ">&#x261b; </span><?php } ?><strong><?php if (isset($trace['type']) and $trace['type'] == 0)
                    { ?>\<?php } ?><?php echo @$trace['function'] ?></strong><ul><?php echo @$trace['valParms'] ?></ul><br />
                            <small><?php echo @$trace['filename'] ?></small>
                            <span class="warning">
                                <?php
                                if (isset($trace['timeAlert']) and $trace['timeAlert'])
                                {
                                    echo "<br />Warning, time jump exceeds trigger! {$trace['timeAlert']}";
                                }
                                if (isset($trace['memoryAlert']) and $trace['memoryAlert'])
                                {
                                    echo "<br />Warning, memory jump exceeds trigger! {$trace['memoryAlert']}";
                                }
                                ?>
                            </span>

                        </td>
                        <td class="digit line">
                            <?php
                            if (isset($trace['line']))
                            {
                                echo "<a href=\"#{$trace['line']}\">{$trace['line']}</a>";
                            }
                            ?>
                        </td>
                        <td class="digit" style="<?php if (isset($trace['timeAlert']) and $trace['timeAlert'])
                    { ?>background:maroon;color:white<?php } ?>">in: <?php echo @$trace['timeOnEntry'] ?> s<br />out: <?php echo @$trace['timeOnExit'] ?> <br />
                            <?php
                            if (isset($trace['timeOnEntry']) and isset($trace['timeOnExit']))
                            {
                                $jTimeConsumit = ($trace['timeOnExit'] - $trace['timeOnEntry']) * 1000000;
                                echo number_format($jTimeConsumit, 0) . ' µs';
                            }
                            else
                            {
                                $jTimeConsumit = 0;
                            }
                            ?></td>
                        <td class="digit" style="<?php if (isset($trace['memoryAlert']) and $trace['memoryAlert'])
                    { ?>background:maroon;color:white<?php } ?>">in: <?php echo @$trace['memoryOnEntry'] ?> MB<br />out: <?php echo @$trace['memoryOnExit'] ?> MB<br />


                            <?php
                            if (isset($trace['memoryOnEntry']) and isset($trace['memoryOnExit']))
                            {
                                $jMemoryConsumit = ($trace['memoryOnExit'] - $trace['memoryOnEntry']) * 1000000;
                                echo number_format($jMemoryConsumit, 0) . ' B';
                            }
                            else
                            {
                                $jMemoryConsumit = 0;
                            }
                            ?>
                        </td>
                    </tr>
                    <?php
                    if (isset($trace) and isset($trace['function']))
                    {
                        if (isset($aSumary[$trace['function']]))
                        {
                            $aSumary[$trace['function']]['mem'] += $jMemoryConsumit;
                            $aSumary[$trace['function']]['tim'] += $jTimeConsumit;
                            $aSumary[$trace['function']]['cnt']++;
                        }
                        else
                        {
                            $aSumary[$trace['function']]['func'] = $trace['function'];
                            $aSumary[$trace['function']]['mem'] = $jMemoryConsumit;
                            $aSumary[$trace['function']]['tim'] = $jTimeConsumit;
                            $aSumary[$trace['function']]['cnt'] = 1;
                        }
                    }

                    /**
                     * fem el sumari per files
                     */
                    if (isset($trace) and isset($trace['filename']))
                    {
                        if (isset($aSumaryS[$trace['filename']]))
                        {
                            $aSumaryS[$trace['filename']]['mem'] += $jMemoryConsumit;
                            $aSumaryS[$trace['filename']]['tim'] += $jTimeConsumit;
                            $aSumaryS[$trace['filename']]['cnt']++;
                        }
                        else
                        {
                            $aSumaryS[$trace['filename']]['filename'] = $trace['filename'];
                            $aSumaryS[$trace['filename']]['mem'] = $jMemoryConsumit;
                            $aSumaryS[$trace['filename']]['tim'] = $jTimeConsumit;
                            $aSumaryS[$trace['filename']]['cnt'] = 1;
                        }
                    }
                }
                ?>
            </table>
            <?php
        }

//        usort($aSumary, 'aryComp');
        unset($fullTrace);
        usortByArrayKey($aSumary, 'tim', SORT_DESC);
        usortByArrayKey($aSumaryS, 'tim', SORT_DESC);

        echo "<a name=\"sumary\"></a><h2>Sumari de funcions per temps emprat</h2>
             <table>
                <tr>
                    <th>Function</th>
                    <th>times</th>
                    <th>sum Time</th>
                    <th>sum Memory</th>
                    <th>avg Time</th>
                    <th>avg Memory</th>
</tr>
             ";

        foreach ($aSumary as $row)
        {
            if ($row['cnt'] != 0)
            {

                $jAvgTim = $row['tim'] / $row['cnt'];
                $jAvgMem = $row['mem'] / $row['cnt'];
            }
            else
            {
                $jAvgTim = 0;
                $jAvgMem = 0;
            }

            $row['cnt'] = number_format($row['cnt'], 0);
            $row['tim'] = number_format($row['tim'], 0);
            $row['mem'] = number_format($row['mem'], 0);
            
            $jAvgMem = number_format($jAvgMem, 0);
            $jAvgTim = number_format($jAvgTim, 0);

            echo "<tr>
                 <td>{$row['func']}</td>
                 <td class='digit'>{$row['cnt']}</td>
                 <td class='digit'>{$row['tim']}</td>
                 <td class='digit'>{$row['mem']}</td>
                 <td class='digit'>{$jAvgTim}</td>
                 <td class='digit'>{$jAvgMem}</td>
                 
                 </tr>";
        }


        echo "</table>";




        echo "<a name=\"sumary\"></a><h2>Sumari d'scripts per temps emprat</h2>
             <table>
                <tr>
                    <th>Script</th>
                    <th>instructions</th>
                    <th>sum Time</th>
                    <th>sum Memory</th>
                    <th>avg Time per inst</th>
                    <th>avg Memory per inst</th>
               </tr>
             ";

        foreach ($aSumaryS as $row)
        {
            if ($row['cnt'] != 0)
            {

                $jAvgTim = $row['tim'] / $row['cnt'];
                $jAvgMem = $row['mem'] / $row['cnt'];
            }
            else
            {
                $jAvgTim = 0;
                $jAvgMem = 0;
            }

            $row['cnt'] = number_format($row['cnt'], 0);
            $row['mem'] = number_format($row['mem'], 0);
            $row['tim'] = number_format($row['tim'], 0);
            $jAvgMem = number_format($jAvgMem, 0);
            $jAvgTim = number_format($jAvgTim, 0);

            echo "<tr>
                 <td>{$row['filename']}</td>
                 <td class='digit'>{$row['cnt']}</td>
                 <td class='digit'>{$row['tim']}</td>
                 <td class='digit'>{$row['mem']}</td>
                 <td class='digit'>{$jAvgTim}</td>
                 <td class='digit'>{$jAvgMem}</td>
                 </tr>";
        }


        echo "</table>";
        ?>

        <script type="text/javascript">
            $(document).ready(function() {
                $('td.line a').each(function() {

                    $(this).click(function() {
                        showCode(this);
                    })

                })
            })

            function showCode(where) {
                var file = $(where).parent().parent().find('small').text();
                window.open('trace-code.php?line=' + $(where).text() + '&file=' + file + '#l' + $(where).text(), 'code', 'width=500,height=400,toolbar=no,status=no,menubar=no,scrollbars=yes');
            }

        </script>
    </body>
</html>
