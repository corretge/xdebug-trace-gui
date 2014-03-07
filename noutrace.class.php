<?php

class noutrace
{

  public $logDirectory;
  public $traceFormat;
  public $file;
  public $memoryAlarm = 0.3;
  public $timeAlarm = 0.03;
  public $onlyOneInstruction = '';
  public $onlyOneScript = '';
  public $customNamespace = 'Corretge\\';
  public $filesize;
  protected $defFN;

  public function __construct()
  {
    $this->logDirectory = ini_get('xdebug.trace_output_dir');
    $this->traceFormat = ini_get('xdebug.trace_format');
    ini_set('xdebug.auto_trace', 'Off');
  }

  public function rtvFiles()
  {
    $ret = '';
    $aFiles = array();
    $files = new DirectoryIterator($this->logDirectory);
    foreach ($files as $file)
    {
      if (substr_count($file->getFilename(), '.xt') == 0)
      {
        continue;
      }


      $date = explode('.', $file->getFilename());
      $date = date('Y-m-d H:i:s', $file->getCTime());

      if ($file->getFilename() == $this->file)
      {
        $jSel = ' selected="selected"';
      }
      else
      {
        $jSel = '';
      }

      $aFiles[$date . uniqid()] = '<option value="' . $file->getFilename() . '" ' . $jSel . '> ' . $date . ' - ' . str_replace('_','-',$file->getFilename()) . '-' . number_format($file->getSize() / 1024,
                                                                                                                                                 0,
                                                                                                                                                 ',',
                                                                                                                                                 '.') . '-KB</option>';
    }

    ksort($aFiles);

    return implode("\n", $aFiles);
  }

  public function aryComp($a, $b)
  {
    if ($a['cnt'] == $b['cnt'])
    {
      return 0;
    }
    /**
     * fem-ho desc
     */
    return ($a['cnt'] > $b['cnt']) ? -1 : 1;
  }

  public function usortByArrayKey(&$array, $key, $asc=SORT_ASC)
  {
    $sort_flags = array(SORT_ASC, SORT_DESC);
    if (!in_array($asc, $sort_flags))
      throw new InvalidArgumentException('sort flag only accepts SORT_ASC or SORT_DESC');
    $cmp = create_function('array $a, array $b use $key, $asc, $sort_flags','
        if (!is_array($key))
        { //just one key and sort direction
          if (!isset($a[$key]) || !isset($b[$key]))
          {
            throw new Exception(\'attempting to sort on non-existent keys\');
          }
          if ($a[$key] == $b[$key])
            return 0;
          return ($asc == SORT_ASC xor $a[$key] < $b[$key]) ? 1 : -1;
        } else
        { //using multiple keys for sort and sub-sort
          foreach ($key as $sub_key => $sub_asc)
          {
            //array can come as \'sort_key\'=>SORT_ASC|SORT_DESC or just 'sort_key', so need to detect which
            if (!in_array($sub_asc, $sort_flags))
            {
              $sub_key = $sub_asc;
              $sub_asc = $asc;
            }
            //just like above, except \'continue\' in place of return 0
            if (!isset($a[$sub_key]) || !isset($b[$sub_key]))
            {
              throw new Exception(\'attempting to sort on non-existent keys\');
            }
            if ($a[$sub_key] == $b[$sub_key])
              continue;
            return ($sub_asc == SORT_ASC xor $a[$sub_key] < $b[$sub_key]) ? 1 : -1;
          }
          return 0;
        }
      ');
    usort($array, $cmp);
  }

  /**
   * establim els paràmetres que ens arriben del formulari.
   */
  public function setParams()
  {
    if (isset($_GET['file']))
    {
      $this->file = basename($_GET['file']);

      /**
       * mirem que sigui un arxiu vàlid
       */
      if (!file_exists($this->logDirectory . '/' . $this->file))
      {
        throw new Exception("Can't access to file " . $this->logDirectory . '/' . $this->file);
      }

      $this->filesize = filesize($this->logDirectory . '/' . $this->file);
    }

    if (isset($_GET['onlyOneInstruction']))
    {
      $this->onlyOneInstruction = ($_GET['onlyOneInstruction']);
    }
    if (isset($_GET['onlyOneScript']))
    {
      $this->onlyOneScript = ($_GET['onlyOneScript']);
    }

     //$this->memoryAlarm = (float) $_GET['memory'];
    //$this->timeAlarm = (float) $_GET['time'];
  }

  /**
   * la mare dels ous, la traça
   *
   * Sense que serveixi de precedents i per un tema de performance, aquest
   * mètode escriurà a stdoutput directament.
   */
  public function trace()
  {
    /**
     * recuperem la llista de funcions, les pròpies de PHP hi
     * seran sota ['internal']
     */
    //$this->defFN = get_defined_functions();

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
     * inicialitzem alguns camps
     */
    $prevLvl = 0;
    $prevTim = 0;
    $prevMem = 0;
    $class = 'odd';

    /**
     * mirem si ens demanen iniLin
     */
    if (!isset($_GET['iniLin']))
    {
      $_GET['iniLin'] = 0;
      $ctrlPrimeraLin = false;
    }
    else
    {
      $ctrlPrimeraLin = true;
    }
    $iniLin = (double) $_GET['iniLin'];
    $maxLin = $iniLin + 1024;

    /**
     * mirem si ens demanen una instrucció concreta, llavors no hi ha limit
     */
    $controlDeLinies = (!empty($this->onlyOneInstruction) or !empty($this->onlyOneScript));
    $controlInstruction = !empty($this->onlyOneInstruction);
    $controlScript = !empty($this->onlyOneScript);


    /**
     * només acceptarem tipus de traça 1
     */
    if ($this->traceFormat != 1)
    {
      throw new Exception("xdebug.trace_format in /etc/php5/conf.d/xdebug.ini must be 1");
    }

    $aSteps = array();

    /**
     * Process all lines
     */
    $fh = fopen($this->logDirectory . '/' . $this->file, 'r');
    $nRow = 0;
    $eof = true;

    while ($jReadedLine = fgets($fh))
    {
      $nRow++;

      if (!$controlDeLinies and $nRow < $iniLin)
      {
        continue;
      }

      $jData = explode("\t", $jReadedLine);
      $jDataCnt = count($jData);

      /**
       * si es tracta de la capçalera de l'arxiu, la mostrem com a info
       */
      if ($jDataCnt == 1)
      {
        echo "<pre>$jReadedLine</pre>";
        continue;
      }
      /**
       * si és el registre de finalització d'una instrucció, la processem
       */
      elseif ($jDataCnt == 5)
      {

//        list($jFLevel, $jFId, $jFPoint, $jFTime, $jFMemory) = $jData;


        /**
         * Si és el final de tot, no el comptarem, doncs no tenim cap id d'incic
         * el mostrarem directament
         */
        if ($jData[0] == '')
        {
          echo "<h3>TOTAL " . number_format(count($aSteps), 0) .
          " function/method calls in " . number_format($jData[3], 6) . " ms with " .
          number_format(((int) $jData[4]) / 1024, 3) . " KB's </h3>";
        }
        else
        {

          continue;

          /**
           * li restem el temps i la memòria
           */
          $aSteps[$jData[1]][3] = number_format((float) $jData[3] - (float) $aSteps[$jData[1]][3],
                                                6);
          $aSteps[$jData[1]][4] = number_format((float) $jData[4] - (float) $aSteps[$jData[1]][4],
                                                0);
        }
      }
      /**
       * En qualsevol altre cas, és un registre d'inici d'instrucció
       */
      else
      {
//        list($jILevel, $jIId, $jIPoint, $jITime, $jIMemory, $jIFunction,
//          $jIType, $jIFile, $jIFilename, $jILine, $jINumParms) = $jData;

        If ($prevTim == 0)
        {
          $prevTim = (float) $jData[3];
          $prevMem = (float) $jData[4];

          if ($iniLin == 0)
          {
              continue;
          }
        }


        /**
         * procedim a fer la sortida
         */
        /**
         * si hi ha un canvi de nivell, en funció de si és
         * més petit o més gran,
         */
        if ($prevLvl < $jData[0])
        {
          if ($ctrlPrimeraLin)
          {
            echo str_repeat("<ul>", $jData[0]);
            $ctrlPrimeraLin = false;
          }
          else
          {
            echo "<ul>";
          }
        }
        elseif ($prevLvl > $jData[0])
        {
          echo str_repeat("</ul>", $prevLvl - $jData[0]);
        }


        $prevLvl = $jData[0];


        /**
         * imprimim, només si es correspon a la instrucció que han demanat.
         */
        if (!$controlDeLinies or
            ($controlInstruction and strpos($this->onlyOneInstruction, $jData[5]) === 0) or
            ($controlScript and strpos($jData[8], $this->onlyOneScript) !== false)

            )
        {
            echo "<li title=\"{$nRow}\" class=\"{$class}\">";


          /**
           * @todo fer-ho via CSS
           */
          if ($class == 'odd')
          {
            $class = 'even';
          }
          else
          {
            $class = 'odd';
          }



          echo '<span class="line">';
          echo "<a href='trace-code.php?file={$jData[8]}&line={$jData[9]}' target='trace-code'>$jData[9]</a>";
          echo "</span>";

          echo '<span class="time">';

//        echo "ini"  . number_format($prevTim, 6) . "<br />";
//        echo "end"  . number_format((float) $jData[3], 6) . "<br />";
          $jSeconds = (float) $jData[3] - $prevTim;
          echo number_format($jSeconds * 1000000, 0) . ' µs';

          echo "</span>";

          echo '<span class="mem">';
//        echo "ini"  . number_format($prevMem, 0) . "<br />";
//        echo "end"  . number_format((float) $jData[4], 0) . "<br />";
          echo number_format((float) $jData[4] - $prevMem, 0);
          echo "</span>";



//        list($jILevel, $jIId, $jIPoint, $jITime, $jIMemory, $jIFunction,
//          $jIType, $jIFile, $jIFilename, $jILine, $jINumParms) = $jData;

          echo '<span class="func">';
          echo "<b>{$jData[5]}</b><br/>";

          if ($jData[10] > 0)
          {
            echo "<ul>";

            for ($jI = 11; $jI <= 10 + $jData[10]; $jI++)
            {
              $jData[$jI]=str_replace('<!--', '&lt;!--', $jData[$jI]); //The Fix for situation: if appeared tag "<!--" in the values of variables then it comments out the remains of result output. That was unexpected.
              echo "<li class=\"parm\">{$jData[$jI]}</li>";
            }
            echo "</ul>";
          }
          elseif (!empty($jData[7]))
          {
            echo "<ul><li class=\"parm\">{$jData[7]}</li></ul>";
          }

          echo "<br/>";

          echo "<i class=\"pgm\">{$jData[8]}</i>";


          echo '</span>';


          echo "</li>";

          ob_flush();
        }

        /**
         * Si superem el màxim de línies, sortim
         */
        if (!$controlDeLinies and $nRow > $maxLin)
        {
          $eof = false;
          break;
        }

        $prevTim = (float) $jData[3];
        $prevMem = (float) $jData[4];

        $lastLine = $nRow;
      }
    }

    if (!$eof)
    {
      $_GET['iniLin'] = $lastLine + 1;
      echo "<br /><br><a href=\"{$_SERVER['SCRIPT_NAME']}?";
      foreach ($_GET as $parm => $val)
      {
        echo "{$parm}={$val}&";
      }
      echo "\">next 1024 lines</a>";
    }
  }

  public function debugMem($line, $method = null)
  {
    echo "<!-- line {$line} memory " . number_format(memory_get_usage(true), 0);

    if (isset($method))
    {
      echo ' method ' . $method;
    }

    echo " -->";
  }

}
