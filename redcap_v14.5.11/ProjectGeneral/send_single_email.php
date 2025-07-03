<?php

/**
 * GENERIC SCRIPT FOR SENDING INVIDUAL EMAILS
 * Accepts all email attributes as Post parameters: subject, from, to, message
 */


// Call config file
if (isset($_GET['pid'])) {
	require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
} else {
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
}

// Make sure request is Post and has all parameters needed
if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['from']) || !isset($_POST['to']) || !isset($_POST['message'])) {
	// Return "0" if this is an ajax call
	if ($isAjax) {
		exit("0");
	} else {
		exit("ERROR!");
	}
}

// Set blank value for subject, if missing
if (!isset($_POST['subject'])) $_POST['subject'] = '';
// Unescape parameters
$_POST['from'] = strtolower(strip_tags(label_decode($_POST['from'])));
$_POST['to'] = strip_tags(label_decode($_POST['to']));
$_POST['subject'] = strip_tags(label_decode($_POST['subject']));
$_POST['message'] = decode_filter_tags($_POST['message']);

// The From address must match a user's emails in their Profile
$allowedFromAddresses = [strtolower($user_email)];
if ($user_email2 != '') $allowedFromAddresses[] = strtolower($user_email2);
if ($user_email3 != '') $allowedFromAddresses[] = strtolower($user_email3);
if (!in_array($_POST['from'], $allowedFromAddresses)) {
    if ($isAjax) {
        exit("0");
    } else {
        exit("ERROR!");
    }
}

// Set up email to be sent
$email = new Message();
$email->setFrom($_POST['from']);
if (isset($_POST['from_display_name'])) $email->setFromName($_POST['from_display_name']);
$email->setTo($_POST['to']);
$email->setSubject($_POST['subject']);
$email->setBody('<html><body style="font-family:arial,helvetica;">'.nl2br($_POST['message']).'</body></html>');
if ($email->send()) {
	Logging::logEvent("","","MANAGE",$_POST['to'],"From: {$_POST['from']}\nTo: {$_POST['to']}\nSubject: {$_POST['subject']}\nMessage:\n{$_POST['message']}\n","Send email");
	if ($isAjax) {
		exit("1");
	} else {
		exit("EMAIL SENT!");
	}
} else {
	if ($isAjax) {
		exit("0");
	} else {
		exit("ERROR!");
	}
}