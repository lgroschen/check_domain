#!/usr/bin/php
<?php
// Check Domain PLUGIN
//
// Copyright (c) 2014 Luke Groschen, Nagios Enterprises, LLC.  All rights reserved.
//  
// $Id: $lgroschen@nagios.com

define("PROGRAM", 'check_domain.php');
define("VERSION", '1.1.0');
define("STATUS_OK", 0);
define("STATUS_WARNING",  1);
define("STATUS_CRITICAL", 2);
define("STATUS_UNKNOWN", 3);
define("DEBUG", true);


function parse_args() {
    $specs = array(array('short' => 'h',
                         'long' => 'help',
                         'required' => false),
                   array('short' => 'd',
                         'long' => 'domain', 
                         'required' => true),
                   array('short' => 'c', 
                         'long' => 'critical', 
                         'required' => false),
                   array('short' => 'w', 
                         'long' => 'warning', 
                         'required' => false),
                   array('short' => 's', 
                         'long' => 'whoisServer', 
                         'required' => false)
    );
    
    $options = parse_specs($specs);
    return $options;
}

function parse_specs($specs) {

    $shortopts = '';
    $longopts = array();
    $opts = array();

    // Create the array that will be passed to getopt
    // Accepts an array of arrays, where each contained array has three 
    // entries, the short option, the long option and required
    foreach($specs as $spec) {    
        if(!empty($spec['short'])) {
            $shortopts .= "{$spec['short']}:";
        }
        if(!empty($spec['long'])) {
            $longopts[] = "{$spec['long']}:";
        }
    }

    // Parse with the builtin getopt function
    $parsed = getopt($shortopts, $longopts);

    // Make sure the input variables are sane. Also check to make sure that 
    // all flags marked required are present.
    foreach($specs as $spec) {
        $l = $spec['long'];
        $s = $spec['short'];

        if(array_key_exists($l, $parsed) && array_key_exists($s, $parsed)) {
            plugin_error("Command line parsing error: Inconsistent use of flag: ".$spec['long']);
        }
        if(array_key_exists($l, $parsed)) {
            $opts[$l] = $parsed[$l];
        }
        elseif(array_key_exists($s, $parsed)) {
            $opts[$l] = $parsed[$s];
        }
        elseif($spec['required'] == true) {
            plugin_error("Command line parsing error: Required variable ".$spec['long']." not present.");
        }
    }
    return $opts;

}

function debug_logging($message) {
    if(DEBUG) {
        echo $message;
    }
}

function plugin_error($error_message) {
    print("***ERROR***:\n\n{$error_message}\n\n");
    fullusage();
    nagios_exit('', STATUS_UNKNOWN);
}

function nagios_exit($stdout='', $exitcode=0) {
    print($stdout);
    exit($exitcode);
}

function main() {
    $options = parse_args();
    
    if(array_key_exists('version', $options)) {
        print('Plugin version: '.VERSION);
        fullusage();
        nagios_exit('', STATUS_OK);
    }

    check_environment();
    check_domain($options);
}

function check_environment() {
    exec('which whois 2>&1', $execout, $return_var);
    $whois_path = $execout[0];

    if ($return_var != 0) {
        plugin_error("whois is not installed in your system.");
    }
}

function check_domain($options) {
    //get the expiration date string for our given domain
    $execout = "";
    $domain = $options['domain'];
    $server = (!empty($options['whoisServer'])) ? $options['whoisServer'] : null;

    if ($server !== null) {
    	$whois_server = "-h " . $server;
    } else {
    	$whois_server = null;
    }

    $cmd = 'whois '.$domain.' '. $whois_server .' | grep -i \'expir\|renew\|paid-till\'';
    exec($cmd, $execout, $exitcode);

    if($exitcode != 0) {
        nagios_exit('Error running whois: '.implode('\n', $execout), STATUS_UNKNOWN);
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

    $warning = (!empty($options['warning'])) ? $options['warning'] : null;
    $critical = (!empty($options['critical'])) ? $options['critical'] : null;

    //plugin output
    if ($critical !== null && $expire_days <= $critical) {
        nagios_exit("CRITICAL - Domain ".$domain." will expire in ".$expire_days." days (".$expire_date.").\n\n", STATUS_CRITICAL);
    } elseif ($warning !== null && $expire_days <= $warning) {
        nagios_exit("WARNING - Domain ".$domain." will expire in ".$expire_days." days (".$expire_date.").\n\n", STATUS_WARNING);
    } else {
        nagios_exit("OK - Domain ".$domain." will expire in ".$expire_days." days (".$expire_date.").\n\n", STATUS_OK);
    }
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
	"check_domain.php - v".VERSION."
        Copyright (c) 2014 Luke Groschen, Nagios Enterprises <lgroschen@nagios.com>, 
                      2009-2014 Elan Ruusamäe <glen@pld-linux.org>
	Under GPL v2 License

	This plugin checks the expiration date of a domain name.

	Usage: ".PROGRAM." -h | -d <domain> [-c <critical>] [-w <warning>] [-s <whoisServer>]
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
	-s
		 Specify a whois server (whois.internic.net by default)

	This plugin will use the whois service to get the expiration date for the domain name.
	Example:
	     $./".PROGRAM." -d nagios.com -w 30 -c 10 \n\n"
    );
}

main();
?>