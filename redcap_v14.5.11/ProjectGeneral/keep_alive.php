<?php 


if (isset($_GET['pid']) && is_numeric($_GET['pid']))
{
	require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
}
else
{
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
}

print "1";