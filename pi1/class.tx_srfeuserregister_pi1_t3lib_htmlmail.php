<?php
	/***************************************************************
	*  Copyright notice
	*
	*  (c) 1999-2003 Kasper Skaarhoj (kasper@typo3.com)
	*  (c) 2003-2004 Stanislas Rolland (stanislas.rolland@fructifor.com)
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

	require_once (PATH_t3lib.'class.t3lib_htmlmail.php');

	/**
	 * t3lib_htmlmail system class extended by Stanislas Rolland so that
	 *         - quoted-printable messages can be correctly sent thus avoiding SPAM filtering
	 *         - the mails may encoded with charsets different from iso-8859-1
	 *
	 * translate_uri($uri) code from http://ca.php.net/rawurlencode
	 * replaces rawurlencode in substMediaNamesInHTML($absolute) and substHREFsInHTML()
	 *
	 * @author      Stanislas Rolland <stanislas.rolland@fructifor.com>
	 */
	class tx_srfeuserregister_pi1_t3lib_htmlmail extends t3lib_htmlmail {

		var $charset = 'iso-8859-1';
		var $alt_8bit = 0;
		var $lineBreak;
		var $innerMessageid;
		var $plain_text_header = "Content-Type: text/plain; charset=iso-8859-1\nContent-Transfer-Encoding: quoted-printable";
		var $html_text_header = "Content-Type: text/html; charset=iso-8859-1\nContent-Transfer-Encoding: quoted-printable";

		/**
 * Initialize class
 *
 * @return	void
 */
		function start () {
				// Sets the message id
			$localhost = gethostbyaddr('127.0.0.1');
			if (!$localhost || $localhost == '127.0.0.1' || $localhost == 'localhost') $localhost = md5($TYPO3_CONF_VARS['SYS']['sitename']).'.TYPO3'; 
			$this->innerMessageid = md5(microtime()) . '@' . $localhost;
			$this->messageid = $this->innerMessageid;
			//$this->messageid = '<' . $this->innerMessageid . '>';

				// default line break (Unix)
			$this->lineBreak = chr(10);
				// line break for Windows
			if (TYPO3_OS == 'WIN') {
				$this->lineBreak = chr(13).chr(10);
			}

				// Quoted-printable headers
			$this->plain_text_header = 'Content-Type: text/plain; charset=' . $this->charset . $this->lineBreak . 'Content-Transfer-Encoding: quoted-printable';
			$this->html_text_header = 'Content-Type: text/html; charset=' . $this->charset . $this->lineBreak . 'Content-Transfer-Encoding: quoted-printable';
		}

/**
 * Set text headers to base64 encoding
 *
 * @return	void
 */
		function useBase64() {
			$this->plain_text_header = 'Content-Type: text/plain; charset=' . $this->charset . $this->lineBreak . 'Content-Transfer-Encoding: base64';
			$this->html_text_header = 'Content-Type: text/html; charset=' . $this->charset . $this->lineBreak . 'Content-Transfer-Encoding: base64';
			$this->alt_base64 = 1;
		}
/**
 * Set text headers to 8bit encoding
 *
 * @return	void
 */
		function use8Bit()    {
			$this->plain_text_header = 'Content-Type: text/plain; charset=' . $this->charset . '; format=flowed' . $this->lineBreak . 'Content-Transfer-Encoding: 8bit';
			$this->html_text_header = 'Content-Type: text/html; charset=' . $this->charset . $this->lineBreak . 'Content-Transfer-Encoding: 8bit';
			$this->alt_8bit = 1;
		}

/**
 * Set message headers
 *
 * @return	void
 */
		function setHeaders () {
				// Clears the header-string and sets the headers based on object-vars.
			$this->headers = '';
				// Message_id
			$this->add_header("Message-ID: <" . $this->innerMessageid . ">");
				// Return path
			if ($this->returnPath) {
				$this->add_header('Return-Path: '.$this->returnPath);
			}
				// X-id
			if ($this->Xid) {
				$this->add_header('X-Typo3MID: '.$this->Xid);
			}

				// From
			if ($this->from_email) {
				if ($this->from_name) {
					$name = $this->convertName($this->from_name);
					$this->add_header("From: $name <$this->from_email>");
				} else {
					$this->add_header("From: $this->from_email");
				}
			}
				// Reply
			if ($this->replyto_email) {
				if ($this->replyto_name) {
					$name = $this->convertName($this->replyto_name);
					$this->add_header("Reply-To: $name <$this->replyto_email>");
				} else {
					$this->add_header("Reply-To: $this->replyto_email");
				}
			}
				// Organisation
			if ($this->organisation) {
				$name = $this->convertName($this->organisation);
				$this->add_header("Organisation: $name");
			}
				// mailer
			if ($this->mailer) {
				$this->add_header("X-Mailer: $this->mailer");
			}
				// priority
			if ($this->priority) {
				$this->add_header("X-Priority: $this->priority");
			}
			$this->add_header('Mime-Version: 1.0');
		}

/**
 * Encode header strings (especially message fromName, toName and subject) when required
 *
 * @param	string		$name: the string to be converted
 * @return	string		the converted string
 */
		function convertName($name) {
			if ($this->charset == 'iso-8859-1') {
				if (ereg('[^'.chr(32).'-'.chr(60).chr(62).'-'.chr(127)."]", $name)) {
					return '=?'.$this->charset.'?B?'.base64_encode($name).'?=';
				} else {
					return $name;
				}
			} elseif ( $this->charset == 'windows-1250' ) {
					return '=?'.$this->charset.'?B?'.base64_encode($name).'?=';
			} elseif ( $GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'] == 'mbstring' ) {
				$string = $name;
				$current_internal_encoding = mb_internal_encoding();
				mb_internal_encoding($this->charset);
				$string = mb_encode_mimeheader($string, $this->charset);
				mb_internal_encoding($current_internal_encoding);
				return $string;
			} else {
				return $name;
			}
		}

/**
 * Construct message HTML content
 *
 * @param	string		$boundary: boundary to be used
 * @return	void
 */
		function constructHTML ($boundary) {
			if (count($this->theParts['html']['media'])) {
				// If media, then we know, the multipart/related content-type has been set before this function call...
				$this->add_message('--'.$boundary);
				// HTML has media
				$newBoundary = $this->getBoundary();
				$this->add_message('Content-Type: multipart/alternative;');
				$this->add_message(' boundary="'.$newBoundary.'"');

// <Set encoding of media files>
				$this->add_message("Content-Transfer-Encoding: 7bit");
// </Set encoding of media files>

				$this->add_message('');
				$this->constructAlternative($newBoundary);     // Adding the plaintext/html mix
				$this->constructHTML_media($boundary);
				//$this->add_message('--'.$boundary."--\n");
			} else {
				$this->constructAlternative($boundary); // Adding the plaintext/html mix, and if no media, then use $boundary instead of $newBoundary
			}
		}

/*
 * @author    Kasper Skaarhoj <kasperYYYY@typo3.com>
 *
 * Enables "return path" feature. Function from class.t3lib_htmlmail.php in Typo 3.7.0 framework.
 */
	function sendTheMail () {
			// Sends the mail.
			// Requires the recipient, message and headers to be set.
		if (trim($this->recipient) && trim($this->message))    {    //  && trim($this->headers)
			$returnPath = (strlen($this->returnPath)>0)?'-f ' . $this->returnPath : '';
				//On windows the -f flag is not used (specific for sendmail and postfix), but instead the php.ini parameter sendmail_from is used.
			if($this->returnPath) {
				ini_set(sendmail_from, $this->returnPath);
			}
				//If safe mode is on, the fifth parameter to mail is not allowed, so the fix wont work on unix with safe_mode=On
			if(!ini_get('safe_mode')) {
				mail(   $this->recipient,
					$this->subject,
					$this->message,
					$this->headers,
					$returnPath);
			} else {
				mail(   $this->recipient,
					$this->subject,
					$this->message,
					$this->headers);
			}
				// Sending copy:
			if ($this->recipient_copy)    {
				if(!ini_get('safe_mode')) {
					mail(	$this->recipient_copy,
						$this->subject,
						$this->message,
						$this->headers,
						$returnPath);
				} else {
					mail(	$this->recipient_copy,
						$this->subject,
						$this->message,
						$this->headers    );
				}
			}
				// Auto response
			if ($this->auto_respond_msg)    {
				$theParts = explode("/",$this->auto_respond_msg,2);
				$theParts[1] = str_replace("/",chr(10),$theParts[1]);
				mail(	$this->from_email,
					$theParts[0],
					$theParts[1],
					"From: ".$this->recipient,
					$returnPath);
			}
			if($this->returnPath) {
				ini_restore(sendmail_from);
			}
			return true;
		} else {return false;}
	}

/**
 * Substitute media file names in HTML content
 *
 * @param	boolean		$absolute: if true, substituted url's will be absolute
 * @return	void
 */
		function substMediaNamesInHTML($absolute) {
			// This substitutes the media-references in $this->theParts['html']['content']
			// If $absolute is true, then the refs are substituted with http:// ref's indstead of Content-ID's (cid).
			if (is_array($this->theParts['html']['media'])) {
				reset ($this->theParts['html']['media']);
				while (list($key, $val) = each ($this->theParts['html']['media'])) {
					if ($val['use_jumpurl'] && $this->jumperURL_prefix) {

						$theSubstVal = $this->jumperURL_prefix.$this->ux_translate_uri($val['absRef']);
						//                          $theSubstVal = $this->jumperURL_prefix.rawurlencode($val['absRef']);
					} else {
						$theSubstVal = ($absolute) ? $val['absRef'] :
						"cid:part".$key.'.'.$this->messageid;
					}
					$this->theParts['html']['content'] = str_replace(
					$val['subst_str'],
						$val['quotes'].$theSubstVal.$val['quotes'],
						$this->theParts['html']['content']  );
				}
			}
			if (!$absolute) {
				$this->fixRollOvers();
			}
		}

/**
 * Substitute HREF url's in HTML content
 *
 * @return	void
 */
		function substHREFsInHTML() {
			// This substitutes the hrefs in $this->theParts['html']['content']
			if (is_array($this->theParts['html']['hrefs'])) {
				reset ($this->theParts['html']['hrefs']);
				while (list($key, $val) = each ($this->theParts['html']['hrefs'])) {
					if ($this->jumperURL_prefix && $val['tag'] != 'form') {
						// Form elements cannot use jumpurl!
						if ($this->jumperURL_useId) {
							$theSubstVal = $this->jumperURL_prefix.$key;
						} else {

							$theSubstVal = $this->jumperURL_prefix.$this->ux_translate_uri($val['absRef']);
							//                               $theSubstVal = $this->jumperURL_prefix.rawurlencode($val['absRef']);
						}
					} else {
						$theSubstVal = $val['absRef'];
					}
					$this->theParts['html']['content'] = str_replace(
					$val['subst_str'],
						$val['quotes'].$theSubstVal.$val['quotes'],
						$this->theParts['html']['content'] );
				}
			}
		}

		/**
 * Substitute HREF url's in plain content
 *
 * @param	string		$content: plain content of the message
 * @return	string		plain content with substituted url's
 */
		function substHTTPurlsInPlainText($content) {
			// This substitutes the http:// urls in plain text with links
			if ($this->jumperURL_prefix) {
				$textpieces = explode('http://', $content);
				$pieces = count($textpieces);
				$textstr = $textpieces[0];
				for($i = 1; $i < $pieces; $i++) {
					$len = strcspn($textpieces[$i], chr(32).chr(9).chr(13).chr(10));
					if (trim(substr($textstr, -1)) == "" && $len) {
						$lastChar = substr($textpieces[$i], $len-1, 1);
						if (!ereg("[A-Za-z0-9\/#]", $lastChar)) {
							$len--;
						}
						// Included "\/" 3/12

						$parts[0] = 'http://'.substr($textpieces[$i], 0, $len);
						$parts[1] = substr($textpieces[$i], $len);

						if ($this->jumperURL_useId) {
							$this->theParts['plain']['link_ids'][$i] = $parts[0];
							$parts[0] = $this->jumperURL_prefix.'-'.$i;
						} else {
							$parts[0] = $this->jumperURL_prefix.$this->ux_translate_uri($parts[0]);
							//                               $parts[0] = $this->jumperURL_prefix.rawurlencode($parts[0]);
						}
						$textstr .= $parts[0].$parts[1];
					} else {
						$textstr .= 'http://'.$textpieces[$i];
					}
				}
				$content = $textstr;
			}
			return $content;
		}

	/**
	 * Rawurlencode an url, but only between the slashes
	 *
	 * @param	string		$uri: the url to be encoded
	 * @return	string		teh encoded url
	 */
	function ux_translate_uri($uri) {
			//rawurlencode only in between "/", do not rawurlencode the slas,
			//because if you do rawurlencode() over the whole URI, path separator characters '/' are also encoded and request will not happen to be correct.
			//  '/' characters should not be encoded, only those parts in between.
		$url_parts = explode('/', $uri);
		for ($i = 0; $i < count($url_parts); $i++) {
			$url_parts[$i] = rawurlencode($url_parts[$i]);
		}
		return implode('/', $url_parts);
	}

	/**
	 * Quoted-printable encoding modified by Martin Kutschker <Martin.Kutschker@activesolution.at>
	 *
	 * @param       [type]          $string: ...
	 * @return      [type]          ...
	 */
	function quoted_printable($string)      {
			// This functions is buggy. It seems that in the part where the lines are breaked every 76th character, that it fails if the break happens right in a quoted_printable encode character!
		$newString = "";
			// unify internal line breaks
		$string = str_replace(chr(13).chr(10),chr(10),$string); // DOS -> Unix
		$string = str_replace(chr(13),chr(10),$string); // Mac -> Unix
		$theLines = explode(chr(10),$string);   // Break lines. Doesn't work with mac eol's which seems to be 13. But 13-10 or 10 will work
		while (list(,$val)=each($theLines))     {
			$val = ereg_replace(chr(13)."$","",$val);               // removes possible character 13 at the end of line
			$newVal = "";
			$theValLen = strlen($val);
			$len = 0;
			for ($index=0;$index<$theValLen;$index++)       {
				$char = substr($val,$index,1);
				$ordVal =Ord($char);
				if ($len>(76-4) || ($len>(66-4)&&$ordVal==32))  {
					$len=0;
					$newVal.="=".$this->lineBreak;
				}
				if (($ordVal>=33 && $ordVal<=60) || ($ordVal>=62 && $ordVal<=126) || $ordVal==9 || $ordVal==32) {
					$newVal.=$char;
					$len++;
				} else {
					$newVal.=sprintf("=%02X",$ordVal);
					$len+=3;
				}
			}
			$newVal = ereg_replace(chr(32)."$","=20",$newVal);              // replaces a possible SPACE-character at the end of a line
			$newVal = ereg_replace(chr(9)."$","=09",$newVal);               // replaces a possible TAB-character at the end of a line
			$newString.=$newVal.$this->lineBreak;
		}
		return $newString;
	}

	} //end of class

	if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1_t3lib_htmlmail.php"]) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1_t3lib_htmlmail.php"]);
	}

?>
