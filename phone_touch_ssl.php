#!/usr/bin/php
<?php
//#############################################################################
//
//   AudioCodes IP Phones web-API control script
//   v.2.2
//   Coded by Dmitry Nikitin (d.nikitin@gge.ru)
//
//   Command-line options:
//   -R Force ALL found devices to reboot
//   -r Force *unregistered* devices to reboot
//   -s Scan only (no reboot at all)
//   -c Get IP addresses from Cisco hostname/IP specified as [source]
//   -h Get IP addresses from HPE hostname/IP specified as [source]
//   -f Get IP addresses from file specified as [source]
//
//   REQUIREMENTS:
//   PHP (php-cli) version 5.x or higher
//   HTTP::Response2 PHP module (pear)
//   sshpass to take password from command line (reading from file)
//   clogin (rancid) tools to retrieve ARP/IP lists directly from Cisco switch
//   
//   CONSTANTS
//   LOGIN		- default username for 440HD
//   PASS		- default password for 440HD
//   CMD_SSH	- sshpass/ssh command line
//   CMD_ARP	- arp table display command (HPE specific)
//   CLOGIN		- clogin path/command
//   DEBUG		- true/false allow/disallow debug & errors output
//
//#############################################################################

require_once 'HTTP/Request2.php';

// DEFINITIONS ===============================================================

define ('LOGIN'	,	'admin');
define ('PASS'	,	'223344');
define ('DEBUG'	,	false);
define ('EXCLUDE',	'/home/dnikitin/.phone_touch_exclude');
define ('CMD_SSH',	'/usr/bin/sshpass -f /home/dnikitin/.netsecret ssh 00uc_net@');
define ('CMD_ARP',	' "disp arp | inc 0090.8f85"');
define ('CLOGIN',	'/home/dnikitin/bin/clogin -u 00uc_net -c "terminal length 0; show arp | inc 0090.8f85; exit" '); 

if (DEBUG) {
	error_reporting(E_ALL);
} else {
	error_reporting(0);
}

$count = 0;

// MAIN ROUTINE ==============================================================

# Check for command-line parameters
if (count($argv) <= 1) {
	showHelp(preg_replace('/^.*\//', '', $argv[0]));
} else {
	if (isset($argv[2]) && isset($argv[3])) {
		if ($argv[2] == '-c') {
			echo "Getting IP list from Cisco [$argv[3]]: ";
			$iplist = parseFromCisco($argv[3]);
		} elseif ($argv[2] == '-h') {
			echo "Getting IP list from HP $argv[3]: ";
			$iplist = parseFromHP($argv[3]);
		} elseif ($argv[2] == '-f') {
			echo "Getting IP addresses from $argv[3]: ";
			$iplist = parseFromFile($argv[3]);
		} else {
			showHelp(preg_replace('/^.*\//', '', $argv[0]));
			exit;
		}	
		echo count($iplist) . " device(s) found.\n";
	} else {
		showHelp(preg_replace('/^.*\//', '', $argv[0]));
	}
#exit;
	if (($argv[1] == '-R') || ($argv[1] == '-r') || ($argv[1] == '-s')) {
		echo "Starting scan/restart process:\n";
		sort($iplist, SORT_NATURAL);
		$exlist = getExclusions(EXCLUDE);
		$count = 1;
		foreach ($iplist as $data) {
			list($ip, $mac) = explode(":", $data);
			printf("[%s] #%'02d %s (%s) -> ", date('Y/m/d H:i:s'), $count, $ip, $mac);
			$count++;
			if (count($exlist)) {
				if (!in_array($mac, $exlist)) {
					//if ($count > 10) break; // TEST SUITE
					$status = checkStatus($ip);
					if ($status == 0) { // Unregistered
						if ($argv[1] == '-R' || $argv[1] == '-r') {
							echo "UNREGISTERED -> Reboot\n";
							forceReboot($ip);
						} else {
							echo "UNREGISTERED\n";
						}
					} elseif ($status == 1) { // Registered
						if ($argv[1] == '-R') {
							echo "FORCED reboot!\n";
							forceReboot($ip);
						} else {
							echo "Registered\n";
						}
					}
				} else {
					echo "Skipped (found in exclusions)\n";
				}
			}
		}
		break;
	} else {
		showHelp(preg_replace('/^.*\//', '', $argv[0]));
	}
}

exit;

// FUNCTIONS =================================================================

# Getting IP addresses from Cisco switch
function parseFromCisco($host) {
	$rawlist = array();
	$iplist = array();
	exec(CLOGIN.$host, $rawlist);
	foreach ($rawlist as $rec) {
		$pattern = '/^Internet\s+(\d+)\.(\d+)\.(\d+)\.(\d+)\s+\d+\s+(\w+)\.(\w+)\.(\w+).*$/';
		$replacement = '$1.$2.$3.$4:$5$6$7';
		if (preg_match($pattern, $rec)) { 
			array_push($iplist, preg_replace($pattern, $replacement, $rec));
		}
	}
	//exit;
	return $iplist;
}

# Getting IP addresses from HP switch
function parseFromHP($host) {
	$rawlist = array();
	$iplist = array();
	if (DEBUG) {
		echo "\nDebug (exec): ".CMD_SSH.$host.CMD_ARP_HP."\n";
	}
	exec(CMD_SSH.$host.CMD_ARP, $rawlist);
	foreach ($rawlist as $rec) {
		$pattern = '/^(\d+)\.(\d+)\.(\d+)\.(\d+)\s+(\w+)-(\w+)-(\w+)\s+.*$/';
		$replacement = '$1.$2.$3.$4:$5$6$7';
		if (preg_match($pattern, $rec)) { 
			array_push($iplist, preg_replace($pattern, $replacement, $rec));
		}
	}
	return $iplist;
}

# Getting IP addresses from file
function parseFromFile($filename) {
	$iplist = array();
	$file = fopen($filename, 'r');
	if (!$file) die ("ERROR: Can't open file: \"$filename\"!\n");
	while ($buffer = fgets($file, 256)) {
		$arp = explode(' ', preg_replace('/\s+/', ' ',$buffer));
		array_push($iplist, $arp[1]);
	}
	fclose($file);
	return $iplist;
}

# Getting exclusions from file
function getExclusions($filename) {
	$exlist = array();
	$file = fopen($filename, 'r');
	if ($file) {
		while ($buffer = fgets($file, 256)) {
			array_push($exlist, trim($buffer));
		}
		fclose($file);
		return $exlist;
	}
}

function checkStatus($ip) {
	//return rand(0,1); // TEST SUITE
	##############
	$request = new HTTP_Request2();
	$request->setUrl('https://' . $ip . '/login.cgi');
	$request->setMethod(HTTP_Request2::METHOD_POST);
	$request->setConfig(array(
	    'ssl_verify_peer'	=>	FALSE,
	    'ssl_verify_host'	=>	FALSE
	));
	$request->addPostParameter(array(
		'user'	=>	LOGIN,
		'psw'	=>	base64_encode(PASS)
	));
	
	try {
		$response = $request->send();
		$cookie = $response->getCookies();
		if (200 == $response->getStatus()) {
			try {
				$request->setUrl('https://' . $ip . '/voip_status.cgi');
				$request->addCookie('session', $cookie[0]['value']);
				$response = $request->send();
				if (200 == $response->getStatus()) {
					$xml = simplexml_load_string($response->getBody());
					if ($xml->Line->SipStatus == 'Registered') {
						return 1;
					} else {
						return 0;
					}
				}
			} catch (HTTP_Request2_Exception $e) {
				echo 'Error: ' . $e->getMessage() . "\n";
			}
		}
	} catch (HTTP_Request2_Exception $e) {
		echo 'Error: ' . $e->getMessage() . "\n";
	}
	return -1;
}

function forceReboot($ip) {
	$request = new HTTP_Request2();
	$request->setUrl('https://' . $ip . '/login.cgi');
	$request->setMethod(HTTP_Request2::METHOD_POST);
	$request->setConfig(array(
	    'ssl_verify_peer'	=>	FALSE,
	    'ssl_verify_host'	=>	FALSE
	));
	$request->addPostParameter(array(
		'user'	=>	LOGIN,
		'psw'	=>	base64_encode(PASS)
	));
	
	try {
		$response = $request->send();
		$cookie = $response->getCookies();
		if (200 == $response->getStatus()) {
			try {
				$request->setUrl('https://' . $ip . '/mainform.cgi/reboot.htm');
				$request->addCookie('session', $cookie[0]['value']);
				$response = $request->send();
				if (200 == $response->getStatus()) {
					$request->addPostParameter('REBOOT', 2);
					$response = $request->send();
				}
			} catch (HTTP_Request2_Exception $e) {
				echo 'Error: ' . $e->getMessage() . "\n";
			}
		}
	} catch (HTTP_Request2_Exception $e) {
		echo 'Error: ' . $e->getMessage() . "\n";
	}
}

function showHelp($scriptname) {
	print<<<END

Usage:
	$scriptname COMMAND OPTION SOURCE

Commands:
	-R Force ALL found devices to reboot
	-r Force *unregistered* devices to reboot
	-s Scan only (no reboot at all)

Options:
	-c Get IP addresses from Cisco switch
	-h Get IP addresses from HPE switch
	-f Get IP addresses from file

Source:
	IP-address/hostname of the router or path to a file with IP-addresses


END;
}
?>
