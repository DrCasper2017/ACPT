<?php

$filename = 'rawarp.txt';
$file = fopen($filename, 'r');
$txtinput = '';
while ($buffer = fgets($file, 256)) {
	$arp = explode(" ", preg_replace('/\s+/', ' ',$buffer));
	echo "$arp[1]\n";
}
fclose($file);
?>
