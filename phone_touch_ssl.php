#!/usr/bin/php
<?php
//#############################################################################
//
//   AudioCodes IP Phones web-API control script
//   v.1.1
//   Coded by Dmitry Nikitin (d.nikitin@gge.ru)
//
//   Command-line options:
//   -R Force ALL found devices to reboot
//   -r Force *unregistered* devices to reboot
//   -s Scan only (no reboot at all)
//   -c Get IP addresses from a hostname/IP specified as [source]
//   -f Get IP addresses from file specified as [source]
//
//   REQUIREMENTS:
//   PHP (php-cli) version 5.x or higher
//   HTTP::Response2 PHP module (pear)
//   clogin (rancid) tools to retrieve ARP/IP lists directly from Cisco switch
//   
//   CONSTANTS
//   LOGIN		- default username for 440HD
//   PASS		- default password for 440HD
//   CLOGIN		- path to clogin executable
//   DEBUG		- true/false allow/disallow debug & errors output
//
//#############################################################################

require_once 'HTTP/Request2.php';

// DEFINITIONS ===============================================================

define ('LOGIN'	,	'admin');
define ('PASS'	,	'1234');
define ('CLOGIN',	'/home/dnikitin/bin/clogin -u admin -c "terminal length 0; show ip arp vrf LAN | inc 0090.8f85; exit" '); 
define ('DEBUG'	,	false);

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
			$iplist = parseFromSwitch($argv[3]);
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
exit;
	if (($argv[1] == '-R') || ($argv[1] == '-r') || ($argv[1] == '-s')) {
		echo "Starting scan/restart process:\n";
		sort($iplist, SORT_NATURAL);
		foreach ($iplist as $ip) {
			$count++;
			//if ($count > 10) break; // TEST SUITE
			printf("[%s] #%'02d %s -> ", date('Y/m/d H:i:s'), $count, $ip);
			$status = checkStatus($ip);
			if ($status == 0) { // Unregistered
				if ($argv[1] == '-R' || $argv[1] == '-r') {
					echo "UNREGISTERED -> Reboot\n";
					forceReboot($ip);
				} else {
					echo "UNREGISTERED\n";
				}
			} elseif ($status == 1) { // Registered
				if ($argn[1] == '-R') {
					echo "FORCED reboot!\n";
					forceReboot($ip);
				} else {
					echo "Registered\n";
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
function parseFromSwitch($host) {
	$rawlist = array();
	$iplist = array();
	exec(CLOGIN.$host, $rawlist);
	foreach ($rawlist as $rec) {
		$pattern = '/^Internet\s+(\d+)\.(\d+)\.(\d+)\.(\d+).*$/';
		$replacement = '$1.$2.$3.$4';
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
	$scriptname [-option] [-c|-f] [source]

Options:
	-R Force ALL found devices to reboot
	-r Force *unregistered* devices to reboot
	-s Scan only (no reboot at all)
	-c Get IP addresses from a hostname/IP specified as [source]
	-f Get IP addresses from file specified as [source]


END;
}
?>
