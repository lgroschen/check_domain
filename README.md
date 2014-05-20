Nagios Plugin: check_domain
============================

A Nagios plugin for checking a domain expiration date by registry name.  


> This plugin relies on [jwhois](https://github.com/jodrell/jwhois) to find the registration 
> dates so any TLD that is not supported by whois will not return correctly and will output
> something similar to the following:
> 	Error running whois:
> 
> Thanks to [glensc](https://github.com/glensc) for his work on [nagios-plugin-check_domain](https://github.com/glensc/nagios-plugin-check_domain) 
> which inspired this php version of his shell plugin.


Usage:

	$./check_domain.php -d nagios.org
	OK - Domain nagios.com will expire in 248 days (2014-10-04)


Full Usage:

	"check_domain.php - v".VERSION."
        Copyright (c) 2005 Tomàs Núñez Lirola <tnunez@criptos.com>, 
                      2009-2014 Elan Ruusamäe <glen@pld-linux.org>
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
