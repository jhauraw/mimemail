<?php
/* vim: set expandtab tabstop=2 shiftwidth=2: */

/**
 * MimeMail Class 1.1 - 2004-06-18
 *
 * (c) Copyright 2004 Jhaura Wachsman. All Rights Reserved.
 *
 * Author: Jhaura Wachsman <jw at jhaurawachsman dot com>
 *
 * PHP Class object for use in generating RFC MIME compliant email message
 * headers and bodies. Supports plain text, HTML or Multipart message formats.
 * Simple clear methods allow you to quickly generate one-off or personalized
 * messages in masse. Works with PHP's mail() function or MTAs like qmail and
 * Sendmail. Outputs (1) string headers formatted to RFC MIME specifications
 * and (2) string body with optional quoted-printable encoding (recommended
 * for HTML messages).
 *
 * Based on RFC 2821, MIME RFCs and the excellent MIME::Lite PERL module.
 *
 * Private methods and vars begin with an underscore (_).
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * @author Jhaura Wachsman <jw at jhaurawachsman dot com>
 * @version $Revision: 1.1 $
 * @package MimeMail
 */

class MimeMail {

	var $_hdrs;    # assoc array container holds header key/values
	var $_attribs; # assoc array container holds mime content-* key/values
	var $_parts;   # part level objects array (one part object per element)
	var $_data;    # message data container
	var $_ref;     # _parts array index position of current part object

	var $_crlf         = "\n";    # host os end of line
	var $_rfc_date     = 'r (T)'; # RFC 2821 date format (used in date() func)
	var $_mime_version = '1.0';   # mime version used to construct message
	var $_prologue     = 'This is a multi-part message in MIME format.';

	var $qp_len = 76; # length of line for quoted printable encoding

	/* MIMEMAIL CONSTRUCTOR ------------------------------------------------- */

	/**
	 * Calls the init method with the top param set to true. This creates a top
	 * level object.
	 *
	 * @params see the init method
	 *
	 * @access public
	 * @return void
	 */

	function MimeMail($prm = false, $top = true) { $this->_init($prm, $top); }

	/**
	 * Create a new instance of the MimeMail class and stores a reference to the
	 * object in the _parts array. Also, set the class wide _ref param to the
	 * array index of this part. This is used by other class methods when
	 * working with either the top level or the parts objects.
	 *
	 * @params see the init method
	 *
	 * @access public
	 * @return a reference to this part object in the _parts array.
	 */

	function &part($prm) {

		# Add the reference to the _parts array
		$this->_parts[] =& new MimeMail($prm, $top = false);

		# Set _ref to the current object ref
		$this->_ref = count($this->_parts) -1;

		return $this->_parts[$this->_ref];
	}

	/**
	 * A helper method, determines which object reference to work on. If _ref is
	 * set we work on one of the parts objects in the _parts array, otherwise we
	 * work on the top-level object.
	 *
	 * @access private
	 * @returns a reference to the current working object.
	 */

	function &_partRef() {

		isset($this->_ref) ? $obj =& $this->_parts[$this->_ref] : $obj =& $this;

		return $obj;
	}

	/**
	 * Generates a boundary identifier for multipart messages. As per RFC 2046
	 * 5.1.1 not longer than 70 characters, md5 returns a 32 character string.
	 *
	 * @access private
	 * @return a unique boundary string.
	 */

	function _genBoundary() {

		return '_----=_Part_' . md5(uniqid(time())) . '_';
	}

	/**
	 * The core method of the class, populates the object with all the params
	 * input by the user. Currently just content-* header and data values.
	 *
	 * @param [prm] an assoc array of user input key-value pairs
	 * @param [top] is this the top-level object or a part-level
	 *
	 * Valid [prm] keys -------------------------------------------------------
	 *
	 * @param [type]        any valid MIME content-type, e.g., text/plain or
	 *                      image/jpeg if this is a multipart message, the
	 *                      top-level should should be set to one of the
	 *                      multipart types
	 *
	 * @param [charset]     charset used for this part, e.g., iso-8859-1
	 * @param [id]          content for this part, usually only used when
	 *                      embedding images or using attachments
	 *
	 * @param [data]        actual part body, message body content
	 * @param [encoding]    encoding to be used or used on [data], e.g., 7bit or
	 *                      quoted-printable
	 *
	 * @param [disposition] RFC 2183 content-disposition, (inline|attachment)
	 *
	 * @param [length]      byte size of [data] after encoding, if not set and
	 *                      not toggled off we set it
	 *
	 * @param [filename]    used when a file is input, sets this value in
	 *                      content-type and -disposition
	 *
	 * @access private
	 * @return void
	 */

	function _init($prm, $top) {

		# Set this object's content-type
		isset($prm['type']) ? $type = $prm['type'] : $type = false;

		if ($type) $this->attr('content-type', $type);

		# If this is a multipart message, generate a boundary
		if ($type && (strpos($type, 'multipart/') === 0)) {

			isset($prm['boundary']) ? $boundary = $prm['boundary'] : $boundary = $this->_genBoundary();

			$this->attr('content-type.boundary', $boundary);
		}

		# If this is the top-level object
		if ($top) {

			# Add MIME-Version header
			$this->add('mime-version', $this->_mime_version);

			# Add Date header
			$this->add('date', date($this->_rfc_date));
		}

		# Content charset
		if (isset($prm['charset'])) $this->attr('content-type.charset', $prm['charset']);

		# Content id
		if (isset($prm['id'])) $this->attr('content-id', $prm['id']);

		# Part data, need to do BEFORE content-transfer-encoding/disposition/length
		if (isset($prm['data'])) $this->_data = trim($prm['data']);

		# Content transfer encoding, drop param to lower, use default if not set
		if (isset($prm['encoding'])) $this->attr('content-transfer-encoding', strtolower($prm['encoding']));

		# Content disposition
		if (isset($prm['disposition'])) $this->attr('content-disposition', $prm['disposition']);

		# If we have some data
		if ($this->_data) {

			if (!isset($prm['nolen'])) {

				# Content length, when set by user
				if (isset($prm['length'])) $len = trim($prm['length']);

				# Grab the encoding for this part (just set above)
				$enc = $this->attr('content-transfer-encoding');

				/**
				 * :SPECIAL: For Quoted-Printable encoding we want to re-assign
				 * [_data] to the qp version of itself. Also we want to unset any user
				 * input length because now the data length will have changed.
				 */

				if ($enc == 'quoted-printable') {

					$this->_data = $this->_quotedPrintableEncode($this->_data);

					unset($len);
				}

				# Compute length, if we don't already have it.
				if (!isset($len)) $len = strlen($this->_data);

				# Set the length in a content type header
				if ($len != '') $this->attr('content-length', $len);

			} # end if not nolen

		} # end if data

		# Filename for attached files
		if (isset($prm['filename'])) {

			$filename = trim($prm['filename']);

			$this->attr('content-type.name', $filename);
			$this->attr('content-disposition.filename', $filename);
		}
	}

	/**
	 * Allows the user to add a non-MIME header, such as From: me@us.com or
	 * X-Mailer: My Mailer 1.0. Two optional calling styles may be used:
	 *
	 * 1. Get value style: add('from'); No value param is set. In this case the
	 * value of the header in the current object will be returned.
	 *
	 * 2. Delete header style: add('from', ''); Value param is set to empty. In
	 * this case the header will be deleted from the object.  This style can be
	 * used to delete unwanted headers that are set by this class automatically,
	 * such as the Date: and MIME-Version headers
	 *
	 * @param [tag]   the id of the header such as 'from' or 'x-mailer',
	 *                case-insensitive
	 *
	 * @param [value] the value of the header such as me@us.com or Mailer 1.0
	 *
	 * @access public
	 * @return mixed, tag value only when the optional get value style is used,
	 * false on no value set.
	 */

	function add($tag, $value = false) {

		# Set the current object to work on
		$obj =& $this->_partRef();

		# Clean and drop to lowercase
		$tag = strtolower(trim($tag));

		# If this is the add or delete style, delete the header
		if ($value !== false && isset($obj->_hdrs[$tag])) unset($obj->_hdrs[$tag]);

		# If this is the add style add the header, else return the header value
		if ($value) {

			$obj->_hdrs[$tag] = trim($value);

		} else {

			return isset($obj->_hdrs[$tag]) ? $obj->_hdrs[$tag] : false;
		}
	}

	/**
	 * Allows the user to add a MIME Content-* header to the part or an
	 * attribute to an existing Content-* header. As with the add method both
	 * optional Get and Delete calling styles are supported.
	 *
	 * Special dotted format used to add/get/delete attributes to content-*
	 * headers such as 'content-type.charset'
	 *
	 * @param [attr]  the id of the attribute such as 'content-type' or
	 *                'content-type.charset', case-insensitive
	 *
	 * @param [value] the value of the header such as 'text/plain' or 'us-ascii'
	 *
	 * @access public
	 * @return mixed, attr value only when the optional get value style is used,
	 * otherwise false.
	 */

	function attr($attr, $value = false) {

		# Set the current object to work on
		$obj =& $this->_partRef();

		# Clean and drop to lowercase
		$attr = strtolower(trim($attr));

		$tag = strtok($attr, '.'); # get first token
		$sub = strtok('.');        # get second token

		# If this is the add or delete style, delete the header
		if ($value !== false && isset($obj->_attribs[$tag]['' . $sub . ''])) unset($obj->_attribs[$tag]['' . $sub . '']);

		/**
		 * Add to forced assoc array or return value, sub will be empty '' for
		 * anonymous first sub-field.
		 */

		if ($value) {

			$obj->_attribs[$tag]['' . $sub . ''] = trim($value);

		} else {

			return isset($obj->_attribs[$tag]['' . $sub . '']) ? $obj->_attribs[$tag]['' . $sub . ''] : false;
		}
	}

	/**
	 * Prints out the entire message headers and body part(s) to a string.
	 *
	 * @access public
	 * @return an entire message string.
	 */

	function all2Str() {

		$o = $this->hdrs2Str();

		# Append an eol between the hdrs and body, signaling end of headers
		$o .= $this->_crlf;

		$o .= $this->body2Str();

		return $o;
	}

	/**
	 * Prints just the top-level headers to a string.
	 *
	 * @access public
	 * @return headers string.
	 */

	function hdrs2Str() {

		# Must first clear any part reference
		unset($this->_ref);

		return $this->_printHeaders();
	}

	/**
	 * Prints just the body to a string.
	 *
	 * @access public
	 * @return body string.
	 */

	function body2Str() {

		return $this->_printBody();
	}

	/**
	 * Print out the headers for this part.
	 *
	 * @access private
	 * @return a header string in RFC format.
	 */

	function _printHeaders() {

		# Set the current object to work on
		$obj =& $this->_partRef();

		$o = '';

		# Add the non-MIME-headers to the string
		$o .= $this->_fieldsStr($obj->_hdrs);

		# Format the MIME-headers into a hdrs type string
		$attr = $this->_attrStr($obj->_attribs);

		# Add the MIME-headers to the string
		$o .= $this->_fieldsStr($attr);

		return $o;
	}

	/**
	 * Print out the body, if this is a multipart message, print out each part
	 * in succession, separated by a boundary.
	 *
	 * @access private
	 * @return a body string in RFC format.
	 */

	function _printBody() {

		# Must first clear any part reference
		unset($this->_ref);

		$o = '';

		# Get the content type from the top-level part
		$type = $this->attr('content-type');

		# If this is a multipart message
		if (strpos($type, 'multipart/') === 0) {

			$boundary = $this->attr('content-type.boundary');

			# Prologue
			$o .= $this->_prologue . $this->_crlf;

			# Loop through the _parts obj array
			$i = 0;
			foreach ($this->_parts as $obj) {

				# This is our current object reference
				$this->_ref = $i;

				# Add the part boundary
				$o .= $this->_crlf . '--' . $boundary . $this->_crlf;

				# Add the headers for this part
				$o .= $this->_printHeaders();

				# Append a eol as the spacer between the headers and body
				$o .= $this->_crlf;

				# Normalize line endings in data and add it
				$o .= $obj->_data;

				$i++;

				unset($this->_ref);
			}

			# Epilogue
			$o .= $this->_crlf . '--' . $boundary . '--' . $this->_crlf. $this->_crlf;

		# This is a single part simple message, just print body
		} else {

			$o .= $this->_data;
		}

		return $o;
	}

	/**
	 * Takes an address and a comment and formats it as commonly used in From:
	 * and To: headers.
	 *
	 * @param [address] an email address
	 * @param [comment] comment string, usually name
	 *
	 * @access public
	 * @return string formatted as safe header data.
	 */

	function format($address, $comment = false) {

		$address = trim($address);
		$comment = trim($comment);

		return $comment ? '"' . $comment . '" <' . $address . '>' : $address;
	}

	/**
	 * Format the MIME Content-* headers array into a header style string.
	 *
	 * @param [attr] multi-dimensional assoc array of attributes set by attr
	 *               method
	 *
	 * @access public
	 * @return mixed, an assoc array with header id as key and attributes string
	 * as value, or false if no array is given
	 */

	function _attrStr($attr) {

		if (!is_array($attr)) return false;

		$arr = array();

		foreach ($attr as $tag => $sub) {

			$str = '';

			foreach ($sub as $k => $v) {

				/**
				 * Only add  semi-colon ';' if more than one attr, drop each attr to
				 * own line preceded by a space
				 */

				$k == '' ? $str .= $v : $str .= ';' . $this->_crlf . ' ' .$k . '="' . $v . '"';
			}

			$arr[$tag] = $str;
		}

		return $arr;
	}

	/**
	 * Format headers array into a header style string. Formats tags into nice
	 * looking strings using regex.
	 *
	 * @param [fields] an assoc array of tag/value pairs set by add or attr_str
	 * methods
	 *
	 * @access public
	 * @return a header string, or false if no array is given.
	 */

	function _fieldsStr($fields) {

		if (!is_array($fields)) return false;

		$str = '';

		foreach ($fields as $k => $v) {

			if (!isset($v) || $v == '') continue;

			# Uppercase the first character of each word
			$k = preg_replace('/\b[a-z]/e', "strtoupper('\\0')", $k);

			# Uppercase mime-
			$k = preg_replace('/^mime-/i', 'MIME-', $k);

			# Format with ':' and eol
			$str .= $k . ': ' . $v . $this->_crlf;
		}

		return $str;
	}

		/**
		 * Encodes data in quoted-printable format with optional line length. This
		 * function modified from Richard Heyes's (richard at phpguru dot org)
		 * version in mail mime class.
		 *
		 * @param [data]   data to encode
		 * @param [qp_len] max line length, should not be more than 76 chars
		 *
		 * @access private
		 * @return string, encoded data.
		 */

		function _quotedPrintableEncode($data, $qp_len = false) {

				if (!$qp_len) $qp_len = $this->qp_len;

				$lines  = preg_split('/\r?\n/', $data);
				$escape = '=';
				$output = '';

				while (list(, $line) = each($lines)) {

						$linlen  = strlen($line);
						$newline = '';

						for ($i = 0; $i < $linlen; $i++) {

								$char = substr($line, $i, 1);
								$dec  = ord($char);

								# Convert space at eol only
								if ($dec == 32 && $i == ($linlen - 1)) {

										$char = $escape . '20';

								} elseif($dec == 9) { # do nothing if a tab.

								} elseif($dec == 61 || $dec < 32 || $dec > 126) {

										$char = $escape . strtoupper(sprintf('%02s', dechex($dec)));
								}

								# EOL is not counted
								if ((strlen($newline) + strlen($char)) >= $qp_len) {

										# Soft line break; " =\r\n" is okay
										$output .= $newline . $escape . $this->_crlf;
										$newline = '';
								}

								$newline .= $char;

						} # end of for

						$output .= $newline . $this->_crlf;

				} # end while

				return rtrim($output); # don't want last crlf
		}
}
?>