#!/usr/bin/php
<?php
/* check_openvpn.php - Nagios plugin to check status of remote OpenVPN
 *                     server over its management port.
 *
 * Copyright (c) February  3, 2006
 * by Nic Bernstein <nic@onlight.com>
 * for Onlight, llc.
 * 2266 North Prospect Ave.
 * Suite 610
 * Milwaukee, WI  53202
 *
 * Portions derived from "get_snmp.php" by:
 *    Sebastián Gómez (tiochan@gmail.com)
 *    UPCnet - Politechnical University of Catalonya - Spain
 *
 *
 * All rights reserved, except as provided by the following license
 * information...
 *
 * License Information:
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 */

define('THIS_VERSION','1.0');
error_reporting(E_ALL);

/* my_start() - Open debugging file if needed
 *
 * Arguments:
 *  None
 */
function my_start() {
    global $debug;
    global $fd;
    global $_REQUEST;
    global $argc;
    global $argv;

    if (isset($_REQUEST['-D'])) {
        $debug = true;
        $tmp = date('Y/m/d H:i:s -');
        
        for ($i = 0; $i < $argc; $i++) {
            $tmp .= ' ' . $argv[$i];
        }
        if (!$fd=fopen('/tmp/' . $argv[0] . '.txt','a')) {
            fwrite(STDOUT,
                   "Error opening debug file /tmp/$argv[0]get_snmp.txt");
            exit(3);
        }
        fwrite($fd,"\n$tmp\n");
    }
}

/* debug_msg() - write a message to debugging file
 *
 * Arguments:
 *   $msg  - STRING - message to write to debugging file
 */
function debug_msg($msg) {
    global $debug;
    global $fd;
    
    if($debug) {
        fwrite($fd, " - $msg");
    }
}

/* my_echo - write message to standard out, and to debugging file if
 *           debugging is enabled
 *
 * Arguments:
 *   $msg - STRING - message to write
 */
function my_echo($msg) {
    fwrite(STDOUT, $msg);
    debug_msg($msg);
}

/* my_exit() - Exit program with exit status and optional message
 *
 * Arguments:
 *   $exit_status - INTEGER - Exit status
 *   $msg - STRING - Message to echo on exit (default empty)
 */
function my_exit($exit_status, $msg='') {

    global $debug;
    global $fd;
    
    if($debug) {
        fwrite($fd, "$msg\n - exit with status code $exit_status\n");
        fclose($fd);
    }
    if($msg != '') {
        my_echo($msg . "\n");
    }    
    exit($exit_status);
}

/* my_unknown() - Print message and exit with status 3 (UNKNOWN)
 *
 * Arguments:
 *   $msg - STRING - Message to print
 */
function my_unknown($msg='') {
    my_exit(3, "OpenVPN UNKNOWN - $msg");
}

/* my_hdrprob() - Report a problem parsing header and exit
 *
 * Arguments:
 *   None
 */
function my_hdrprob() {
    my_unknown('Problem parsing header line');
}

/* show_usage() - Print standard usage instructions and exit
 *
 * Arguments:
 *    None
 */
function show_usage() {
    global $argc;
    global $argv;
    
?>
  Usage: <?php echo $argv[0]; ?> -H=<host> -p=<port> <action> [ -P=<password> ] <options>

  Required:
    -H=[STRING|IP]
        Hostname or IP address
    -p, --port=INTEGER
        TCP port of management interface (no default, required)
  Optional:
    -P, --password=STRING
        Password for management interface (no default, not required)
  Actions:
    -h, --help,
        show this help
    -v, --version,
        show version
    -C, --cn=STRING
        Common Name (in certificate) to seek
    -R, --route=IP NETWORK
        Network to seek route for
    -A, --addr=IP Address
        IP Address of client system to seek

    Options are parsed in this order. More than one option will execute only
    one action (first found).
  
  Options:
    -d, --perfdata
        Output performance data (Can only be used with -C or -A)
    -w=<warning_value>,
        warning limit (Cannot be used with actions)
    -c=<critical_value>,
        critical limit (Cannot be used with actions)
    --neg   negate the return code
    -D      Dump debug output into /tmp/<?php echo $argv[0];?>.txt file
      
  Examples:
    To check for general operation:
      <?php echo $argv[0];?> -H=ovpn.example.com -p=1010
    will return:
      OpenVPN OK - 5 clients connected, 10 routes

    As above with thresholds:
      <?php echo $argv[0];?> -H=ovpn.example.com -p=1010 -w=5 -c=3
    Will return:
      OpenVPN WARNING (5) - 5 clients connected, 13 routes
      
    To check for connection of particular client by Common Name:
      <?php echo $argv[0];?> -H=ovpn.example.com -p=1010 -C=Bob_Smith
    will return:
      OpenVPN OK - Client Bob_Smith connected since Thu Feb  2 10:03:34 2006
      
    As above, but with performance data:
      <?php echo $argv[0];?> -H=ovpn.example.com -p=1010 -C=Bob_Smith -d
    will return:
      OpenVPN OK - Client Bob_Smith connected since Thu Feb  2 10:03:34 2006 | bytessent=39853986,bytesreceived=5077417,connected_since=1138896214

    To check for connection of particular remote IP address:
      <?php echo $argv[0];?> -H=ovpn.example.com -p=1010 -A=192.168.1.12
    will return:
      OpenVPN OK - Real Address 192.168.1.12 connected since Thu Feb  2 10:03:34 2006

    To check for routing of particular network (or address):
      <?php echo $argv[0];?> -H=ovpn.example.com -p=1010 -R=192.168.122.0
    will return:
      OpenVPN OK - Network 192.168.122.0 routed via Bob_Smith

  Return status:
  
  - Without thresholds:
      exit code 0: OK (OpenVPN management port answered)
      exit code 1: WARNING (OpenVPN management port did not answer)
      
  - If a threshold (warning and/or critical value) is specified:
      exit code 0: OK
      exit code 1: WARNING
      exit code 2: CRITICAL
      exit code 3: UNKNOWN, OTHER
  
<?php
}

/* parse_arguments() - Parse argv[] to get arguments
 *
 * Arguments:
 *    None
 *
 * NOTE: This routing uses parse_string(), which means that all argument
 *       value pairs must be constructed with equals sing (=) to be
 *       properly parsed.  I.e.:
 *                             -C=common_name
 *       and not:
 *                             -C common_name
 *
 *       We consider this to be sub-optimal, but are too lazy to write
 *       a full blown getopts style parser right now.
 */
function parse_arguments() {
    global $argc;
    global $argv;
    global $_REQUEST;
    
    if ($argc > 0) {
        for ($i=1;$i < $argc;$i++) {
            parse_str($argv[$i],$tmp);
            $_REQUEST = array_merge($_REQUEST, $tmp);
        }
    }
}
/******** END OF FUNCTIONS ********/

parse_arguments();
my_start();

// Show version and exit
if (isset($_REQUEST['-v']) || isset($_REQUEST['--version'])) {
    my_exit(3,$argv[0] . ' (' . THIS_VERSION . ")\n");
}

// Show usage and exit
if ($argc < 1 || in_array($argv[1], array('--help', '-h', '-?'))) {
    show_usage();
    my_exit(3);
}

// Set hostname
if (isset($_REQUEST['-H'])) {
    $host = $_REQUEST['-H'];

    // Check if numerical address was entered
    $octets = explode('.', $host);

    // Add [] to hostname if so
    if (is_int($octets[0])) {
        $host = "[$host]";
    }
} else {
    show_usage();
    my_unknown('Must provide hostname or address');
}

// Set password
if (isset($_REQUEST['-P'])) {
    $passwd = $_REQUEST['-P'];
} else if (isset($_REQUEST['--password'])) {
    $passwd = $_REQUEST['--password'];
}

// Set warning threshold
if (isset($_REQUEST['-w'])) {
    $warn_val = $_REQUEST['-w'];
} else {
    unset($warn_val);
}

// Set critical threshold
if (isset($_REQUEST['-c'])) {
    $crit_val = $_REQUEST['-c'];
} else {
    unset($crit_val);
}

// Set Common Name to seek for
if (isset($_REQUEST['-C'])) {
    $cseek = $_REQUEST['-C'];
} else if (isset($_REQUEST['--cn'])) {
    $cseek = $_REQUEST['--cn'];
}

// Set network to seek routes of
if (isset($_REQUEST['-R'])) {
    $rseek = $_REQUEST['-R'];
} else if (isset($_REQUEST['--route'])) {
    $rseek = $_REQUEST['--route'];
}

// Set "Real Address" to seek
if (isset($_REQUEST['-A'])) {
    $aseek = $_REQUEST['-A'];
} else if (isset($_REQUEST['--addr'])) {
    $aseek = $_REQUEST['--addr'];
}

// If looking for particular connection, report performance data?
if (isset($aseek) || isset($cseek)) {
    $perfdata = (isset($_REQUEST['-d']) || isset($_REQUEST['--perfdata']));
}

// Negate warning/critical
$negate = isset($_REQUEST['--neg']);

// Initialize parameters
$connected = FALSE;

// Set port
if (isset($_REQUEST['-p']) || isset($_REQUEST['--port'])) {
    if (isset($_REQUEST['-p'])) {
        $port=$_REQUEST['-p'];
    } else {
        $port=$_REQUEST['--port'];
    }
    // Open socket to management port
    $fp = stream_socket_client("tcp://$host:$port", $errno, $errstr, 30);
    if (!$fp) {
        echo "$errstr ($errno)<br />\n";
        my_unknown("Could not open connection to port $port on host $host");
    }
    /* Parse the initial blob of stuff from the connection
     * This will either begin with ">INFO..." if no password is required
     * or "ENTER PASSWORD" if one is.
     */
    while (!$connected) {
        $line = fread($fp, 1024);
        if ($debug) my_echo($line);
        
        /* Parse out the token and process the line
         * A colon is used to delimit the initial tokens from the balance
         * of the string
         */
        $point = strpos($line, ':');
        $token = substr($line, 0, $point);
        $balance = substr($line, ($point + 1));

        // Now act based upon the token
        switch ($token) {
        case 'ENTER PASSWORD':
            // Send the password
            if (isset($passwd)) {
                fwrite($fp, "$passwd\r\n");
            } else {
                my_unknown('Password required but not set');
            }
            break;
        case 'SUCCESS':
            // No action needed
            if ($debug) my_echo("$balance\n");
            break;
        case '>INFO':
            // We are fine, dump out of the loop!
            $connected = TRUE;
            if ($debug) my_echo("$balance\n");
            break;
        case 'ERROR':
            // Don't know what this could be
            $error = "$balance\n";
            if ($debug) my_echo("$balance\n");
            break;
        } // switch($token)
    } // while (!$connected)
} else {
    // No port was provided
    unset($port);
    my_unknown('Must provide port');
}

if (isset($connected)) {
    // Get status report in format 2
    fwrite($fp, "status 2\r\n");

    // Parse responses
    $end = FALSE;
    while (!$end) {
        $line = fgets($fp, 1024);
        if ($debug) my_echo($line);
        /* Parse out the token and process the line
         * In a status report in format 2, a comma is the delimiter
         */
        $point = strpos($line, ',');
        if ($point === FALSE) {
            // We have reached the end of the report
            $end = TRUE;
            break;
        }
        $token = substr($line, 0, $point);
        $balance = substr($line, ($point + 1));

        // Act based upon the token
        switch ($token) {
        case 'TITLE':
            // No action needed
            if ($debug) my_echo("$balance\n");
            break;
        case 'TIME':
            $time = trim($balance);
            if ($debug) my_echo("$balance\n");
            break;
        case 'HEADER':
            // We need to save the header for later
            $header[] = trim($balance);
            if ($debug) my_echo("$balance\n");
            break;
        case 'CLIENT_LIST':
            // Build up an array of connected clients
            $client[] = trim($balance);
            if ($debug) my_echo("$balance\n");
            break;
        case 'ROUTING_TABLE':
            // Build an array of routes
            $route[] = trim($balance);
            if ($debug) my_echo("$balance\n");
            break;
        case 'GLOBAL_STATS':
            $global[] = trim($balance);
            if ($debug) my_echo("$balance\n");
            break;
        case 'ERROR':
            $error = trim($balance);
            if ($debug) my_echo("$balance\n");
            break;
        } // switch($token)
    } // while !$end
    $clients = count($client);
    $routes = count($route);

    // Are we looking for a particular client?
    if (isset($cseek)) {
        /* We need to parse the header for CLIENT_LIST to figure
         * which fields are which.  We need to bail if we cannot
         * find a field, because something very wrong has happened
         * (like a new version of OpenVPN has gotten rid of a field
         *  we depend on).
         */
        /* We go through this nonsense so that if a future version
         * merely changes the sequence of fields in the report, we
         * won't break.
         */
        foreach ($header as $hdr) {
            $harray = explode(',', $hdr);
            if ($harray[0] == 'CLIENT_LIST') {
                $cn = array_search('Common Name', $harray);
                if ($cn === FALSE) my_hdrprob();

                $ct = array_search('Connected Since', $harray);
                if ($ct === FALSE) my_hdrprob();

                // If we need performance data, find that too
                if ($perfdata) {
                    $br = array_search('Bytes Received', $harray);
                    if ($br === FALSE) my_hdrprob();
                    $bs = array_search('Bytes Sent', $harray);
                    if ($bs === FALSE) my_hdrprob();
                    $ctt = array_search('Connected Since (time_t)', $harray);
                    if ($ctt === FALSE) my_hdrprob();
                }
                break;
            }
        }
        if ($cn) {
            // Okay, we know where to look, now get the data
            unset($contime);
            foreach ($client as $clnt) {
                $carray = explode(',', $clnt);
                /* We offset by one because we tossed the first field
                 * as a token
                 */
                if ($carray[$cn - 1] == $cseek) {
                    $contime = $carray[$ct - 1];
                    if ($perfdata) {
                        unset($bytessent, $bytesrec);
                        $bytessent = $carray[$bs - 1];
                        $bytesrec = $carray[$br - 1];
                        $contimet = $carray[$ctt -1];
                    }
                }
            }
        }
        // And report it
        if (isset($contime)) {
            my_echo("OpenVPN OK - Client $cseek connected since $contime");
            if ($perfdata) {
                my_echo(" | bytessent=$bytessent,bytesreceived=$bytesrec,connected_since=$contimet");
            }
            my_echo("\n");
        } else {
            // If we didn't find the connection time, then something is bad
            my_echo("OpenVPN CRITICAL - Client $cseek not connected\n");
        }
        // end if (isset($cseek))
    } else if (isset($rseek)) {
        /* Same routine as above, but this time we are parsing the
         * ROUTING_TABLE to find a particular route.
         */
        foreach ($header as $hdr) {
            $harray = explode(',', $hdr);
            if ($harray[0] == 'ROUTING_TABLE') {
                $va = array_search('Virtual Address', $harray);
                if ($va === FALSE) my_hdrprob();
                $cn = array_search('Common Name', $harray);
                if ($cn === FALSE) my_hdrprob();
                break;
            }
        }
        if ($va) {
            unset($comname);
            foreach ($route as $rte) {
                $rarray = explode(',', $rte);
                if (strstr($rarray[$va - 1], $rseek)) {
                    $comname = $rarray[$cn - 1];
                }
            }
        }
        if (isset($comname)) {
            my_echo("OpenVPN OK - Network $rseek routed via $comname\n");
        } else {
            my_echo("OpenVPN CRITICAL - Network $rseek not routed\n");
        }
        // end if (isset($rseek)) 
    } else if (isset($aseek)) {
        // Lastly, back to the CLIENT_LIST for an address instead of CN
        foreach ($header as $hdr) {
            $harray = explode(',', $hdr);
            if ($harray[0] == 'CLIENT_LIST') {
                $ra = array_search('Real Address', $harray);
                if ($ra === FALSE) my_hdrprob();
                $ct = array_search('Connected Since', $harray);
                if ($ct === FALSE) my_hdrprob();
                if ($perfdata) {
                    $br = array_search('Bytes Received', $harray);
                    if ($br === FALSE) my_hdrprob();
                    $bs = array_search('Bytes Sent', $harray);
                    if ($bs === FALSE) my_hdrprob();
                    $ctt = array_search('Connected Since (time_t)', $harray);
                    if ($ctt === FALSE) my_hdrprob();
                }
                break;
            }
        }
        if ($ra) {
            unset($contime);
            foreach ($client as $clnt) {
                $carray = explode(',', $clnt);
                if (strstr($carray[$ra - 1], $aseek)) {
                    $contime = $carray[$ct - 1];
                    if ($perfdata) {
                        unset($bytessent, $bytesrec);
                        $bytessent = $carray[$bs - 1];
                        $bytesrec = $carray[$br - 1];
                        $contimet = $carray[$ctt -1];
                    }
                }
            }
        }
        if (isset($contime)) {
            my_echo("OpenVPN OK - Address $aseek connected since $contime");
            if ($perfdata) {
                my_echo(" | bytessent=$bytessent,bytesreceived=$bytesrec,connected_since=$contimet");
            }
            my_echo("\n");
        } else {
            my_echo("OpenVPN CRITICAL - Real Address $aseek not connected\n");
        }
        // end if (isset($aseek)) 
    } else {
        // Not looking for anything special, just general state
        $result = "$clients clients connected, $routes routes";

        // Check our thresholds if needed
        if(isset($crit_val) && $crit_val >= $clients) {
            my_echo("OpenVPN CRITICAL ($crit_val) - $result\n");
            my_exit(2);
        } else if(isset($warn_val) && $warn_val >= $clients) {
            my_echo("OpenVPN WARNING ($warn_val) - $result\n");
            my_exit(1);
        } else {
            my_echo("OpenVPN OK - $result\n");
        }
    }

    // Close the connection
    fclose($fp);
    my_exit(0);
} // end if (isset($connected))

// We never connected, bail
show_usage();
my_unknown('exit without action');
?>
<?php //vim:set ts=4 sw=4 ai: ?>
