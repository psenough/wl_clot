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

//
// when game is done go through $players and group them in winning and losing teams
// find out which one corresponds to $json['teams'] and update them
//
function updateTeams($players, $json) {

	unset($teams);
	$teams = array( 'won' => array(), 'lost' => array() );
	foreach ($players as $player) {
		if ($player['state'] == 'Won') {
			array_push($teams['won'], $player['id']);
		} else {
			array_push($teams['lost'], $player['id']);
		}
	}
	
	//updateWinnerTeam($teams['won']);
	unset($jsonteam);
	foreach ($json['teams'] as &$jsonteam) {
		$foundteam = 0;
		
		foreach ($jsonteam['players'] as $jsonplayer) {
			foreach ($teams['won'] as $thisplayer) {
				if ($jsonplayer['token'] == $thisplayer) $foundteam++;
			}
		}
		
		if ($foundteam == 3) {
			echo 'updating team with win<br>';
			$jsonteam['wins']++;
			$jsonteam['rating']++;
			//$jsonteam['pendinggames']--;
			break;
		}
	}

	//updateLosingTeam($teams['lost']);
	unset($jsonteam);
	foreach ($json['teams'] as &$jsonteam) {
		$foundteam = 0;
		
		foreach ($jsonteam['players'] as $jsonplayer) {
			foreach ($teams['lost'] as $thisplayer) {
				if ($jsonplayer['token'] == $thisplayer) $foundteam++;
			}
		}
		
		if ($foundteam == 3) {
			echo 'updating team with win<br>';
			$jsonteam['losses']++;
			$jsonteam['rating']--;
			//$jsonteam['pendinggames']--;
			break;
		}
	}

	return true;
}

//
// check dupe games
//
function checkDupeGames($jsongames, $gameplayers) {
	global $now;
	
	foreach ($jsongames as $g1) {

		$sixcount = 0;
		foreach ($g1['players'] as $p1) {
			foreach ($gameplayers as $p2) {
				//echo 'comparing: '.$p1['id'].' with '.$p2.'<br>';
				if ($p1['id'] == $p2) $sixcount++;
			}
		}
		//echo 'count: '.$sixcount.'<br>';
		if ($sixcount == 6) {
			// it's a dupe, but it might be old and crippled
			//  and in that case it's not considered a dupe anymore
			
			// if its a pending game, it's automatically dupe
			if ($g1['state'] != 'Finished') {
				echo 'dupe being played: '.$g1['id'].'<br>';
				return true;
			} else {
				// if its not a pending game but has recently been finished, it's still a dupe
				if (array_key_exists('datetimefinished',$g1)) {
					$then = DateTime::createFromFormat('Y/m/d H:i:s', $g1['datetimefinished']);	
					$interval = date_diff($now, $then);
					if ($interval->format('%a') < 30) {
						echo 'dupe finished less then a month ago: '.$g1['id'].'<br>';
						return true;
					}
				}
			}
			
			// any other case (finished and older then 30 days)
			// means its no longer considered a dupe
			// so just stay calm and carry on
			
		}
	}
	
	return false;
}

// 
// check names of players on a game
// see if their name in the json players list needs an update
//
// the system allows the users to change their names every few weeks
// checking for updates on all using validatetokenapi would become costly
// this way of checking on active games is gentler on the server
//
function checkNamesUpdates($gamejson, $json_teams) {
	foreach ($gamejson['players'] as $player) {
		foreach ($json_teams as &$team) {
			foreach ($team['players'] as &$thisplayer) {
				//echo 'comparing '.$thisplayer['name'].' to '.$player['name'].'<br>';
				if (($player['id'] == $thisplayer['token']) && ($player['name'] != $thisplayer['name'])) {
					//echo 'changing '.$thisplayer['name'].' to '.$player['name'].'<br><br>';
					$thisplayer['name'] = $player['name'];
				}
			}
		}
	}
}

$index = 'indexes/index_latest.json';
$string = file_get_contents($index);
if ($string) {
	//echo $string;
	$json=json_decode($string,true);
	
	//var_dump($json);
	
	//echo 'then: '.$json['datetime'].'<br>';
	$then = DateTime::createFromFormat('Y/m/d H:i:s', $json['datetime']);	
	$interval = date_diff($now, $then);
	
	// only update if it has passed 12 hours since last update
	if (($interval->format('%a')*24+$interval->format('%h')) > 8)
	{
	
		// check all games from index, if game is pending check if its over
		unset($value);
		foreach ($json['games'] as &$value) {
			//echo $value['id'].' game is '.$value['state'].'<br><br>';
			
			// if game was previously not finished, recheck it now
			if ($value['state'] != 'Finished') {
				
				$gamejson = API_GameFeed($value['id']);
				
				//echo $result;
				//var_dump($gamejson);//['termsOfUse'];
				if (array_key_exists('error',$gamejson)) {
					echo 'error retrieving gamefeed for '.$value['id'];
					continue;
				}
				
				checkNamesUpdates($gamejson,&$json['teams']);
				
				$deletedgame = false;
				unset($gamejson['termsOfUse']);
				unset($gamejson['name']);
				$value = $gamejson;
				
				if ($gamejson['state'] == 'Finished') {
					if (!updateTeams($gamejson['players'], &$json)) {
						echo 'error updating teams';
						die;
					}
					
					$value['datetimefinished'] = $now;
				}
			}
		}
		
		//
		// update number of pending games of teams
		//
		
		// set pendinggames of all teams to 0
		unset($jsonteam);
		foreach ($json['teams'] as &$jsonteam) {
			$jsonteam['pendinggames'] = 0;
		}
		
		// go through each active game and add pending games
		unset($game);
		foreach ($json['games'] as $game) {
			if ($game['state'] != 'Finished') {
				
				unset($teamA);
				$teamA = array();
				unset($teamB);
				$teamB = array();

				unset($player);
				foreach ($game['players'] as $player) {
					if ($player['team'] == '0') {
						array_push($teamA, $player['id']);
					} else {
						array_push($teamB, $player['id']);
					}
				}
				
				unset($jsonteam);
				foreach ($json['teams'] as &$jsonteam) {
					$foundteamA = 0;
					$foundteamB = 0;
					
					unset($jsonplayer);
					foreach ($jsonteam['players'] as $jsonplayer) {
						foreach ($teamA as $thisplayer) {
							if ($jsonplayer['token'] == $thisplayer) $foundteamA++;
						}
						foreach ($teamB as $thisplayer) {
							if ($jsonplayer['token'] == $thisplayer) $foundteamB++;
						}
					}
		
					if ($foundteamA == 3) {						
						$jsonteam['pendinggames']++;
					}
					if ($foundteamB == 3) {						
						$jsonteam['pendinggames']++;
					}
				}
				
			}
		}

		$moregames = 0;
		
		// sort teams by pendinggames, then rating
		// use the sort run to count number of $moregames for later use
		unset($rating);
		unset($games);
		foreach ($json['teams'] as $key => $row) {
			$rating[$key] = $row['rating'];
			$games[$key] = $row['maxgames'] - $row['pendinggames'];
			if (($row['maxgames'] - $row['pendinggames']) > 0) $moregames += $row['maxgames'] - $row['pendinggames'];
		}
		array_multisort($games, SORT_ASC, $rating, SORT_DESC, $json['teams']);
		
		//echo '<br><br>';
		//echo $moregames.'<br>';

		$gamesleft = true;
		while( $gamesleft ) {
	
			if ($moregames >= 2) {

				unset($players);
				$players = array(
								0=>array('token' => '', 'team' => '0'),
								1=>array('token' => '', 'team' => '0'),
								2=>array('token' => '', 'team' => '0'),
								3=>array('token' => '', 'team' => '1'),
								4=>array('token' => '', 'team' => '1'),
								5=>array('token' => '', 'team' => '1')
							);
				$idx1 = -1;
				$idx2 = -1;
				 
				$index = 0;
				foreach ($json['teams'] as $jsonteam) {
					//echo 'checking this team out:<br>';
					//var_dump($jsonteam);
					//echo '<br>looking sexy<br>';
					
					if ($jsonteam['pendinggames'] < $jsonteam['maxgames']) {
					
						// if we are already on second team
						if ($idx1 != -1) {
							//echo 'checking dupes... '.$players[0]['token'].' '.$jsonteam['players'][0]['token'].'<br>';
							$dupe = checkDupeGames(
											$json['games'],
											array( 	$players[0]['token'],
													$players[1]['token'],
													$players[2]['token'],
													$jsonteam['players'][0]['token'],
													$jsonteam['players'][1]['token'],
													$jsonteam['players'][2]['token']
													)
										);
							
							if ($dupe) {
								echo 'found a dupe game, skipping<br>';
								continue; // skip to next team
							}
							
							$players[3]['token'] = $jsonteam['players'][0]['token'];
							$players[4]['token'] = $jsonteam['players'][1]['token'];
							$players[5]['token'] = $jsonteam['players'][2]['token'];
							$idx2 = $index;
						} else {
							$players[0]['token'] = $jsonteam['players'][0]['token'];
							$players[1]['token'] = $jsonteam['players'][1]['token'];
							$players[2]['token'] = $jsonteam['players'][2]['token'];
							$idx1 = $index;
						}
						
					}
					
					$index++;
				}
				
				//var_dump($players); echo '<br>';
				
				//todo: refactor code above to try all possible team combos,
				//      current code is fixed around providing games for first team listed
				//      but it's possible that the first team has pending games which are dupes
				//      and that other teams below dont have dupes with each other
				//      in such case we should be creating games for those other teams
				//      and this code is not

				if ($players[5]['token'] != '') {
					
					echo 'creating a game with these suckers:<br>';
					var_dump($players);
					echo '<br>hope it works...<br><br>';
					
					$templateID = '301662';//'301366';
					$gameName = 'Europe 3vs3 Ladder';
					$personalMessage = 'http://tinyurl.com/wl-clot-eu3vs3';
				
					$resultjson = API_CreateGame($templateID, $gameName, $personalMessage, $players);
						
					if (array_key_exists('error',$resultjson)) {
						echo 'error creating game: ';
						var_dump($resultjson);
						echo '<br>';
						break;
					} else {
						$gameid = $resultjson['gameID'];
					}
				
					$gamejson = API_GameFeed($gameid);
				
					unset($gamejson['termsOfUse']);
					unset($gamejson['name']);
					foreach ($gamejson['players'] as &$gplayer) {
						unset($gplayer['email']);
						unset($gplayer['color']);
						unset($gplayer['isAI']);
					}	
			
					array_push($json['games'], $gamejson);
					
					$json['teams'][$idx1]['pendinggames']++;
					$json['teams'][$idx2]['pendinggames']++;
					$moregames -= 2;
					
				} else {
					$gamesleft = false;
				}
			} else {
				$gamesleft = false;
			}
		}
		
		// update date
		$json['datetime'] = $now->format('Y/m/d H:i:s');
		
		// sort teams by rating, prioritize wins
		foreach ($json['teams'] as $key => $row) {
			$avg[$key]  = $row['rating'];
			$win[$key] = $row['wins'];
		}
		array_multisort($avg, SORT_DESC, $win, SORT_DESC, $json['teams']);

		echo json_encode($json);
		saveJSON($json);
		
	}
	
}

echo 'done';

?>
