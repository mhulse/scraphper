<?php
/**
 * Scraphper\Scrape fetch() example usage.
 *
 * Used to "screen-scrape" images and cache them locally for any number of
 * seconds. You use this script in-line within img tags like so:
 *
 * <img src="image.php?ttl=300&url=http%3A%2F%2Fwww.somedomain.com%2Fsomeimage.gif" />
 * 
 * You must url encode the url within the src attribute.
 *
 * @author Troy Wolf <troy@troywolf.com>
 * @contributor Micky Hulse <m@mky.io>
 * @modified 2005/06/21
 */

require_once(__DIR__ . '/../vendor/autoload.php');

$url = (isset($_GET['url'])) ? $_GET['url'] : '';
$ttl = (isset($_GET['ttl'])) ? $_GET['ttl'] : 0;

$name = basename($url);
$extension = strtolower(substr(strrchr($filename, '.'), 1));
switch($extension) {
	case 'gif':
		$type = 'image/gif';
		break;
	case 'png':
		$type = 'image/png';
		break;
	case ('jpeg' OR 'jpg'):
		$type = 'image/jpg';
		break;
	default:
		$type = 'image/jpg';
}
header('Content-Type: ' . $type);

$scraphper = new Scraphper\Scrape;
$scraphper->dir = 'cache/';
$scraphper->fetch($url, $ttl);
echo $scraphper->body;
