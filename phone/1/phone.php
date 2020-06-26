<?php
//print_r($_REQUEST);
//writeToLog($_REQUEST, 'incoming');

require_once (__DIR__.'/crest/crest.php');
require_once (__DIR__.'/lib/freeSwitchEsl.php');
$freeswitch = new Freeswitchesl();

$mypbxsocketip = '44.44.44.44'; //public ip
$myport = "8021";
$mypass = "ClueCon";
$mypbx = "192.168.1.3"; //local ip
$waittime = 1;

$callto = isset($_POST['data']['PHONE_NUMBER']) ? $_POST['data']['PHONE_NUMBER'] : 0;
$calltoi = isset($_POST['data']['PHONE_NUMBER_INTERNATIONAL']) ? $_POST['data']['PHONE_NUMBER_INTERNATIONAL'] : 0;
$userid = isset($_POST['data']['USER_ID']) ? $_POST['data']['USER_ID'] : 0;
$entitytype = isset($_POST['data']['CRM_ENTITY_TYPE']) ? $_POST['data']['CRM_ENTITY_TYPE'] : 0;
$entityid = isset($_POST['data']['CRM_ENTITY_ID']) ? $_POST['data']['CRM_ENTITY_ID'] : '';

$auth = isset($_POST['auth']['application_token']) ? $_POST['auth']['application_token'] : 0;
$domain = isset($_POST['auth']['domain']) ? $_POST['auth']['domain'] : '';

if($auth === "bitrix_token" && $domain === "bitrix_domain"){
	
$contact = ( CRest :: call (
    'crm.contact.list' ,
   		[
 	 	 'FILTER' => ['PHONE' => $callto],
	 	 'SELECT' => ['ID','ASSIGNED_BY_ID'],
	 	 //'EMAIL'=> [['VALUE' => 'lola@yea.com', 'VALUE_TYPE' => 'WORK']] ,
	 	 //'PHONE'=> [['VALUE' => '123458', 'VALUE_TYPE' => 'WORK']] ,
    	])
	);
//writeToLog($contact['result'][0]['ID'], 'Contact ID.');
	
$responsable = ( CRest :: call (
    	'user.get' ,
   			[
 	  			'FILTER' => ['ID' => $userid],
				'SELECT' => ['UF_PHONE_INNER','WORK_PHONE','PERSONAL_MOBILE'],
    		])
		);
//writeToLog($responsable['result'][0]['UF_PHONE_INNER']); //extencion
$caller = $responsable['result'][0]['UF_PHONE_INNER'];
//writeToLog($caller, 'User EXT.');
	
$connect = $freeswitch->connect($mypbxsocketip,$myport,$mypass);
sleep($waittime);
if ($connect) {
	//$call = $freeswitch->api("originate", "sofia/internal/77@192.168.15.62 &bridge(sofia/internal/78@192.168.15.62)");
	$call = $freeswitch->api("originate", "sofia/internal/" . $caller . "@" . $mypbx . " &bridge(sofia/gateway/8054240f-5678-4885-b18f-5678/" . $callto . ")");
} else {
	$connect = $freeswitch->connect($mypbxsocketip,$myport,$mypass);
	sleep($waittime);
	if ($connect) {
		$call = $freeswitch->api("originate", "sofia/internal/" . $caller . "@" . $mypbx . " &bridge(sofia/gateway/8054240f-5678-4885-b18f-5678/" . $callto . ")");
	}
}
$freeswitch->disconnect();
	
if(isset($call) && $call != ""){
	
	$timeline = ( CRest :: call (
    'crm.timeline.comment.add' ,
   	[
		'fields' =>
           [
               "ENTITY_ID" => $contact['result'][0]['ID'],
               "ENTITY_TYPE" => "contact",
               "COMMENT" => "A call was done to this Contact with result: ". $call,
           ]
   	])
	);
	
	$setmessage = ( CRest :: call (
    	'im.notify' ,
   		[
			"to" => $responsable['result'][0]['ID'],
         	"message" => "A call was done to this Contact with result: ". $call,
         	"type" => 'SYSTEM',
   		])
	);
	
}	
	
} //end if

/**
 * Write data to log file.
 *
 * @param mixed $data
 * @param string $title
 *
 * @return bool
 */
function writeToLog($data, $title = '') {
 $log = "\n------------------------\n";
 $log .= date("Y.m.d G:i:s") . "\n";
 $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
 $log .= print_r($data, 1);
 $log .= ob_get_flush();
 $log .= "\n------------------------\n";
 file_put_contents(getcwd() . '/hook.log', $log, FILE_APPEND);
 return true;
}  

?>