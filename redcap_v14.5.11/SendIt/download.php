<?php


// Disable authentication
defined("NOAUTH") or define("NOAUTH", true);
// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
SendIt::renderDownloadPage();