<?php

$config['directory'] = ini_get('xdebug.trace_output_dir');

ini_set('xdebug.auto_trace', 'Off');

/**
 * Do not modify below this line :) 
 * ...unless you really, really, reaaaally want to
 */
// get our own trace files
$ownTraces = file_get_contents('trace-own.cache');
$ownTraces = explode(PHP_EOL, $ownTraces);

define('XDEBUG_TRACE_GUI_MEMORY_TRIGGER', '0.3');
define('XDEBUG_TRACE_GUI_TIME_TRIGGER', '0.03');

define('XDEBUG_TRACE_GUI_CUSTOM_NAMESPACE', 'Corretge\\');

function aryComp($a, $b)
{
            if ($a['cnt'] == $b['cnt']) {
                return 0;
        }
        /**
         * fem-ho desc
         */
        return ($a['cnt'] > $b['cnt']) ? -1 : 1;
}

function usortByArrayKey(&$array, $key, $asc=SORT_ASC) { 
    $sort_flags = array(SORT_ASC, SORT_DESC); 
    if(!in_array($asc, $sort_flags)) throw new InvalidArgumentException('sort flag only accepts SORT_ASC or SORT_DESC'); 
    $cmp = create_function('array $a, array $b use $key, $asc, $sort_flags',' 
        if(!is_array($key)) { //just one key and sort direction 
            if(!isset($a[$key]) || !isset($b[$key])) { 
                throw new Exception(\'attempting to sort on non-existent keys\'); 
            } 
            if($a[$key] == $b[$key]) return 0; 
            return ($asc==SORT_ASC xor $a[$key] < $b[$key]) ? 1 : -1; 
        } else { //using multiple keys for sort and sub-sort 
            foreach($key as $sub_key => $sub_asc) { 
                //array can come as \'sort_key\'=>SORT_ASC|SORT_DESC or just \'sort_key\', so need to detect which 
                if(!in_array($sub_asc, $sort_flags)) { $sub_key = $sub_asc; $sub_asc = $asc; } 
                //just like above, except \'continue\' in place of return 0 
                if(!isset($a[$sub_key]) || !isset($b[$sub_key])) { 
                    throw new Exception(\'attempting to sort on non-existent keys\'); 
                } 
                if($a[$sub_key] == $b[$sub_key]) continue; 
                return ($sub_asc==SORT_ASC xor $a[$sub_key] < $b[$sub_key]) ? 1 : -1; 
            } 
            return 0; 
        } 
    ')}; 
    usort($array, $cmp); 
}
