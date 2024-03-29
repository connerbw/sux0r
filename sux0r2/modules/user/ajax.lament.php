<?php

// Ajax
// Lament to the log

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../initialize.php');

// ---------------------------------------------------------------------------
// Error checking
// ---------------------------------------------------------------------------

if (!isset($_SESSION['users_id'])) die();
if (empty($_POST['lament'])) die();

$lament = strip_tags($_POST['lament']);
$lament = trim($lament);
$lament = substr($lament, 0, 500);

if (!$lament) die();

// ---------------------------------------------------------------------------
// Go
// ---------------------------------------------------------------------------

$log = new suxLog();
$log->write($_SESSION['users_id'], $lament);

// ---------------------------------------------------------------------------
// Clear template caches
// ---------------------------------------------------------------------------

$tpl = new suxTemplate('user');
$tpl->clearCache('profile.tpl', "{$_SESSION['nickname']}|{$_SESSION['nickname']}");

echo $lament;

