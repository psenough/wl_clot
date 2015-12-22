<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require('simple_html_dom.php');

$clots = [
			[ 	
				"url" => "http://real-time-ladder.appspot.com/lot/5649391675244544",
				"type" => "realtime",
				"url_type" => "fizzer_example"
			],
			[ 	
				"url" => "http://wl1v1-clot.appspot.com/lot/5629499534213120",
				"type" => "multiday",
				"url_type" => "fizzer_example"
			],
			[ 
				"url" => "http://multi-day-ladder.appspot.com/lot/5629499534213120",
				"type" => "multiday",
				"url_type" => "fizzer_example"
			],
			[
				"url" => "http://warladder.net",
				"type" => "multiday",
				"url_type" => "dutch"
			]
		];
		
//var_dump($clots);
		
$output = [];
$i = 0;

foreach ($clots as $clot) {
	$output[$i] = [];
	
	$output[$i]['url'] = $clot["url"];
	
	$html = file_get_html($clot["url"]);
	if ($html) {
		switch($clot["url_type"]) {
			case "fizzer_example":
				$output[$i]['name'] = $html->find("h1", 0)->plaintext;
				$output[$i]['players'] = count($html->find('.table',1)->find('tr'))-1;
				$output[$i]['type'] = $clot["type"];
			break;
			case "dutch":
				//TODO: check if there are other ladders listed in this page instead of just taking the first one
				$output[$i]['name'] = $html->find('.table',0)->find('a',0)->plaintext;
				$output[$i]['players'] = $html->find('.table',0)->find('a',1)->plaintext;
				$output[$i]['type'] = $clot["type"];
			break;
		}

	} else {
		//echo $clot.' taking too long to reply<br>';
		$output[$i]['error'] = 'timeout';
	}
	$i++;
}

//var_dump($output);

echo '{"ladders":'.json_encode($output).'}';

?>