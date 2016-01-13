<?php

header('Content-Type: application/json');

require('simple_html_dom.php');
require('../common/savejsonbackup.php');

date_default_timezone_set('UTC');
$now = new DateTime("now");
//echo 'now: '.$now->format('Y/m/d H:i:s').'<br>';

$string = file_get_contents($cachefilename);
if ($string) {
	//echo $string;
	$json1=json_decode($string,true);
	if (array_key_exists('datetime', $json1)) {
		
		//echo 'then: '.$json1['datetime'].'<br>';
		$then = DateTime::createFromFormat('Y/m/d H:i:s', $json1['datetime']);	
		
		$then1 = $then->add(new DateInterval('PT2M'));
		//echo 'then+1: '.$then1->format('Y/m/d H:i:s').'<br>';
		
		if ($then1 > $now) {
			echo $string;
			return;
		}
	}
}


$clots = [
	/*[ 	
		"url" => "http://real-time-ladder.appspot.com/lot/5649391675244544",
		"type" => "realtime",
		"url_type" => "motd_template"
	],
	[ 	
		"url" => "http://wl1v1-clot.appspot.com/lot/5629499534213120",
		"type" => "multiday",
		"url_type" => "motd_template"
	],
	[ 
		"url" => "http://multi-day-ladder.appspot.com/lot/5629499534213120",
		"type" => "multiday",
		"url_type" => "motd_template"
	],*/
	[
		"url" => "http://warladder.net",
		"type" => "multiday",
		"url_type" => "dutch"
	]
];
		
$output = [];
$i = 0;

foreach ($clots as $clot) {
	$output[$i] = [];
	
	$output[$i]['url'] = $clot["url"];
	
	$html = file_get_html($clot["url"]);
	if ($html) {
		switch($clot["url_type"]) {
			case "motd_template":
				//TODO: extract template info
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

$json['ladders'] = $output;
$json['datetime'] = $now->format('Y/m/d H:i:s');

?>