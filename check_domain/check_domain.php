#!/usr/bin/php
<?php
// Check Domain PLUGIN
//
// Copyright (c) 2014 Nagios Enterprises, LLC.  All rights reserved.
//  
// $Id: $lgroschen@nagios.com

define( "PROGRAM", 'check_domain.php' );
define( "VERSION", '1.0.0' );
define( "STATUS_OK",       0 );
define( "STATUS_WARNING",  1 );
define( "STATUS_CRITICAL", 2 );
define( "STATUS_UNKNOWN",  3 );

$domain = "";
$whois = "";
$whois_path = "";
$shortopts = "o";
$shortopts .= "h::";
$shortopts .= "d:";
$shortopts .= "w:";
$shortopts .= "c:";
$shortopts .= "P::";
$longopts = array(
	"help::",
	"domain:",
	"warning:",
	"critical:",
	"path::"
);

//get command options
$options = getopt($shortopts, $longopts);

//find jwhois path and set to variable
exec('which whois 2>&1', $execout, $return_var);
$whois_path = $execout[0];

if ($return_var != 0) {
    echo 'It looks like you are missing jWhois on your Nagios XI server. Run: <p><b>yum install jwhois -y</b></p> as root user on your Nagios XI server.';
}

if ($whois_path) {
	if(file_exists($whois_path) && exec($whois_path)) {
		$whois = $whois_path;
	} elseif(exec($whois_path."/whois")) {
		$whois = $whoispath."/whois";
	}

	if(!file_exists($whois)) {
		die($STATE_UNKNOWN." UNKNOWN - Unable to find whois binary, you specified an incorrect path");
	}

} else {
	if (!exec("type whois > /dev/null 2>&1")) {
		die($STATE_UNKNOWN." UNKNOWN - Unable to find whois binary in your path. Is it installed? Please specify path.");
	}
}

$options['P'] = $whois;
$options['path'] = $whois;

//check command options
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

		case "-h":
			fullusage();
			exit(STATUS_UNKNOWN);

		case "--help":
			fullusage();
			exit(STATUS_UNKNOWN);

		// default:
		// 	echo "*Unrecognized Argument Given*\n\n";
		// 	fullusage();
		// 	exit(STATUS_UNKNOWN);
	}
}

//get the expiration date string for our given domain
$execout = "";
if (array_key_exists('d', $options)) {
	exec('whois '.$options['d'].' | grep -i \'expir\|renew\|paid-till\'', $execout);
} elseif (array_key_exists('domain', $options)) {
	exec('whois'.$options['domain'].' | grep -i \'expir\|renew\|paid-till\'', $execout);
} else {
	echo "No domain specified or an internal error occured!";
}

//main plugin functionality
$raw_date = $execout[0];
$offset = strpos($raw_date, ":")+1;

if ($offset !== false) {
	$date = trim(substr($raw_date, $offset));
	$pdate = format_dates($date,$format='mdy');
}

$expire_seconds = strtotime($pdate);
$expire_date = $pdate;
$current_seconds = time();
$diff_seconds = $expire_seconds - $current_seconds;
$expire_days = round($diff_seconds / 86400);

var_dump($warning, $critical);

//plugin output
if ($expire_days < 0) {
	exit(STATUS_CRITICAL." CRITICAL - Domain ".$domain." expired on ".$expire_date."\n\n");
} elseif ($expire_days < $critical) {
	exit(STATUS_CRITICAL." CRITICAL - Domain ".$domain." will expire in ".$expire_days." days (".$expire_date.").\n\n");
} elseif ($expire_days < $warning) {
	exit(STATUS_WARNING." WARNING - Domain ".$domain." will expire in ".$expire_days." days (".$expire_date.").\n\n");
} else {
	exit(STATUS_OK." OK - Domain ".$domain." will expire in ".$expire_days." days (".$expire_date.").\n\n");
}

//worker functions
function format_dates (&$res, $format='mdy') {
	if (!is_array($res)) return $res;

	foreach ($res as $key => $val) {
		if (is_array($val)) {
			if (!is_numeric($key) && ($key=='expires' || $key=='created' || $key=='changed')) {
				$d = get_date($val[0],$format);
				if ($d) $res[$key] = $d;
			} else {
				$res[$key] = format_dates($val,$format);
			}
		} else {
			if (!is_numeric($key) && ($key=='expires' || $key=='created' || $key=='changed')) {
				$d = get_date($val,$format);
				if ($d) $res[$key] = $d;
			}
		}
	}

	return $res;
}

function get_date($date, $format) {
	if(strtotime($date) > 0) {
		return date('Y-m-d', strtotime($date));
	}

	$months = array( 'jan'=>1,  'ene'=>1,  'feb'=>2,  'mar'=>3, 'apr'=>4, 'abr'=>4,
	                 'may'=>5,  'jun'=>6,  'jul'=>7,  'aug'=>8, 'ago'=>8, 'sep'=>9,
	                 'oct'=>10, 'nov'=>11, 'dec'=>12, 'dic'=>12 );

	$parts = explode(' ',$date);

	if (strpos($parts[0],'@') !== false) {
		unset($parts[0]);
		$date = implode(' ',$parts);
	}

	$date = str_replace(',',' ',trim($date));
	$date = str_replace('.',' ',$date);
	$date = str_replace('-',' ',$date);
	$date = str_replace('/',' ',$date);
	$date = str_replace("\t",' ',$date);

	$parts = explode(' ',$date);
	$res = false;

	if ((strlen($parts[0]) == 8 || count($parts) == 1) && is_numeric($parts[0])) {
		$val = $parts[0];
		for ($p=$i=0; $i<3; $i++) {
			if ($format[$i] != 'Y') {
				$res[$format[$i]] = substr($val,$p,2);
				$p += 2;
			} else {
				$res['y'] = substr($val,$p,4);
				$p += 4;
			}
		}
	} else {
		$format = strtolower($format);

		for ($p=$i=0; $p<count($parts) && $i<strlen($format); $p++) {
			if (trim($parts[$p]) == '')
				continue;

			if ($format[$i] != '-')	{
				$res[$format[$i]] = $parts[$p];
			}

			$i++;
		}
	}

	if (!$res) return $date;

	$ok = false;

	while (!$ok) {
		reset($res);
		$ok = true;

		while (list($key, $val) = each($res)) {
			if ($val == '' || $key == '') continue;

			if (!is_numeric($val) && isset($months[substr(strtolower($val),0,3)])) {
				$res[$key] = $res['m'];
				$res['m'] = $months[substr(strtolower($val),0,3)];
				$ok = false;
				break;
			}

			if ($key != 'y' && $key != 'Y' && $val > 1900) {
				$res[$key] = $res['y'];
				$res['y'] = $val;
				$ok = false;
				break;
			}
		}
	}

	if ($res['m'] > 12) {
		$v = $res['m'];
		$res['m'] = $res['d'];
		$res['d'] = $v;
	}

	if ($res['y'] < 70) {
		$res['y'] += 2000;
	} else {
		if ($res['y'] <= 99)
			$res['y'] += 1900;
	}

	return sprintf("%.4d-%02d-%02d",$res['y'],$res['m'],$res['d']);
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

	This plugin will use the whois service to get the expiration date for the domain name.
	Example:
	     $./".PROGRAM." -d www.nagios.com -w 30 -c 10 \n\n"
    );
}
?>