#!/bin/bash
###############################################################################
##
##   AudioCodes IP Phones reboot script
##   v.1.0
##   Coded by Dmitry Nikitin
##  
##   REQUIREMENTS:
##   telnet remote control should be available on the phones
##   
##   VARS
##   net	- network (up to class "C") to be scanned/processed
##   addr	- first address (last octet of IPv4) of the specified $net
##   last	- last address (last octet of IPv4) of the specidied $net
##   user	- default username to access the phone
##   pass	- default password to access the phone
##   cmd	- command to execute in remote shell (reboot by default)
##   logdir	- directory where log-files will be placed
##
###############################################################################
addr=1
last=2
net='127.0.0' #'10.40.0'
user=admin
pass=1234
cmd='reboot'
#cmd='ls && exit'
logdir='/home/user/logs'

# DO NOT MODIFY SCRIPT BELOW THIS LINE
logfile=phone_reboot_$(date +%Y%m%d)_$net.0.log
echo "Starting job @ $(date '+%Y-%m-%d %R:%S')" > $logdir/$logfile
until [ "$addr" -eq $last ]
do
	echo "[$(date '+%Y-%m-%d %R:%S')]: $net.$addr -> CONNECT" >> $logdir/$logfile
	(
		sleep 1
		echo ${user}
		sleep 1
		echo ${pass}
		sleep 1
		echo ${cmd}
		sleep 1
	) | telnet $net.$addr 2>&1 | tee -a $logdir/$logfile > /dev/null
	echo "[$(date '+%Y-%m-%d %R:%S')]: $net.$addr <- DONE" >> $logdir/$logfile
	echo >> $logdir/$logfile
	let "addr++"
done
