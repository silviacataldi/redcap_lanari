<?php

// Is this an External Module passthru request? If so, init based on context and delegate to EM framework
if (isset($_GET["__passthru"]) && $_GET["__passthru"] == "ExternalModules") {
    define("EM_ENDPOINT", true);
    if (isset($_GET["pid"])) {
        require_once dirname(__FILE__) . "/Config/init_project.php";
    }
    else {
        require_once dirname(__FILE__) . "/Config/init_global.php";
    }
    require_once APP_PATH_EXTMOD . "index.php";
    exit;
}

// Config for non-project pages
require_once dirname(__FILE__) . "/Config/init_global.php";

// Call the real page. This page is just a shell for the index file in the Home directory.
require_once APP_PATH_DOCROOT . "Home/index.php";