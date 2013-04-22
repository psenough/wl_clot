<?php

function saveJSON($json) {
	$namepath = 'indexes/';
	$prename = 'index_';
	$postname = '.json';
	$counter = 0;

	// save backup json
	$done = false;
	while($done == false) {
		$pad = str_pad($counter, 5, "0", STR_PAD_LEFT);
		$filename = $namepath.$prename.$pad.$postname;
		if (!file_exists($filename)) {
			$done = true;
			$json['ref'] = 'index_'.$pad.$postname;
			file_put_contents($filename, json_encode($json)); 
		} else {
			if ($counter == 99999) $done = true;
		}
		$counter++;
	}

	// save index_latest.json
	file_put_contents('indexes/index_latest.json', json_encode($json));
}


?>
