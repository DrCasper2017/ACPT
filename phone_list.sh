#!/bin/bash
###############################################################################
##
##   AudioCodes IP Phones list generator
##   v.1.0
##   Coded by Dmitry Nikitin
##  
##   VARS
##   net	- network (up to class "C") to be scanned/processed
##   addr	- first address (last octet of IPv4) of the specified $net
##   last	- last address (last octet of IPv4) of the specidied $net
##
###############################################################################

net=10.40.8
addr=1
last=255

# DO NOT MODIFY SCRIPT BELOW THIS LINE
until [ "$addr" -eq $last ]
do
	echo "$net.$addr"
	let "addr++"
done
