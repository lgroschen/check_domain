#!/usr/bin/php
<?php
// Check Domain PLUGIN
//
// Copyright (c) 2014 Nagios Enterprises, LLC.  All rights reserved.
//  
// $Id: $lgroschen

define( "PROGRAM", 'check_domain.php' );
define( "VERSION", '1.0.0' );

// include_once(dirname(__FILE__).'/../check_domain.php');

// ini_set( 'display_errors', true );
// ini_set( 'display_startup_errors', true );

define( "STATUS_OK",       0 );
define( "STATUS_WARNING",  1 );
define( "STATUS_CRITICAL", 2 );
define( "STATUS_UNKNOWN",  3 );

$critical = 7;
$warning = 30;
$domain = "";
$whois_path = "";
$shortopts = "o";
$shortopts .= "h::";
$shortopts .= "d:";
$shortopts .= "w:";
$shortopts .= "c:";
$shortopts .= "P:";
$longopts = array(
	"help::",
	"domain:",
	"warning:",
	"critical:",
);

$options = getopt($shortopts, $longopts);

var_dump($options);

foreach ($options as $opts) {

	switch ($opts) {

		case "-c":
			$critical = $options['c'];
			break;

		case "--critical":
			$critical = $options['critical'];
			break;

		case "-w":			
			$warning = $options['w'];
			break;

		case "--warning":
			$warning = $options['warning'];
			break;

		case "-d":
			$domain = $options['d'];
			break;

		case "--domain":
			$domain = $options['domain'];
			break;

		case "-P":
			$whois_path = $options['P'];
			break;

		case "--path":
			$whois_path = $options['path'];
			break;

		case "-h":
		case "--help":
			fullusage();
			exit(STATUS_UNKNOWN);

		// default:
			// echo "*Unrecognized Argument Given*\n\n";
			// fullusage();
			// exit(STATUS_UNKNOWN);
	}

	var_dump($critical, $warning, $domain);
}


function fullusage() {
print(
"check_domain.php - v1.0.0
Copyright (c) 2005 Tomàs Núñez Lirola <tnunez@criptos.com>, 2009-2014 Elan Ruusamäe <glen@pld-linux.org>
Under GPL v2 License

This plugin checks the expiration date of a domain name.

Usage: ".PROGRAM." -h | -d <domain> [-c <critical>] [-w <warning>]
NOTE: -d must be specified

Options:
-h
     Print this help and usage message
-d
     Domain name to query against
-w
     Response time to result in warning status (days)
-c
     Response time to result in critical status (days)

This plugin will use whois service to get the expiration date for the domain name.
Example:
     ".PROGRAM." -d www.nagios.com -w 30 -c 10 \n\n"
    );
}


exec('which whois 2>&1', $output, $return_var);
$whois_path = $output[0];

if( $return_var != 0) {
    echo 'It looks like you are missing jWhois on your Nagios XI server. Run: <p><b>yum install jwhois -y</b></p> as root user on your Nagios XI server.';
}

if($whois_path) {
	if(file_exists($whois_path) && exec($whois_path)) {
		$whois = $whois_path;
	} elseif(exec($whois_path."/whois")) {
		$whois=$whoispath."/whois";
	}

	if(!file_exists($whois)) {
		die($STATE_UNKNOWN." UNKNOWN - Unable to find whois binary, you specified an incorrect path");
	}

} else {
	if(!exec("type whois > /dev/null 2>&1")) {
		die($STATE_UNKNOWN." UNKNOWN - Unable to find whois binary in your path. Is it installed? Please specify path.");
	}
}

// $ouput = $whois + $domain;





