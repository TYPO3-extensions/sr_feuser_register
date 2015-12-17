<?php
namespace SJBR\SrFeuserRegister\Mail;

/*
 *  Copyright notice
 *
 *  (c) 2007-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Email functions
 */
class Message
{
	/**
	 * Dispatches the email messsage
	 *
	 * @param string $recipient: email address
	 * @param string $admin: email address
	 * @param string $content: plain content for the recipient
	 * @param string $HTMLcontent: HTML content for the recipient
	 * @param string $adminContent: plain content for admin
	 * @param string $adminContentHTML: HTML content for admin
	 * @param string $fileAttachment: file name
	 * @param array $conf: the plugin configuration
	 * @return void
	 */
	static public function send($recipient, $admin, $content = '', $contentHTML = '', $adminContent = '', $adminContentHTML = '', $fileAttachment = '', $conf)
	{
		// Send mail to admin
		if ($admin && ($adminContent != '' || $adminContentHTML != '')) {
			if (isset($conf['email.']['replyTo'])) {
				if ($conf['email.']['replyTo'] == 'user') {
					$replyTo = $recipient;
				} else {
					$replyTo = $conf['email.']['replyTo'];
				}
			}
			self::messageSend($adminContentHTML, $adminContent, $admin, $conf['email.']['from'], $conf['email.']['fromName'], $replyTo);
		}

		// Send mail to user
		if ($recipient && ($content != '' || $contentHTML != '')) {
			$replyToAdmin = $conf['email.']['replyToAdmin'] ?: '';
			self::messageSend($contentHTML, $content, $recipient, $conf['email.']['from'], $conf['email.']['fromName'], $replyToAdmin, $fileAttachment);
		}
	}

	/**
	 * Invokes the HTML mailing class
	 *
	 * @param string  $HTMLContent: HTML version of the message
	 * @param string  $PLAINContent: plain version of the message
	 * @param string  $recipient: email address
	 * @param string  $fromEmail: email address
	 * @param string  $fromName: name
	 * @param string  $replyTo: email address
	 * @param string  $fileAttachment: file name
	 * @return void
	 */
	static protected function messageSend($HTMLContent, $PLAINContent, $recipient, $fromEmail, $fromName, $replyTo = '', $fileAttachment = '')
	{
		if (trim($recipient) && (trim($HTMLContent) || trim($PLAINContent))) {
			$fromName = str_replace('"', '\'', $fromName);
			if (preg_match('#[/\(\)\\<>,;:@\.\]\[\s]#', $fromName)) {
				$fromName = '"' . $fromName . '"';
			}
			$defaultSubject = 'Front end user registration message';
			if ($HTMLContent) {
				$parts = preg_split('/<title>|<\\/title>/i', $HTMLContent, 3);
				$subject = trim($parts[1]) ? strip_tags(trim($parts[1])) : $defaultSubject;
			} else {
				// First line is subject
				$parts = explode(chr(10), $PLAINContent, 2);
				$subject = trim($parts[0]) ? trim($parts[0]) : $defaultSubject;
				$PLAINContent = trim($parts[1]);
			}
			$mail = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Mail\\MailMessage');
			$mail->setSubject($subject);
			$mail->setFrom(array($fromEmail => $fromName));
			$mail->setSender($fromEmail);
			$mail->setReturnPath($fromEmail);
			$mail->setReplyTo($replyTo ? array($replyTo => '') : array($fromEmail => $fromName));
			$mail->setPriority(3);
			// ATTACHMENT
			if ($fileAttachment && file_exists($fileAttachment)) {
				$mail->attach(\Swift_Attachment::fromPath($fileAttachment));
			}
			// HTML
			if (trim($HTMLContent)) {
				$HTMLContent = self::embedMedia($mail, $HTMLContent);
				$mail->setBody($HTMLContent, 'text/html');
			}
			// PLAIN
			$mail->addPart($PLAINContent, 'text/plain');
			// SET Headers and Content
			$mail->setTo(array($recipient));
			$mail->send();
		}
	}

	/**
	 * Embeds media into the mail message
	 *
	 * @param MailMessage $mail: mail message
	 * @param string $htmlContent: the HTML content of the message
	 * @return string the subtituted HTML content
	 */
	static protected function embedMedia(MailMessage $mail, $htmlContent)
	{
		$substitutedHtmlContent = $htmlContent;
		$media = array();
		$attribRegex = self::makeTagRegex(array('img', 'embed', 'audio', 'video'));
		// Split the document by the beginning of the above tags
		$codepieces = preg_split($attribRegex, $htmlContent);
		$len = strlen($codepieces[0]);
		$pieces = count($codepieces);
		$reg = array();
		for ($i = 1; $i < $pieces; $i++) {
			$tag = strtolower(strtok(substr($htmlContent, $len + 1, 10), ' '));
			$len += strlen($tag) + strlen($codepieces[$i]) + 2;
			$dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);
			// Fetches the attributes for the tag
			$attributes = self::getTagAttributes($reg[0]);
			if ($attributes['src']) {
				$media[] = $attributes['src'];
			}
		}
		foreach ($media as $key => $source) {
			$substitutedHtmlContent = str_replace(
				'"' . $source . '"',
				'"' . $mail->embed(\Swift_Image::fromPath($source)) . '"',
				$substitutedHtmlContent);
		}
		return $substitutedHtmlContent;
	}

	/**
	 * Creates a regular expression out of an array of tags
	 *
	 * @param array $tags: the array of tags
	 * @return string the regular expression
	 */
	static protected function makeTagRegex(array $tags)
	{
		$regexpArray = array();
		foreach ($tags as $tag) {
			$regexpArray[] = '<' . $tag . '[[:space:]]';
		}
		return '/' . implode('|', $regexpArray) . '/i';
	}

	/**
	 * This function analyzes a HTML tag
	 * If an attribute is empty (like OPTION) the value of that key is just empty. Check it with is_set();
	 *
	 * @param string $tag: is either like this "<TAG OPTION ATTRIB=VALUE>" or this " OPTION ATTRIB=VALUE>" which means you can omit the tag-name
	 * @return array array with attributes as keys in lower-case
	 */
	static protected function getTagAttributes($tag)
	{
		$attributes = array();
		$tag = ltrim(preg_replace('/^<[^ ]*/', '', trim($tag)));
		$tagLen = strlen($tag);
		$safetyCounter = 100;
		// Find attribute
		while ($tag) {
			$value = '';
			$reg = preg_split('/[[:space:]=>]/', $tag, 2);
			$attrib = $reg[0];
			$tag = ltrim(substr($tag, strlen($attrib), $tagLen));
			if (substr($tag, 0, 1) == '=') {
				$tag = ltrim(substr($tag, 1, $tagLen));
				if (substr($tag, 0, 1) == '"') {
					// Quotes around the value
					$reg = explode('"', substr($tag, 1, $tagLen), 2);
					$tag = ltrim($reg[1]);
					$value = $reg[0];
				} else {
					// No quotes around value
					preg_match('/^([^[:space:]>]*)(.*)/', $tag, $reg);
					$value = trim($reg[1]);
					$tag = ltrim($reg[2]);
					if (substr($tag, 0, 1) == '>') {
						$tag = '';
					}
				}
			}
			$attributes[strtolower($attrib)] = $value;
			$safetyCounter--;
			if ($safetyCounter < 0) {
				break;
			}
		}
		return $attributes;
	}
}