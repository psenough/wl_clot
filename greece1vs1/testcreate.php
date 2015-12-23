<?

error_reporting(E_ALL);
ini_set('display_errors', '1');

require('../common/auth.php');
require('../common/dopostrequest.php');
require('../common/warlight_gameapi.php');
require('../common/savejsonbackup.php');

date_default_timezone_set('UTC');
$now = new DateTime("now");
//echo 'now: '.$now->format('Y/m/d H:i:s').'<br>';


//lets create a new game	
$templateID = '305181';
$gameName = 'Greece 1vs1 Ladder';
unset($players);
$players = array( 	
					0=>array('token' => 'OpenSeat'),
					1=>array('token' => 'OpenSeat')
				);
//$personalMessage = 'http://tinyurl.com/wl-clot-greece';
$personalMessage = 'http://php-psenough.rhcloud.com/greece1vs1/';

$resultjson = API_CreateGame(
					$templateID,
					$gameName,
					$personalMessage,
					$players
					);

var_dump($resultjson);
/*if (array_key_exists('error',$resultjson)) {
	//echo 'error creating game with template '.$templateID.' '.$gameName.' with players:<br>';
	//var_dump($players);
	echo $resultjson['error'];
	die;
} else {
	$gameid = $resultjson['gameID'];
	echo 'done:'.$gameid;
}*/

?>