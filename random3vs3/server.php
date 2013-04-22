<?php


//error_reporting(E_ALL);
//ini_set('display_errors', '1');

require('../common/auth.php');
require('../common/dopostrequest.php');
require('../common/warlight_gameapi.php');
require('../common/savejsonbackup.php');


// remove player from official ladder list
//   no need to set it to innactive on index json db players
//   it'll get set automatically on later part of the algo
function removePlayerFromList($player_id) {
	$filename = 'players.txt';
	//echo 'filesize: '.filesize($filename).'<br>';
	$fp = @fopen($filename, 'r'); 
	if ($fp) {
	   $listplayers = explode("\n", fread($fp, filesize($filename)));
	}
	fclose($fp);
	// clean up empty lines
	foreach (array_keys($listplayers, '') as $key) {
		unset($listplayers[$key]);
	}
	// save new players file without player who declined the game
	$fp = @fopen($filename, 'w+'); 
	foreach ($listplayers as &$thisplayer) {
		if ($thisplayer != $player_id) {
			fwrite($fp,$thisplayer."\n");
		}
	}
	fclose($fp);
}

function getPlayersList() {
	$filename = 'players.txt';
	$fp = @fopen($filename, 'r'); 
	unset($listplayers);
	if ($fp) {
	   $listplayers = explode("\n", fread($fp, filesize($filename)));
	}
	fclose($fp);
	return $listplayers;
}

// 
// check names of players on a game
// see if their name in the json players list needs an update
//
// the system allows the users to change their names every few weeks
// checking for updates on all using validatetokenapi would become costly
// this way of checking on active games is gentler on the server
//
function checkNamesForUpdate($player, $json_players) {
	foreach ($json_players as &$thisplayer) {
		if (($player['id'] == $thisplayer['token']) && ($player['name'] != $thisplayer['name'])) {
			$thisplayer['name'] = $player['name'];
			//echo 'changing '.$thisplayer['name'].' to '.$player['name'].'<br><br>';
		}
	}
}


$templates = array(
				array( 	"templateID" 		=> '290970',
						"templateIDteams" 	=> '302491',
						"gameName" 			=> 'East Asia 3vs3 Ladder'),
						
				array(	"templateID" 		=> '301803',
						"templateIDteams" 	=> '302492',
						"gameName" 		=> 'Europe Warlords 3vs3 Ladder'),
						
				array( 	"templateID" 		=> '301787',
						"templateIDteams" 	=> '302493',
						"gameName" 			=> 'Medium France 3vs3 Ladder'),
						
				array( 	"templateID" 		=> '301673',
						"templateIDteams" 	=> '302494',
						"gameName" 			=> 'Europe Cities 3vs3 Ladder'),
						
				array( 	"templateID" 		=> '301792',
						"templateIDteams" 	=> '302495',
						"gameName" 			=> 'Battle Islands 3vs3 Ladder'),
						
				array( 	"templateID" 		=> '301801',
						"templateIDteams" 	=> '302496',
						"gameName" 			=> 'Imperium Romanum 3vs3 Ladder')	
			);



date_default_timezone_set('UTC');
$now = new DateTime("now");
echo 'now: '.$now->format('Y/m/d H:i:s').'<br>';

$index = 'indexes/index_latest.json';
$string = file_get_contents($index);
if ($string) {
	//echo $string;
	$json=json_decode($string,true);
	
	echo 'then: '.$json['datetime'].'<br>';
	$then = DateTime::createFromFormat('Y/m/d H:i:s', $json['datetime']);	
	$interval = date_diff($now, $then);
	
	// only update if it has passed 12 hours since last update
	if (($interval->format('%a')*24+$interval->format('%h')) > 8)
	{
	
		$pending = 0;

		// check all games from index, if game is pending check if its over
		foreach ($json['games'] as &$value) {
			//echo $value['id'].' game is '.$value['state'].'<br><br>';
			
			// if game was previously not finished, recheck it now
			if ($value['state'] != 'Finished') {
			
				$pending++;
				
				$gamejson = API_GameFeed($value['id']);
				
				//echo $result;
				//var_dump($gamejson);//['termsOfUse'];
				if (array_key_exists('error',$gamejson)) {
					echo 'error retrieving gamefeed for '.$value['id'];
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
					
					// check if name of player has changed
					// update if needed
					checkNamesForUpdate($player,&$json['players']);
					
					//var_dump($json['players']);
					//echo'<br><br>';
					
					//echo $player['id'].'<br>';			
					if ($player['state'] == 'Declined') {
						//echo $player['id'].' declined!<br>';
						// cancel this game
						if (!$deletedgame) {
							$resultjson = API_DeleteLobbyGame(intval($gamejson['id']));
							
							//echo $result;
							if (array_key_exists('error',$resultjson)) {
								echo 'error trying to delete this game<br>';
							} else {
								// remove it from index json db games
								unset($value['id']);
								$deletedgame = true;
								//echo 'deleting this game<br>';
							}
						}
						
						removePlayerFromList($player['id']);
					}
				}
				
				if (array_key_exists('templateid',$gamejson)) {
					$value = $gamejson;
				} else {
					// patch manually until fizzer updates the gamefeed api
					$tid = $templates[0]['templateID'];
					if (array_key_exists('templateid',$value)) {
						$tid = $value['templateid'];
						//echo 'there is a templateid already<br>';
					}
					$value = $gamejson;
					$value['templateID'] = $tid;
				}
				
				
				
				if (!$deletedgame) {
					if ($gamejson['state'] == 'Finished') {
						$pending--;
						unset($player);
						foreach ($value['players'] as $player) {
							if ($player['state'] == 'Won') {
								//echo 'updating win for '.$player['id'].'<br>';
								foreach ($json['players'] as $key => &$value) {
									if ($json['players'][$key]['token'] == $player['id']) {
										$json['players'][$key]['wins'] = intval($json['players'][$key]['wins'])+1;
										$json['players'][$key]['average'] = intval($json['players'][$key]['average'])+1;
										//echo $json['players'][$key]['token'].' now has: '.$json['players'][$key]['wins'].'<br>';
									}
								}
								//var_dump($json['players']);
								//echo '<br><br>';
							} else {
								//echo 'updating loss for '.$player['id'].'<br>';
								foreach ($json['players'] as $key => &$value) {
									if ($json['players'][$key]['token'] == $player['id']) {
										$json['players'][$key]['losses'] = intval($json['players'][$key]['losses'])+1;
										$json['players'][$key]['average'] = intval($json['players'][$key]['average'])-1;
									}
								}
								if ($player['state'] == 'Booted') {
									// remove player from ladder list
									removePlayerFromList($player['id']);
								}
							}
						}
					}
				}				
	
			}
		}
		
		// flag all json db players as innactive
		foreach ($json['players'] as &$player) {
			$player['active'] = 'false';
		}
		
		//var_dump($json);
		//echo '<br><br>';
		
		$listplayers = getPlayersList();
		
		// clean up $list of players, in case there are empty lines
		foreach (array_keys($listplayers, '') as $key) {
			unset($listplayers[$key]);
		}
		
		// flag back into active everyone included in the list
		//  this purges from active state everyone who was booted or has declined a game
		//  they should already be removed from the official list during game check algo
		unset($player);
		foreach ($json['players'] as &$player) {
			$active = false;
			unset($thisplayer);
			foreach ($listplayers as $thisplayer) {
				//echo $thisplayer.' '.$player['token'].'<br>';
				if ($thisplayer == $player['token']) $active = true;
			}
			if ($active) $player['active'] = 'true';
				else $player['active'] = 'false';
		}
		
		//var_dump($json);
		//echo '<br><br>';
		
		// go through the official list and see if any player is missing
		//   not already present or if present is inactive
		// this makes sure new players are added or turned back into active state
		unset($thisplayer);
		foreach ($listplayers as $thisplayer) {
		
			$present = false;
			unset($player);
			foreach ($json['players'] as &$player) {
				if ($thisplayer == $player['token']) {
					$present = true;
					// if he's already present, make sure he is active
					$player['active'] = 'true';
				}
			}
			
			if (!$present) {				
				
				$playerjson = API_ValidateInviteToken($thisplayer);
				
				if (array_key_exists('error',$playerjson)) {
					echo 'error retrieving player info from id '.$thisplayer;
					continue;
				}
				
				// add to index db
				unset($playerjson['tokenIsValid']);
				unset($playerjson['isMember']);
				unset($playerjson['color']);
				unset($playerjson['tagline']);
				$playerjson['token'] = ''.$thisplayer; //needs to be string
				$playerjson['wins'] = '0';
				$playerjson['losses'] = '0';
				$playerjson['average'] = '0';	
				$playerjson['active'] = 'true';			
				array_push($json['players'], $playerjson);
			}
		}

		// sort index db players list by average (wins-losses, prioritize wins on draw)
		foreach ($json['players'] as $key => $row) {
			$avg[$key]  = $row['average'];
			$win[$key] = $row['wins'];
		}
		array_multisort($avg, SORT_DESC, $win, SORT_DESC, $json['players']);

		// check how many players are active
		$activecount = 0;
		unset($oplayer);
		foreach ($json['players'] as $oplayer) {
			if ($oplayer['active'] == 'true') {
				$activecount++;
				echo $oplayer['token'].' '.$oplayer['name'].'<br>';
			}
		}
		
		// figure out what template should be used for the next batch of games
		// its always the one less used so far
		$templateID = $templates[0]['templateID'];
		$templateIDteams = $templates[0]['templateIDteams'];
		$gameName = $templates[0]['gameName'];
		$lowestcount = 0;
		foreach ($templates as $template) {
			$countgames = 0;
			foreach ($json['games'] as $game) {
				if (array_key_exists('templateID',$game)){
					if (
						($game['templateID'] == $template['templateID']) || 
						($game['templateID'] == $template['templateIDteams'])
					   ) $countgames++;
				} else {
					// if there is no templateID stored it means the game was created
					// during the initial eastasia stage of the ladder,
					// so it's +1 for that template
					// this patch should only be required until json is updated
					// with proper template info on all games
					if ($template['templateID'] == $templates[0]['templateID']) 
							$countgames++;
				}
			}
			
			//first is always the initial lowest
			if ($template['templateID'] == $templates[0]['templateID']) {
				$lowestcount = $countgames;
			}
			
			//echo $countgames.' '.$lowestcount.'<br>';
			if ($countgames < $lowestcount) {
				$lowestcount = $countgames;
				$templateID = $template['templateID'];
				$templateIDteams = $template['templateIDteams'];
				$gameName = $template['gameName'];
			}
		}
		
		echo '<br>least played template: '.$gameName.'<br><br>';
				
	
		// create new games only when there are enough players
		//  and the number of pending games lower than the number of ranking tiers
		if (($activecount>=6) && ($pending <= $activecount / 6)) {
			// for all groups of 6 players createGame
			//echo 'inside<br>';
			//echo 'players: '.count($json['players']).'<br>';
			for ($i = 0; $i < count($json['players']);)
			//foreach ($json['players'] as $oplayer) {
			{
				//echo 'looping.. '.$i.'<br>';
				unset($players);
				$players = array(	0=>array('token' => ''),
									1=>array('token' => ''),
									2=>array('token' => ''),
									3=>array('token' => ''),
									4=>array('token' => ''),
									5=>array('token' => '')
								);
								
				for ($j=0; $j<6;) {
					if ($i >= count($json['players'])) break 2;
					if ($json['players'][$i]['active'] == 'true') {
						$players[$j]['token'] = $json['players'][$i]['token'];
						$j++;
						$i++;
					} else {
						$i++;
					}
				}	
				//var_dump($players);
				//echo '<br>';
				if ($players[5]['token'] != '') {
					echo 'creating game with..<br>';
					var_dump($players);
					echo '<br><br>';
					$personalMessage = 'http://tinyurl.com/wl-clot-random3vs3';
					
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
						echo 'created: '.$gameid.'<br><br>';
					}
					
					$gamejson = API_GameFeed($gameid);
					
					unset($gamejson['termsOfUse']);
					unset($gamejson['name']);
					foreach ($gamejson['players'] as &$gplayer) {
						unset($gplayer['email']);
						unset($gplayer['color']);
						unset($gplayer['isAI']);
					}	
					// need to add this property manually until Fizzer updates GameFeedAPI
					$gamejson['templateid'] = ''.$templateID; //needs to be string
					
					array_push($json['games'], $gamejson);
					
				}
			}
			
			// team up the highest ranked player with the 2 lowest
			$j = count($json['players']) - 1; //$i is top, $j is bottom
			for ($i = 0; $i+6 < $j; )
			{
				//echo 'looping.. '.$i.' '.$j.'<br>';
				unset($players);
				$players = array(	0=>array('token' => '', 'team' => '0'),
									1=>array('token' => '', 'team' => '0'),
									2=>array('token' => '', 'team' => '0'),
									3=>array('token' => '', 'team' => '1'),
									4=>array('token' => '', 'team' => '1'),
									5=>array('token' => '', 'team' => '1')
								);
				
				// top player, leader of team 1
				while($players[0]['token'] == '') {
					if ($json['players'][$i]['active'] == 'true')
						$players[0]['token'] = $json['players'][$i]['token'];
					$i++;
					//echo 'i: '.$i.'<br>';
					if ($i > $j) break 2;
				}
				
				// bottom player, member of team 1
				while($players[1]['token'] == '') {
					if ($json['players'][$j]['active'] == 'true')
						$players[1]['token'] = $json['players'][$j]['token'];
					$j--;
					//echo 'j: '.$j.'<br>';					
					if ($i > $j) break 2;
				}
				
				// second top player, leader of team 2
				while($players[3]['token'] == '') {
					if ($json['players'][$i]['active'] == 'true')
						$players[3]['token'] = $json['players'][$i]['token'];
					$i++;
					//echo 'i: '.$i.'<br>';
					if ($i > $j) break 2;
				}
				
				// second bottom player, member of team 2
				while($players[4]['token'] == '') {
					if ($json['players'][$j]['active'] == 'true') $players[4]['token'] = $json['players'][$j]['token'];
					$j--;
					//echo 'j: '.$j.'<br>';
					if ($i > $j) break 2;
				}

				// third bottom player, member of team 1
				while($players[2]['token'] == '') {
					if ($json['players'][$j]['active'] == 'true') $players[2]['token'] = $json['players'][$j]['token'];
					$j--;
					//echo 'j: '.$j.'<br>';					
					if ($i > $j) break 2;
				}			

				// fourth bottom player, member of team 2
				while($players[5]['token'] == '') {
					if ($json['players'][$j]['active'] == 'true') $players[5]['token'] = $json['players'][$j]['token'];
					$j--;
					//echo 'j: '.$j.'<br>';
					if ($i > $j) break 2;
				}			
				
				
				echo 'creating balanced game with..<br>';
				var_dump($players);
				echo '<br><br>';
				$personalMessage = 'http://tinyurl.com/wl-clot-random3vs3';
				
				$resultjson = API_CreateGame(
									$templateIDteams,
									$gameName,
									$personalMessage,
									$players
									);
						
				if (array_key_exists('error',$resultjson)) {
					echo 'error creating game with template '.$templateIDteams.' '.$gameName.' with players:<br>';
					var_dump($players);
					break;
				} else {
					$gameid = $resultjson['gameID'];
					echo 'created: '.$gameid.'<br><br>';
				}
				
				$gamejson = API_GameFeed($gameid);
				
				unset($gamejson['termsOfUse']);
				unset($gamejson['name']);
				foreach ($gamejson['players'] as &$gplayer) {
					unset($gplayer['email']);
					unset($gplayer['color']);
					unset($gplayer['isAI']);
				}	
				// need to add this property manually until Fizzer updates GameFeedAPI
				$gamejson['templateid'] = ''.$templateIDteams; //needs to be string
				
				array_push($json['games'], $gamejson);
					
			}
			
		}
		
		// update date
		$json['datetime'] = $now->format('Y/m/d H:i:s');
		
		echo json_encode($json);
		saveJSON($json);
	
	}
	
}

echo 'done';

?>
