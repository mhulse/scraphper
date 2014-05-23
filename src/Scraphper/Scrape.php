<?php

namespace Scraphper;

/**
 * PHP screen-scraping class with caching (including images).
 * Includes static methods to extract data out of HTML tables into arrays
 * or XML.
 * Supports sending XML requests and custom verbs with support for making
 * WebDAV requests to Microsoft Exchange Server.
 * 
 * @todo Convert log() output to pre-formatted plain text.
 * @todo Remove multiple exit points.
 * @author Troy Wolf <troy@troywolf.com>
 * @author Micky Hulse <m@mky.io>
 * @modified 2014/05/21 by @mhulse
 */

class Scrape {
	
	//----------------------------------
	// Public class variables:
	//----------------------------------
	
	public $dir;
	public $body;
	public $status;
	public $header;
	public $log;
	
	//----------------------------------
	// Private class variables:
	//----------------------------------
	
	private $name;
	private $filename;
	private $url;
	private $port;
	private $verb;
	private $ttl;
	private $headers;
	private $postvars;
	private $xmlrequest;
	private $connect_timeout;
	private $data_ts;
	
	//----------------------------------------------------------------------
	
	/**
	 * PHP 4 constructor.
	 *
	 * @see __construct
	 */
	
	public function Scrape() {
		
		$this->__construct();
		
	}
	
	/**
	 * Constructor.
	 *
	 * Configure defaults.
	 *
	 * @access public
	 * @return boolean
	 */
	
	function __construct() {
		
		$this->log = 'New `Scrape()` object instantiated.<br>' . "\n";
		
		# Seconds to attempt socket connection before giving up:
		$this->connect_timeout = 30; 
		
		# Set the 'dir' property to the directory where you want to store the
		# cached content. I suggest a folder that is not web-accessible. End this
		# value with a "/".
		$this->dir = realpath('./') . '/'; // Default to current dir.
		
		$this->clean();
		
		return TRUE;
		
	}
	
	//----------------------------------------------------------------------
	//
	// Public instance methods:
	//
	//----------------------------------------------------------------------
	
	/**
	 * Get the content.
	 * 
	 * Will use 'ttl' property to determine whether to get the content from
	 * the url or the cache.
	 *
	 * @param string $url
	 * @param integer $ttl
	 * @param string $name
	 * @param string $user
	 * @param string $pwd
	 * @param string $verb
	 * @return boolean
	 */
	
	public function fetch($url = '', $ttl = 0, $name = '', $user = '', $pwd = '', $verb = 'GET') {
		
		$this->log .= '--------------------------------<br>fetch() called<br>' . "\n";
		$this->log .= 'url: ' . $url . '<br>' . "\n";
		$this->status = '';
		$this->header = '';
		$this->body = '';
		
		if ( ! $url) {
			
			$this->log .= 'OOPS: You need to pass a URL!<br>';
			
			return FALSE;
			
		}
		
		$this->url = $url;
		$this->ttl = $ttl;
		$this->name = $name;
		$need_to_save = FALSE;
		
		if ($this->ttl == '0') {
			
			if ( ! $fh = $this->getFromUrl($url, $user, $pwd, $verb)) return FALSE;
			
		} else {
			
			if (strlen(trim($this->name)) == 0) $this->name = MD5($url);
			
			$this->filename = $this->dir . 'http_' . $this->name;
			$this->log .= 'Filename: ' . $this->filename . '<br>';
			$this->getFile_ts();
			
			if ($this->ttl == 'daily') {
				
				if (date('Y-m-d', $this->data_ts) != date('Y-m-d', time())) {
					
					$this->log .= 'cache has expired<br>';
					
					if ( ! $fh = $this->getFromUrl($url, $user, $pwd, $verb)) return FALSE;
					
					$need_to_save = TRUE;
					
					if ($this->getFromUrl()) return $this->saveToCache();
					
				} else {
					
					if ( ! $fh = $this->getFromCache()) return FALSE;
					
				}
				
			} else {
				
				if ((time() - $this->data_ts) >= $this->ttl) {
					
					$this->log .= 'cache has expired<br>';
					
					if ( ! $fh = $this->getFromUrl($url, $user, $pwd)) return FALSE;
					
					$need_to_save = TRUE;
					
				} else {
					
					if ( ! $fh = $this->getFromCache()) return FALSE;
					
				}
				
			}
			
		}
		
		# Get response header:
		$this->header = fgets($fh, 1024);
		$this->status = substr($this->header, 9, 3);
		
		while ((trim($line = fgets($fh, 1024)) != '') && ( ! feof($fh))) {
			
			$this->header .= $line;
			
			if ($this->status == '401' AND strpos($line, 'WWW-Authenticate: Basic realm="') === 0) {
				
				fclose($fh);
				$this->log .= 'Could not authenticate<br>' . "\n";
				return FALSE;
				
			}
			
		}
		
		# Get response body:
		while ( ! feof($fh)) {
			
			$this->body .= fgets($fh, 1024);
			
		}
		
		fclose($fh);
		
		if ($need_to_save) $this->saveToCache();
		
		return $this->status;
		
	}
	
	//----------------------------------------------------------------------
	//
	// Public static methods:
	//
	//----------------------------------------------------------------------
	
	/**
	 * Generic function to return data array from HTML table data.
	 *
	 * @todo Make HTML table tags lowercase.
	 * @param string $rawHTML The page source.
	 * @param string $needle Optional string to start parsing source from.
	 * @param integer $needle_within 0 = needle is BEFORE table, 1 = needle is within table.
	 * @param string $allowed_tags List of tags to NOT strip from data, e.g. "<a><b>".
	 * @return mixed
	 */
	
	public static function table_into_array($rawHTML, $needle = '', $needle_within = 0, $allowed_tags = '') {
		
		$upperHTML = strtoupper($rawHTML);
		$idx = 0;
		
		if (strlen($needle) > 0) {
			
			$needle = strtoupper($needle);
			$idx = strpos($upperHTML, $needle);
			
			if ($idx === FALSE) return FALSE;
			
			if ($needle_within == 1) {
				
				$cnt = 0;
				
				while(($cnt < 100) && (substr($upperHTML,$idx,6) != '<TABLE')) {
					
					$idx = strrpos(substr($upperHTML, 0, $idx - 1), '<');
					$cnt++;
					
				}
				
			}
			
		}
		
		$aryData = array();
		$rowIdx = 0;
		
		# If this table has a header row, it may use TD or TH, so check special
		# for this first row.
		$tmp = strpos($upperHTML, '<TR', $idx);
		
		if ($tmp === FALSE) return FALSE;
		
		$tmp2 = strpos($upperHTML, '</TR>', $tmp);
		
		if ($tmp2 === FALSE) return FALSE;
		
		$row = substr($rawHTML, $tmp, $tmp2 - $tmp);
		$pattern = '/<TH>|<TH\ |<TD>|<TD\ /';
		preg_match($pattern, strtoupper($row), $matches);
		$hdrTag = $matches[0];
		
		while ($tmp = strpos(strtoupper($row),$hdrTag) !== FALSE) {
			
			$tmp = strpos(strtoupper($row), '>', $tmp);
			
			if ($tmp === FALSE) return FALSE;
			
			$tmp++;
			$tmp2 = strpos(strtoupper($row), '</T');
			$aryData[$rowIdx][] = trim(strip_tags(substr($row, $tmp, $tmp2 - $tmp), $allowed_tags));
			$row = substr($row, $tmp2 + 5);
			preg_match($pattern, strtoupper($row), $matches);
			$hdrTag = $matches[0];
			
		}
		
		$idx = strpos($upperHTML, '</TR>', $idx) + 5;
		$rowIdx++;
		
		# Now parse the rest of the rows:
		$tmp = strpos($upperHTML, '<TR', $idx);
		
		if ($tmp === FALSE) return FALSE;
		$tmp2 = strpos($upperHTML, '</TABLE>', $idx);
		
		if ($tmp2 === FALSE) return FALSE;
		
		$table = substr($rawHTML, $tmp, $tmp2 - $tmp);
		
		while ($tmp = strpos(strtoupper($table), '<TR') !== FALSE) {
			
			$tmp2 = strpos(strtoupper($table), '</TR');
			
			if ($tmp2 === FALSE) return FALSE;
			
			$row = substr($table, $tmp, $tmp2 - $tmp);
			
			while ($tmp = strpos(strtoupper($row), '<TD') !== FALSE) {
				
				$tmp = strpos(strtoupper($row), '>', $tmp);
				
				if ($tmp === FALSE) return FALSE;
				
				$tmp++;
				$tmp2 = strpos(strtoupper($row), '</TD');
				$aryData[$rowIdx][] = trim(strip_tags(substr($row, $tmp, $tmp2 - $tmp), $allowed_tags));
				$row = substr($row, $tmp2 + 5);
				
			}
			
			$table = substr($table, strpos(strtoupper($table), '</TR>') + 5);
			$rowIdx++;
			
		}
		
		return $aryData;
		
	}
	
	/**
	 * Generic function to return xml dataset from HTML table data.
	 *
	 * @todo Convert outer string quotes to single string quotes.
	 * @param string $rawHTML The page source.
	 * @param string $needle Optional string to start parsing source from.
	 * @param integer $needle_within 
	 * @param string $allowedTags List of tags to NOT strip from data, e.g. "<a><b>".
	 * @return string
	 */
	
	public static function table_into_xml($rawHTML, $needle = '', $needle_within = 0, $allowedTags = '') {
		
		if ( ! $aryTable = self::table_into_array($rawHTML, $needle, $needle_within, $allowedTags)) return FALSE;
		
		$xml = "<?xml version=\"1.0\" standalone=\"yes\" \?\>\n";
		$xml .= "<TABLE>\n";
		$rowIdx = 0;
		
		foreach ($aryTable AS $row) {
			
			$xml .= "\t<ROW id=\"" . $rowIdx . "\">\n";
			$colIdx = 0;
			
			foreach ($row AS $col) {
				
				$xml .= "\t\t<COL id=\"" . $colIdx . "\">" . trim(utf8_encode(htmlspecialchars($col))) . "</COL>\n";
				$colIdx++;
				
			}
			
			$xml .= "\t</ROW>\n";
			$rowIdx++;
			
		}
		
		$xml .= "</TABLE>";
		
		return $xml;
		
	}
	
	//----------------------------------------------------------------------
	//
	// Private methods:
	//
	//----------------------------------------------------------------------
	
	/**
	 * Scrape content from url.
	 *
	 * @param string $url
	 * @param string $user
	 * @param string $pwd
	 * @param string $verb
	 * @return mixed
	 */
	
	private function getFromUrl($url, $user = '', $pwd = '', $verb = 'GET') {
		
		$this->log .= 'getFromUrl() called<br>';
		preg_match('~([a-z]*://)?([^:^/]*)(:([0-9]{1,5}))?(/.*)?~i', $url, $parts);
		$protocol = $parts[1];
		$server = $parts[2];
		$port = $parts[4];
		$path = $parts[5];
		
		if ($port == '') {
			
			if (strtolower($protocol) == 'https://') {
				
				$port = '443';
				
			} else {
				
				$port = '80';
				
			}
			
		}
		
		if ($path == '') $path = '/';
		
		if ( ! $sock = @fsockopen(((strtolower($protocol) == 'https://') ? 'ssl://' : '') . $server, $port, $errno, $errstr, $this->connect_timeout)) {
			
			$this->log .= 'Could not open connection. Error ' . $errno . ': ' . $errstr . '<br>' . "\n";
			return FALSE;
			
		}
		
		$this->headers['Host'] = $server . ':' . $port;
		
		if (($user != '') && ($pwd != '')) {
			
			$this->log .= 'Authentication will be attempted<br>' . "\n";
			$this->headers['Authorization'] = 'Basic ' . base64_encode($user . ':' . $pwd);
			
		}
		
		if (count($this->postvars) > 0) {
			
			$this->log .= 'Variables will be POSTed<br>' . "\n";
			$request = 'POST ' . $path . ' HTTP/1.0' . "\r\n";
			$post_string = '';
			
			foreach ($this->postvars AS $key=>$value) {
				
				$post_string .= '&' . urlencode($key) . '=' . urlencode($value);
				
			}
			
			$post_string = substr($post_string, 1);
			$this->headers['Content-Type'] = 'application/x-www-form-urlencoded';
			$this->headers['Content-Length'] = strlen($post_string);
			
		} elseif (strlen($this->xmlrequest) > 0) {
			
			$this->log .= 'XML request will be sent<br>' . "\n";
			$request = $verb . ' ' . $path . ' HTTP/1.0' . "\r\n";
			$this->headers['Content-Length'] = strlen($this->xmlrequest);
			
		} else {
			
			$request = $verb . ' ' . $path . ' HTTP/1.0' . "\r\n";
			
		}
		
		if (fwrite($sock, $request) === FALSE) {
			
			fclose($sock);
			$this->log .= 'Error writing request type to socket<br>' . "\n";
			return FALSE;
			
		}
		
		foreach ($this->headers AS $key=>$value) {
			
			if (fwrite($sock, $key . ': ' . $value . "\r\n") === FALSE) {
				
				fclose($sock);
				$this->log .= 'Error writing headers to socket<br>' . "\n";
				return FALSE;
				
			}
			
		}
		
		if (fwrite($sock, "\r\n") === FALSE) {
			
			fclose($sock);
			$this->log .= 'Error writing end-of-line to socket<br>' . "\n";
			return FALSE;
			
		}
		
		if (count($this->postvars) > 0) {
			
			if (fwrite($sock, $post_string . "\r\n") === FALSE) {
				
				fclose($sock);
				$this->log .= 'Error writing POST string to socket<br>' . "\n";
				return FALSE;
				
			}
			
		} elseif (strlen($this->xmlrequest) > 0) {
			
			if (fwrite($sock, $this->xmlrequest . "\r\n") === FALSE) {
				
				fclose($sock);
				$this->log .= 'Error writing xml request string to socket<br>' . "\n";
				return FALSE;
				
			}
			
		}
		
		return $sock;
		
	}
	
	/**
	 * Reset the instance back to mostly new state.
	 *
	 * @todo Update default user agent to something more contemporary?
	 * @return void
	 */
	
	private function clean() {
		
		$this->status = '';
		$this->header = '';
		$this->body = '';
		$this->headers = array();
		$this->postvars = array();
		
		# Try to use user agent of the user making this request. If not available,
		# default to IE6.0 on WinXP, SP1.
		if (isset($_SERVER['HTTP_USER_AGENT'])) {
			
			$this->headers['User-Agent'] = $_SERVER['HTTP_USER_AGENT'];
			
		} else {
			
			$this->headers['User-Agent'] = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)';
			
		}
		
		# Set referrer to the current script since in essence, it is the referring
		# page.
		if (substr($_SERVER['SERVER_PROTOCOL'], 0, 5) == 'HTTPS') {
			
			$this->headers['Referer'] = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			
		} else {
			
			$this->headers['Referer'] = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			
		}
		
	}
	
	/**
	 * Retrieve content from cache file.
	 *
	 * @return mixed
	 */
	
	private function getFromCache() {
		
		$this->log .= 'getFromCache() called<br>';
		
		# Create file pointer:
		if ( ! $fp = @fopen($this->filename, 'r')) {
			
			$this->log .= 'Could not open ' . $this->filename . '<br>';
			return FALSE;
			
		}
		
		return $fp;
		
	}
	
	/**
	 * Save content to cache file.
	 *
	 * @return boolean
	 */
	
	private function saveToCache() {
		
		$this->log .= 'saveToCache() called<br>';
		
		# Create file pointer:
		if ( ! $fp = @fopen($this->filename, 'w')) {
			
			$this->log .= 'Could not open ' . $this->filename . '<br>';
			return FALSE;
			
		}
		
		# Write to file:
		if ( ! @fwrite($fp, $this->header . "\r\n" . $this->body)) {
			
			$this->log .= 'Could not write to ' . $this->filename . '<br>';
			fclose($fp);
			return FALSE;
			
		}
		
		# Close file pointer:
		fclose($fp);
		return TRUE;
		
	}
	
	/**
	 * Get cache file modified date.
	 *
	 * @return [type]
	 */
	
	private function getFile_ts() {
		
		$this->log .= 'getFile_ts() called<br>';
		
		if ( ! file_exists($this->filename)) {
			
			$this->data_ts = 0;
			$this->log .= $this->filename . ' does not exist<br>';
			return FALSE;
			
		}
		
		$this->data_ts = filemtime($this->filename);
		return TRUE;
		
	}
	
}
