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
	],
	[
		"url" => "http://md-ladder.cloudapp.net/",
		"type" => "multiday",
		"url_type" => "motd_template"
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
				//TODO: this players count is total number of players, not the active ones, the active ones are listed at the end of a string on the main page (motd recent change)
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
		"url" => "https://www.warlight.net/Forum/242841-clan-league-9-kick",
		"name" => "Clan League 9",
		"players" => "~300"
	],
	[
		"url" => "https://www.warlight.net/Forum/159429-awp-world-tour-magazine",
		"name" => "AWP World Tour",
		"players" => "~150"
	],
	[
		"url" => "http://md-ladder.cloudapp.net/",
		"name" => "MotD's Multi-Day Ladder CLOT",
		"players" => "~100"
	],
	/*[ 	
		"url" => "https://www.warlight.net/Forum/166666-world-series-warlight-wsow-update",
		"name" => "World Series of Warlight 3",
		"players" => "~100"
	],*/
	[
		"url" => "https://www.warlight.net/Forum/242100-promotionrelegation-league-season-21",
		"name" => "Promotion/Relegation League 21",
		"players" => "~90"
	],
	[
		"url" => "https://www.warlight.net/Forum/251637-small-earth-promotionrelegation-league-season-10",
		"name" => "Small Earth Promotion/Relegation League 10",
		"players" => "~70"
	],
/*	[
		"url" => "https://www.warlight.net/Forum/223576-rpcl3official-thread",
		"name" => "RP Clan League 3",
		"players" => "~75"
	],
	[
		"url" => "https://www.warlight.net/Forum/240538-euro-cup-ii",
		"name" => "Euro Cup 2",
		"players" => "~70"
	],
	[ 	
		"url" => "https://www.warlight.net/Forum/241616-ligue-france-francophone-nouvelle-saison-",
		"name" => "Ligue Francophone 27",
		"players" => "~40"
	],*/
	[ 	
		"url" => "https://www.warlight.net/Forum/242633-captains-league-2nd-season-preparation-thread",
		"name" => "Captain's League 2",
		"players" => "~40"
	],
	/*[
		"url" => "https://www.warlight.net/Forum/151215-warlight-world-champions-league-wwcl",
		"name" => "Warlight World Champions League",
		"players" => "36"
	],*/
	[
		"url" => "http://noliterre.boards.net/",
		"name" => "World of Noliterre",
		"players" => "~20"
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