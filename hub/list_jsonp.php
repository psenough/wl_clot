<?php

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

$cachefilename = 'cache.json';

require_once('list_config.php');

//$myoutput = json_encode($json);

$myoutput = $_GET['callback'] . '(' . "{'success' : '1' , 'data' : ".json_encode($json)." }" . ')';

file_put_contents($cachefilename, $myoutput);

echo $myoutput;

?>