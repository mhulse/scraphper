<?php

require_once __DIR__ . '/../vendor/autoload.php';
use Scraphper\Scrape;
$scraphper = new Scrape;
$scraphper->dir = 'cache/';
$scraphper->fetch('https://www.google.com/');
echo $scraphper->body;
echo '<pre>';
echo $scraphper->header;
echo '</pre>';
echo $scraphper->log;
