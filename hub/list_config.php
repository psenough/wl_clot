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
	],
	[
		"url" => "http://warladder.net",
		"type" => "multiday",
		"url_type" => "dutch"
	]*/
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

$leagues = [
	[ 	
		"url" => "https://www.warlight.net/Forum/166818-clan-league-8-links",
		"name" => "Clan League 8",
		"players" => "~260"
	],
	[ 	
		"url" => "https://www.warlight.net/Forum/164080-manager-league-season-2",
		"name" => "Manager League 2",
		"players" => "~150"
	],
	[ 	
		"url" => "https://www.warlight.net/Forum/159429-awp-world-tour-magazine",
		"name" => "AWP World Tour",
		"players" => "~128"
	],
	[ 	
		"url" => "https://www.warlight.net/Forum/147689-wsow-season-3-rules-call-participants?Offset=0",
		"name" => "World Series of Warlight 4",
		"players" => "~100"
	],
	[ 	
		"url" => "https://www.warlight.net/Forum/152771-small-earth-promotionrelegation-league-season-6",
		"name" => "Small Earth Promotion/Relegation League 6",
		"players" => "~80"
	],
	[ 	
		"url" => "https://www.warlight.net/Forum/149352-promotionrelegation-league-season-19",
		"name" => "Promotion/Relegation League 19",
		"players" => "~50"
	],
	[ 	
		"url" => "https://www.warlight.net/Forum/136968-multiattackers-league-season-iv?Offset=0",
		"name" => "Multiattacker's League IV",
		"players" => "~40"
	],
	[
		"url" => "https://www.warlight.net/Forum/151215-warlight-world-champions-league-wwcl",
		"name" => "Warlight World Champions League",
		"players" => "36"
	],
	[
		"url" => "https://www.warlight.net/Forum/146128-rp-clan-league-2-official-thread",
		"name" => "RP Clan League 2",
		"players" => "~30"
	]	
];

$i = 0;
foreach ($leagues as $league) {
	$output2[$i] = [];
	$output2[$i]['url'] = $league["url"];
	$output2[$i]['name'] = $league["name"];
	$output2[$i]['players'] = $league["players"];
	$i++;
}

$json['leagues'] = $output2;
$json['datetime'] = $now->format('Y/m/d H:i:s');

?>