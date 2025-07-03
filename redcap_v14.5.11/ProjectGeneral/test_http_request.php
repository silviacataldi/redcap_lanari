<?php


// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// Can only access this page as a Post request
if (!isset($_POST['url']) || !isURL($_POST['url'])) exit('0');

// Set timeout value for http request
$timeout = 10; // seconds

// Use HTTP Post method
if (isset($_POST['request_method']) && $_POST['request_method'] == 'post')
{
    $response = http_post($_POST['url'], array(), $timeout);
}

// Use HTTP GET method
else
{
    $response = http_get($_POST['url'], $timeout);
}

// Return boolean response
print ($response === false || $response === null) ? '0' : '1';