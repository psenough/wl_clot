<?php

// get game info from warlight
function API_GameFeed($value_id) {
	global $email;
	global $apitoken;
	
	$url = 'https://www.warlight.net/API/GameFeed?GameID='.$value_id;
	//echo $url.'<br><br>';

	$arr = array(
			'Email' 		=> $email,
			'APIToken' 		=> $apitoken
		);
	$result = do_post_request($url, http_build_query($arr));
	return json_decode($result, true);
}

// get player info from warlight
function API_ValidateInviteToken($player_id) {
	global $email;
	global $apitoken;
	
	$url = 'https://www.warlight.net/API/ValidateInviteToken.aspx?Token='.$player_id;
	$arr = array(
			'Email' 		=> $email,
			'APIToken' 		=> $apitoken
		);
	$result = do_post_request($url, http_build_query($arr));
	return json_decode($result, true); 
}

// delete a lobby game from warlight
function API_DeleteLobbyGame($game_id) {
	global $email;
	global $apitoken;
	
	$url = 'https://www.warlight.net/API/DeleteLobbyGame';
	$arr = array(
			'Email' 		=> $email,
			'APIToken' 		=> $apitoken,
			'gameID'		=> $game_id
		);
	$result = do_post_request($url, json_encode($arr));
	return json_decode($result, true);
}

// create a game in warlight
function API_CreateGame($templateID, $gameName, $personalMessage, $players) {
	global $email;
	global $apitoken;
	
	$arr = array(
			'hostEmail' 	=> $email,
			'hostAPIToken'	=> $apitoken,
			'templateID' 	=> $templateID,
			'gameName'		=> $gameName,
			'personalMessage' => $personalMessage,
			'players'		=> $players
			);
	$jsonString = json_encode($arr);
	$url = 'https://www.warlight.net/API/CreateGame'; 
	$result = do_post_request($url, $jsonString);
	return json_decode($result, true);				
}

?>
