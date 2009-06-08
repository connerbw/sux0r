<?php

// Ajax
// Toggle subscription to a bookmark

require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/../../initialize.php');
require_once(dirname(__FILE__) . '/../../includes/suxLog.php');

// ---------------------------------------------------------------------------
// Ajax Failure
// ---------------------------------------------------------------------------

function failure($msg = null) {
    if (!headers_sent()) header("HTTP/1.0 500 Internal Server Error");
    if ($msg) echo "Something went wrong: \n\n $msg";
    die();
}

// ---------------------------------------------------------------------------
// Error checking
// ---------------------------------------------------------------------------

if (!isset($_SESSION['users_id'])) failure('Invalid user id');
if (!isset($_POST['id']) || !filter_var($_POST['id'], FILTER_VALIDATE_INT) || $_POST['id'] < 1) failure('Invalid bookmark id');

$id = $_POST['id'];

// ---------------------------------------------------------------------------
// Secondary error checking
// ---------------------------------------------------------------------------

require_once(dirname(__FILE__) . '/../../includes/suxBookmarks.php');
$bm = new suxBookmarks();

if (!$bm->getByID($id)) failure('Invalid bookmark ' . $id);

// ---------------------------------------------------------------------------
// Go
// ---------------------------------------------------------------------------

$module = 'bookmarks';
$link = 'link__bookmarks__users';
$col = 'bookmarks';

// Get image names from template config
require_once(dirname(__FILE__) . '/../../includes/suxTemplate.php');
$tpl = new suxTemplate($module);
$tpl->config_load('my.conf', $module);
$image = $tpl->get_config_vars('imgUnsubscribed');

$db = suxDB::get();
$query = "SELECT COUNT(*) FROM {$link} WHERE {$col}_id = ? AND users_id = ? ";
$st = $db->prepare($query);
$st->execute(array($id, $_SESSION['users_id']));

if ($st->fetchColumn() > 0) {
    // Delete
    $query = "DELETE FROM {$link} WHERE {$col}_id = ? AND users_id = ? ";
    $st = $db->prepare($query);
    $st->execute(array($id, $_SESSION['users_id']));
}
else {
    // Insert
    require_once(dirname(__FILE__) . '/../../includes/suxLink.php');
    $suxLink = new suxLink();
    $suxLink->saveLink($link, 'users', $_SESSION['users_id'], $col, $id);
    $image = $tpl->get_config_vars('imgSubscribed');
}

// Log
$log = new suxLog();
$log->write($_SESSION['users_id'], "sux0r::bookmarks::toggle() bookmarks_id: {$id}", 1); // Private

// ---------------------------------------------------------------------------
// Clear template caches
// ---------------------------------------------------------------------------

$tpl->clear_cache(null, "{$_SESSION['nickname']}"); // clear all caches with "nickname" as the first cache_id group

// ---------------------------------------------------------------------------
// Return image string to Ajax request
// ---------------------------------------------------------------------------

echo trim($image);
exit;

?>
