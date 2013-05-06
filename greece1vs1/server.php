<?php


//error_reporting(E_ALL);
//ini_set('display_errors', '1');

require('../common/auth.php');
require('../common/dopostrequest.php');
require('../common/warlight_gameapi.php');
require('../common/savejsonbackup.php');


date_default_timezone_set('UTC');
$now = new DateTime("now");
//echo 'now: '.$now->format('Y/m/d H:i:s').'<br>';

$index = 'indexes/index_latest.json';
$string = file_get_contents($index);
if ($string) {
	//echo $string;
	$json=json_decode($string,true);
	
	//echo 'then: '.$json['datetime'].'<br>';
	$then = DateTime::createFromFormat('Y/m/d H:i:s', $json['datetime']);	
	$interval = date_diff($now, $then);
	
	// only update if it has passed 12 hours since last update
//	if (($interval->format('%a')*24+$interval->format('%h')) > 8)
	{
	
		$openseat = -1;
	
		// check all games from index, if game is pending check if its over
		unset($value);
		unset($jkey);
		foreach ($json['games'] as $jkey => &$value) {
			//echo 'checking '.$value['id'].'<br><br>';
			
			// if game was previously not finished, recheck it now
			if ( 	(array_key_exists('state',$value)) &&
					($value['state'] != 'Finished')
				) {
							
				$gamejson = API_GameFeed($value['id']);
				
				//echo $result;
				//var_dump($gamejson);//['termsOfUse'];
				if (array_key_exists('error',$gamejson)) {
					//echo 'error retrieving gamefeed for '.$value['id'];
					// "error": "Loading the game produced an error: ServerGameKeyNotFound"
					// means this game has been deleted
					unset($json['games'][$jkey]);
					continue;
				}
				
				$deletedgame = false;
				unset($gamejson['termsOfUse']);
				unset($gamejson['name']);
				//unset($gamejson['numberOfTurns']);
				foreach ($gamejson['players'] as &$player) {
					unset($player['email']);
					unset($player['color']);
					unset($player['isAI']);

					// if player is not in $json['players'], add him					
					$present = false;
					unset($jplayer);
					foreach ($json['players'] as $jplayer) {
						if ($jplayer['token'] == $player['id']) {
							$present = true;
						}
					}
					if (!$present) {
						//echo 'not present yet, lets fix that...<br><br>';
						unset($playerjson);			
						$playerjson = API_ValidateInviteToken($player['id']);
						if (array_key_exists('error',$playerjson)) {
							die('error retrieving player info from id '.$player['id'].'! where did you get this number?!');
						}
						// add to index db
						unset($playerjson['tokenIsValid']);
						unset($playerjson['isMember']);
						unset($playerjson['color']);
						unset($playerjson['tagline']);
						$playerjson['token'] = ''.$player['id']; //needs to be string
						$playerjson['wins'] = 0;
						$playerjson['losses'] = 0;
						$playerjson['average'] = 0;	
						$playerjson['active'] = 'true';			
						array_push($json['players'], $playerjson);
					}
					
				}

				if ($gamejson['state'] == 'Finished') {
					unset($vplayer);
					foreach ($gamejson['players'] as $vplayer) {
						if ($vplayer['state'] == 'Won') {
							//echo 'updating win for '.$vplayer['id'].'<br>';
							unset($tkey);
							unset($tvalue);
							foreach ($json['players'] as $tkey => &$tvalue) {
								if ($json['players'][$tkey]['token'] == $vplayer['id']) {
									$json['players'][$tkey]['wins'] = intval($json['players'][$tkey]['wins'])+1;
									$json['players'][$tkey]['average'] = intval($json['players'][$tkey]['average'])+1;
									//echo $json['players'][$tkey]['token'].' now has: '.$json['players'][$tkey]['wins'].'<br>';
								}
							}
						} else {
							//echo 'updating loss for '.$vplayer['id'].'<br>';
							unset($pkey);
							unset($pvalue);
							foreach ($json['players'] as $pkey => &$pvalue) {
								if ($json['players'][$pkey]['token'] == $vplayer['id']) {
									$json['players'][$pkey]['losses'] = intval($json['players'][$pkey]['losses'])+1;
									$json['players'][$pkey]['average'] = intval($json['players'][$pkey]['average'])-1;
									//echo $json['players'][$pkey]['token'].' now has: '.$json['players'][$pkey]['wins'].'<br>';									
								}
							}
						}
					}
				}

				$gamejson['datetime'] = $now->format('Y/m/d H:i:s');
				$value = $gamejson;
				
				// if one of the listed games has open seats we should return the gameid
				if ($gamejson['state'] == 'WaitingForPlayers') {
					$openseat = $gamejson['id'];
				}
					
			}
		}
		
		//var_dump($json['players']);


		// sort index db players list by average (wins-losses, prioritize wins on draw)
		unset($key);
		unset($row);
		foreach ($json['players'] as $key => $row) {
			$avg[$key] = $row['average'];
			$win[$key] = $row['wins'];
		}
		array_multisort($avg, SORT_DESC, $win, SORT_DESC, $json['players']);

		if ($_GET['create'] == 'false') {
			// dont create new game
		} else 
		{
			// create new game
			if ($openseat != -1) {
		
				echo 'done:'.$openseat;
			
			} else {
		
				//lets create a new game	
				$templateID = '305181';
				$gameName = 'Greece 1vs1 Ladder';
				unset($players);
				$players = array( 	
									0=>array('token' => 'OpenSeat'),
									1=>array('token' => 'OpenSeat')
								);
				$personalMessage = 'http://tinyurl.com/wl-clot-greece';
		
				$resultjson = API_CreateGame(
									$templateID,
									$gameName,
									$personalMessage,
									$players
									);
				
				if (array_key_exists('error',$resultjson)) {
					echo 'error creating game with template '.$templateID.' '.$gameName.' with players:<br>';
					var_dump($players);
					die;
				} else {
					$gameid = $resultjson['gameID'];
					echo 'done:'.$gameid;
				}
		
				$gamejson = API_GameFeed($gameid);
		
				unset($gamejson['termsOfUse']);
				unset($gamejson['name']);
				foreach ($gamejson['players'] as &$gplayer) {
					unset($gplayer['email']);
					unset($gplayer['color']);
					unset($gplayer['isAI']);
				}	
				$gamejson['datetime'] = $now->format('Y/m/d H:i:s');
			
				array_push($json['games'], $gamejson);
			
			}
		}
		
		// update date
		$json['datetime'] = $now->format('Y/m/d H:i:s');
		
		//echo json_encode($json);
		saveJSON($json, false);
	
	}
	
}

?>
