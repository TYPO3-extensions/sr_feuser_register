<?php
	/***************************************************************
	*  Copyright notice
	*
	*  (c) 2004 Stanislas Rolland (stanislas.rolland@fructifor.com)
	*  All rights reserved
	*
	*  This script is part of the Typo3 project. The Typo3 project is
	*  free software; you can redistribute it and/or modify
	*  it under the terms of the GNU General Public License as published by
	*  the Free Software Foundation; either version 2 of the License, or
	*  (at your option) any later version.
	*
	*  The GNU General Public License can be found at
	*  http://www.gnu.org/copyleft/gpl.html.
	*
	*  This script is distributed in the hope that it will be useful,
	*  but WITHOUT ANY WARRANTY; without even the implied warranty of
	*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	*  GNU General Public License for more details.
	*
	*  This copyright notice MUST APPEAR in all copies of the script!
	***************************************************************/
	 
	/**
	* This class contains a function to validate url's devloped by:
	*
	* @copyright 2004   Esben Maaløe esm-at-baseclassmodulweb.dk
	* @author   Esben Maaløe esm-at-baseclassmodulweb.dk
	* @license You are free to copy/modify this function to your hearts content
	*          However I ask that you return any improvements you make to me,
	*          and that you credit me in your sourcecode if you use it
	* @version 0.24
	*/
	 
	class tx_srfeuserregister_pi1_urlvalidator {
		 
		function _ValURL($value, $options = array()) {
			$value = trim($value);
			 
			if (!$value)
				return array('Result' => array(EW_ERR_URL_EMPTY_STRING), 'Value' => '');
			 
			/* Set up default options */
			$options = array_merge(array(/**/
			'AllowedProtocols' => array(), /* array('http', 'https', etc...) always lcase! */
			'AllowBracks' => false, /* Allow square brackets in the query string ? */
			'Protocols' => array('http', 'https', 'ftp', 'mailto', 'file', 'news', 'gopher', 'telnet', 'nntp'), /**/
			'AssumeProtocol' => false, /**/
			), $options);
			 
			/* Setup default values for $options['Require]*/
			@ $options['Require'] = array_merge(array(/**/
			'Protocol' => false, /**/
			'User' => false, /**/
			'Password' => false, /**/
			'Server' => false, /**/
			'TLD' => false, /**/
			'Port' => false, /**/
			'Resource' => false, /**/
			'QueryString' => false, /**/
			'Anchor' => false, /**/
			), $options['Require']);
			 
			/* Setup default values for $options['Forbid]*/
			@ $options['Forbid'] = array_merge(array(/**/
			'Protocol' => false, /**/
			'User' => false, /**/
			'Password' => false, /**/
			'Server' => false, /**/
			'TLD' => false, /**/
			'Port' => false, /**/
			'Resource' => false, /**/
			'QueryString' => false, /**/
			'Anchor' => false, /**/
			), $options['Forbid']);
			 
			/* Create a container for the URL parts*/
			$url = array(/**/
			'Protocol' => '', /**/
			'User' => '', /**/
			'Password' => '', /**/
			'Server' => '', /**/
			'Port' => '', /**/
			'Resource' => '', /**/
			'TLD' => '', /**/
			'QueryString' => '', /**/
			'Anchor' => '');
			 
			/* Setup errorcodes for invalid elements */
			$errCodeInvalid = array(/**/
			'Protocol' => EW_ERR_URL_INVALID_PROTOCOL, /**/
			'User' => EW_ERR_URL_INVALID_USER, /**/
			'Password' => EW_ERR_URL_INVALID_PASSWORD, /**/
			'Server' => EW_ERR_URL_INVALID_SERVER, /**/
			'TLD' => EW_ERR_URL_INVALID_TLD, /**/
			'Port' => EW_ERR_URL_INVALID_PORT, /**/
			'Resource' => EW_ERR_URL_INVALID_RESOURCE, /**/
			'QueryString' => EW_ERR_URL_INVALID_QUERYSTRING, /**/
			'Anchor' => EW_ERR_URL_INVALID_ANCHOR);
			 
			/* Setup errorcodes for missing elements */
			$errCodeMissing = array(/**/
			'Protocol' => EW_ERR_URL_MISSING_PROTOCOL, /**/
			'User' => EW_ERR_URL_MISSING_USER, /**/
			'Password' => EW_ERR_URL_MISSING_PASSWORD, /**/
			'Server' => EW_ERR_URL_MISSING_SERVER, /**/
			'TLD' => EW_ERR_URL_MISSING_TLD, /**/
			'Port' => EW_ERR_URL_MISSING_PORT, /**/
			'Resource' => EW_ERR_URL_MISSING_RESOURCE, /**/
			'QueryString' => EW_ERR_URL_MISSING_QUERYSTRING, /**/
			'Anchor' => EW_ERR_URL_MISSING_ANCHOR);
			 
			/* set up some needed vars */
			extract($options);
			$errArr = array();
			$tmpValue = $value;
			$lcValue = strtolower($value);
			 
			/**
			* Split the url into it's subparts
			*/
			 
			foreach ($Protocols as $key => $protocol) {
				if (strpos($lcValue, "$protocol:") === 0) {
					$tmp = explode(':', $tmpValue, 2);
					$url['Protocol'] = $tmp[0];
					$tmpValue = $tmp[1];
					 
					if ($url['Protocol'] == 'mailto' || $url['Protocol'] == 'news') {
						 
						/* Check for % that is NOT an escape sequence */
						if (preg_match('/%[^a-f0-9]/i', $tmpValue) || preg_match("/^[^a-z0-9;&=+$,_.!*'()%~-]/i", $tmpValue)) {
							$errArr[EW_ERR_URL_INVALID_PROTOCOL] = EW_ERR_URL_INVALID_PROTOCOL;
						}
					} else {
						if (!(strpos($tmpValue, '//') === 0)) {
							$errArr[EW_ERR_URL_INVALID_PROTOCOL] = EW_ERR_URL_INVALID_PROTOCOL;
						} else {
							$tmpValue = substr($tmpValue, 2);
						}
					}
				}
			}
			 
			if (!$url['Protocol']) {
				if (strpos(strtolower($tmpValue), ('mailto:')) === 0 || strpos(strtolower($tmpValue), ('news:')) === 0)
					$tmp = ':';
				else
					$tmp = '://';
				 
				$tmp = explode($tmp, $tmpValue, 2);
				if (count($tmp) == 2) {
					$url['Protocol'] = strtolower($tmp[0]);
					$tmpValue = $tmp[1];
				}
			}
			 
			$tmp = explode('?', $tmpValue);
			 
			if (count($tmp) > 1) {
				$tmpValue = $tmp[0];
				$url['QueryString'] = $tmp[1];
				 
				$tmp = explode('#', $url['QueryString']);
				if (count($tmp) > 1) {
					$url['QueryString'] = $tmp[0];
					$url['Anchor'] = $tmp[1];
				}
			} else {
				$tmp = explode('#', $tmpValue);
				if (count($tmp) > 1) {
					$tmpValue = $tmp[0];
					$url['Anchor'] = $tmp[1];
				}
			}
			 
			$tmp = explode('/', $tmpValue, 2);
			if (count($tmp) > 1) {
				$url['Server'] = strtolower($tmp[0]);
				$url['Resource'] = $tmp[1];
			} else {
				$url['Server'] = strtolower($tmpValue);
			}
			 
			/* User / password */
			$tmp = explode('@', $url['Server']);
			if (count($tmp) > 1) {
				$url['User'] = $tmp[0];
				$url['Server'] = $tmp[1];
				 
				if ($url['User']) {
					$tmp = explode(':', $url['User']);
					if (count($tmp) > 1) {
						$url['User'] = $tmp[0];
						$url['Password'] = $tmp[1];
					}
				}
			}
			 
			$tmp = explode(':', $url['Server'], 2);
			if (count($tmp) > 1) {
				if ($tmp[0]) {
					$url['Server'] = $tmp[0];
					$url['Port'] = $tmp[1];
					 
				}
			}
			 
			if (!$url['Protocol'] && !$url['Password'] && in_array(strtolower($url['User']), array('mail', 'news'))) {
				$url['Protocol'] = strtolower($url['User']);
				$url['User'] = '';
				 
			}
			 
			if ($url['Protocol'] == 'mailto' && $url['Server'] && !$url['User']) {
				$url['User'] = $url['Server'];
				$url['Server'] = '';
			}
			 
			/**
			* Validate the different subparts
			*/
			 
			/* Check the protocol */
			if ($url['Protocol']) {
				$tmp = preg_replace("/[^a-z0-9+-.]/", '', $url['Protocol']);
				 
				if ($tmp != $url['Protocol']) {
					$errArr[EW_ERR_URL_INVALID_PROTOCOL] = EW_ERR_URL_INVALID_PROTOCOL;
				}
				 
				if (count($options['AllowedProtocols']))
					if (!in_array($url['Protocol'], $options['AllowedProtocols']))
					$errArr[EW_ERR_URL_INVALID_PROTOCOL] = EW_ERR_URL_INVALID_PROTOCOL;
				 
			}
			 
			/* check userinfo */
			if ($url['User']) {
				/* Check for % that is NOT an escape sequence */
				if (preg_match('/%[^a-f0-9]/i', $url['User']) || preg_match("/[^a-z0-9;&=+$,_.!~*'()%-]/i", $url['User'])) {
					$errArr[EW_ERR_URL_INVALID_USER] = EW_ERR_URL_INVALID_USER;
					$url['User'] = urlencode(urldecode($url['User']));
				}
			}
			if ($url['Password']) {
				/* Check for % that is NOT an escape sequence */
				if (preg_match('/%[^a-f0-9]/i', $url['Password']) || preg_match("/[^a-z0-9;&=+$,_.!~*'()%-]/i", $url['Password'])) {
					$errArr[EW_ERR_URL_INVALID_PASSWORD] = EW_ERR_URL_INVALID_PASSWORD;
				}
				$url['Password'] = urlencode(urldecode($url['Password']));
			}
			 
			//      userinfo      = *( unreserved | escaped |
			//                         ";" | ":" | "&" | "=" | "+" | "$" | "," )
			//      unreserved    = alphanum | mark
			//      mark          = "-" | "_" | "." | "!" | "~" | "*" | "'" |
			//                      "(" | ")"
			 
			//      escaped       = "%" hex hex
			/* Check if the server part is an ip */
			if ($url['Server']) {
				if (!preg_match('/[^.0-9]/', $url['Server'])) {
					$ServerIsIP = true;
					 
					$ipErr = false;
					 
					$ipPart = explode('.', $url['Server']);
					 
					if ($ipPart[0] > 224 || $ipPart[0] == 0) {
						$errArr[EW_ERR_URL_INVALID_SERVER] = EW_ERR_URL_INVALID_SERVER;
					} else {
						for ($i = 1; $i < 4; $i ++) {
							$ipPart[$i] = (integer) $ipPart[$i];
							if ($ipPart[$i] > 255)
								$errArr[EW_ERR_URL_INVALID_SERVER] = EW_ERR_URL_INVALID_SERVER;
						}
					}
					 
					/**
					* @todo Implement checking for reserved class D and E, and
					* other reserved addresses such as 0.0.0.0 or 255.255.255.255
					* and ip-addresses where either the host or the network part
					* is all binary 0s or all binary 1s
					* check:
					* http://www.cisco.com/univercd/cc/td/doc/product/atm/l2020/2020r21x/planning/appndxa.htm#xtocid87496
					*/
					 
					$url['Server'] = join('.', $ipPart);
				}
				/* url is not an ip */
				else
					{
					$ServerIsIP = false;
					 
					$serverParts = explode('.', $url['Server']);
					 
					/* check serverparts */
					for ($i = 0; $i < count($serverParts); $i ++) {
						$tmp = preg_replace('/[^a-z0-9-]/', '', $serverParts[$i]);
						 
						/* Check if it is a top-level server */
						if ($i && $i == count($serverParts) - 1)
							$tmp = preg_replace('/^[^a-z]/', '', $tmp);
						else
							$tmp = preg_replace('/^[^a-z0-9]/', '', $serverParts[$i]);
						 
						$tmp = preg_replace('/[^a-z0-9]$/', '', $tmp);
						 
						if ($tmp != $serverParts[$i]) {
							if ($tmp != '')
								$serverParts[$i] = $tmp;
							else
								unset($serverParts[$i]);
							 
							$errArr[EW_ERR_URL_INVALID_SERVER] = EW_ERR_URL_INVALID_SERVER;
							 
						}
					}
					 
					if (count($serverParts) < 2) {
						if ($Require['TLD']) {
							$errArr[EW_ERR_URL_MISSING_TLD] = EW_ERR_URL_MISSING_TLD;
						}
					} else {
						 
						$url['TLD'] = $serverParts[count($serverParts) - 1];
					}
					 
					$url['Server'] = join('.', $serverParts);
				}
			}
			 
			/* Check the Port */
			if ($url['Port']) {
				$tmp = (integer) $url['Port'];
				if ($url['Port'] != (string) $tmp) {
					$errArr[EW_ERR_URL_INVALID_PORT] = EW_ERR_URL_INVALID_PORT;
					 
					$url['Port'] = '';
				} else {
					$url['Port'] = $tmp;
					if ($url['Port'] > 65535)
						$errArr[EW_ERR_URL_INVALID_PORT] = EW_ERR_URL_INVALID_PORT;
				}
				 
			}
			 
			/* Check the resource */
			//path          = [ abs_path | opaque_part ]
			//path_segments = segment *( "/" segment )
			//segment       = *pchar *( ";" param )
			//param         = *pchar
			//pchar         = unreserved | escaped |
			//                ":" | "@" | "&" | "=" | "+" | "$" | ","
			 
			if ($url['Resource']) {
				$resourceParts = explode('/', $url['Resource']);
				 
				if ($resourceParts[count($resourceParts) - 1] == '')
					array_pop($resourceParts);
				 
				if ($resourceParts[0] == '')
					unset($resourceParts[0]);
				 
				foreach ($resourceParts as $key => $part) {
					if ($part == '') {
						$errArr[EW_ERR_URL_INVALID_RESOURCE] = EW_ERR_URL_INVALID_RESOURCE;
						unset($resourceParts[$key]);
					}
					 
					/* Check for % that is NOT an escape sequence || invalid chars*/
					elseif (preg_match('/%[^a-f0-9]/i', $part) || preg_match("/[^@a-z0-9_.!~*'()$+&,%:=;?-]/i", $part)) {
						$errArr[EW_ERR_URL_INVALID_RESOURCE] = EW_ERR_URL_INVALID_RESOURCE;
						$resourceParts[$key] = urlencode(urldecode($part));
					}
					 
					/* check for invalid chars */
					 
				}
				$url['Resource'] = join('/', $resourceParts);
			}
			 
			if ($url['QueryString']) {
				 
				/* Check for % NOT part of an escape sequence || invalid chars */
				$tmp = $options['AllowBracks'] ? /**/
				"^a-z0-9_.!~*'()%;\/?:@&=+$,\[\]-" : /**/
				"^a-z0-9_.!~*'()%;\/?:@&=+$,-"; /**/
				 
				if (preg_match('/%[^a-f0-9]/i', $url['QueryString']) || preg_match("/[$tmp]+/i", $url['QueryString'])) {
					$errArr[EW_ERR_URL_INVALID_QUERYSTRING] = EW_ERR_URL_INVALID_QUERYSTRING;
					$url['QueryString'] = $url['QueryString'];
				}
				 
			}
			if ($url['Anchor']) {
				if (preg_match('/%[^a-f0-9][a-f0-9]?/i', $url['Anchor']) || //
				preg_match("/[^a-z0-9-_.!~*'()%;\/?:@&=+$,]/i", $url['Anchor'])) {
					$errArr[EW_ERR_URL_INVALID_ANCHOR] = EW_ERR_URL_INVALID_ANCHOR;
					$url['Anchor'] = $url['Anchor'];
				}
				 
			}
			foreach ($url as $partName => $notused) {
				if ($partName == 'TLD' && $ServerIsIP)
					continue;
				 
				if ($Require[$partName] && !$url[$partName])
					$errArr[$errCodeMissing[$partName]] = $errCodeMissing[$partName];
				 
				if ($Forbid[$partName] && $url[$partName])
					$errArr[$errCodeMissing[$partName]] = $errCodeInvalid[$partName];
			}
			 
			/* Construct an estimate of what the value should've been */
			if ($options['AssumeProtocol'] && !$url['Protocol'] && ($url['Server'] || (!$url['Server'] && !$url['Resource'])))
				$url['Protocol'] = $options['AssumeProtocol'];
			 
			$value = $url['Protocol'];
			 
			if ($url['Protocol']) {
				if ($url['Protocol'] == 'mailto' | $url['Protocol'] == 'mailto')
					$value .= ':';
				else
					$value .= '://';
			}
			 
			if ($url['User']) {
				if ($url['Password'])
					$value .= "{$url['User']}:{$url['Password']}";
				else
					$value .= "{$url['User']}";
				 
				if ($url['Server'])
					$value .= '@';
			}
			 
			$value .= $url['Server'];
			 
			if ($url['Port'])
				$value .= ":{$url['Port']}";
			 
			if ($url['Server'] && $url['Resource'])
				$value .= "/";
			 
			$value .= $url['Resource'];
			 
			if ($url['QueryString'])
				$value .= "?{$url['QueryString']}";
			 
			if ($url['Anchor'])
				$value .= "#{$url['Anchor']}";
			 
			$r = array('Result' => count($errArr) ? $errArr : EW_OK, 'Value' => $value, 'URLParts' => $url);
			 
			return $r;
			 
		}
		 
	} //end of class
	 
	if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1_urlvalidator.php"]) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1_urlvalidator.php"]);
	}
	 
?>
