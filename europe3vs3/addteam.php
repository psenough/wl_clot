<?php


//error_reporting(E_ALL);
//ini_set('display_errors', '1');

require('../common/auth.php');
require('../common/dopostrequest.php');
require('../common/savejsonbackup.php');

// custom version
// get player info from warlight
function API_ValidateInviteToken($player_id) {
	global $email;
	global $apitoken;
	
	$url = 'http://warlight.net/API/ValidateInviteToken.aspx?Token='.$player_id;
	$arr = array(
			'Email' 		=> $email,
			'APIToken' 		=> $apitoken
		);
	$result = do_post_request($url, http_build_query($arr));
	
	$playerjson = json_decode($result, true);
	
	if (array_key_exists('error',$playerjson) || !$playerjson) {
		echo 'error retrieving player info from id '.$player_id;
		die;
	}
		
	return json_decode($result, true); 
}


date_default_timezone_set('UTC');

if (
	($_POST['code'] == '') &&
	($_POST['p1'] != '') &&
	($_POST['p2'] != '') &&
	($_POST['p3'] != '')
   ) {

	//echo 'inside<br>';

	$now = new DateTime("now");
	//echo 'now: '.$now->format('Y/m/d H:i:s').'<br>';

	$index = 'indexes/index_latest.json';
	$string = file_get_contents($index);
	if ($string) {
		//echo $string;
		$json=json_decode($string,true);
		
		// update date
		$json['datetime'] = $now->format('Y/m/d H:i:s');
	
		$team = array(
			"name" => "",
			"players" => array(),
			"wins" => 0,
			"losses" => 0,
			"rating" => 0,
			"maxgames" => 2,
			"pendinggames" => 0,
			"active" => "true"
		);
		
		$players = array();
		array_push($players, $_POST['p1']);
		array_push($players, $_POST['p2']);
		array_push($players, $_POST['p3']);
		
		// check if team is a dupe
		foreach ($json['teams'] as &$jsonteam) {
			$foundteam = 0;
		
			foreach ($jsonteam['players'] as $jsonplayer) {
				foreach ($players as $thisplayer) {
					if ($jsonplayer['token'] == $thisplayer) $foundteam++;
				}
			}
		
			if ($foundteam == 2) {
				echo 'found dupe team<br>';
				if ($jsonteam['active'] == "true") {
					echo 'already active, nothing to do here...';
				} else {
					echo 'reactivating team<br>';
					$jsonteam['active'] = "true";
					saveJSON($json);
				}
				die;
			}
		}
		
		// validate if all tokens are valid
		foreach($players as $player) {
			$playerjson = API_ValidateInviteToken($player);
			unset($playerjson['tokenIsValid']);
			unset($playerjson['isMember']);
			unset($playerjson['color']);
			unset($playerjson['tagline']);
			$playerjson['token'] = ''.$player; //needs to be string	
			array_push($team['players'], $playerjson);
		}
		
		array_push($json['teams'], $team);
		//echo json_encode($json);
		saveJSON($json);
	
	}

	echo 'done adding<br>';
	var_dump($team['players']);
	
} else {
?>
	<form action="addteam.php" method="post" enctype="multipart/form-data">
	 <table>
		<tr>
			<td>p1:</td>
			<td><input type="text" name="p1" value="" size="20"></td>
		</tr>
		<tr>
			<td>p2:</td>
			<td><input type="text" name="p2" value="" size="20"></td>
		</tr>
		<tr>
			<td>p3:</td>
			<td><input type="text" name="p3" value="" size="20"></td>
		</tr>
		<tr>
		 <td>key:</td>
		 <td><input type="password" name="code" value="" size="10"><input type="submit" value="submit"></td>
		</tr>
	 </table>
	</form>
<?php

}

?>
