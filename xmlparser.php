<?php

$filename = 'rawxml.xml';
$file = fopen($filename, 'r');
$xmlinput = '';
while ($buffer = fgets($file, 4906)) {
	$xmlinput .= $buffer;
}
fclose($file);
$xml = simplexml_load_string($xmlinput);
if ($xml->Line->SipStatus == 'Registered') {
	echo "Yeahhhh!!!";
}

?>
