<?php

// Ajax
// Train a document using genericBayesInterface()

/*
Steps to add a new module to this trainer:
1) Adjust $valid_links and $valid_modules variables, accordingly
2) Create a new procedure/condition in getBody() function
*/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../initialize.php');

// ---------------------------------------------------------------------------
// Variables
// ---------------------------------------------------------------------------

$valid_links = array('messages', 'rss_items', 'bookmarks');
$valid_modules = array('blog', 'feeds', 'bookmarks');

// ---------------------------------------------------------------------------
// Maleable function
// ---------------------------------------------------------------------------

function getBody($link, $id) {

    $body = false;

    if ($link == 'messages') {
        $msg = new suxThreadedMessages();
        $body = $msg->getByID($id);
        $body = "{$body['title']} \n\n {$body['body_plaintext']}";
    }
    elseif ($link == 'rss_items') {
        $rss = new suxRSS();
        $body = $rss->getItemByID($id);
        $body = "{$body['title']} \n\n {$body['body_plaintext']}";
    }
    elseif ($link == 'bookmarks') {        
        $bm = new suxBookmarks();
        $body = $bm->getByID($id);
        $body = "{$body['title']} \n\n {$body['body_plaintext']}";
    }

    return $body;

}

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

if (!isset($_SESSION['users_id'])) failure('Invalid users_id');
if (!isset($_POST['link']) || !in_array($_POST['link'], $valid_links)) failure('Invalid link');
if (!isset($_POST['module']) || !in_array($_POST['module'], $valid_modules)) failure('Invalid module');
if (!isset($_POST['id']) || !filter_var($_POST['id'], FILTER_VALIDATE_INT) || $_POST['id'] < 1) failure('Invalid id');
if (!isset($_POST['cat_id']) || !filter_var($_POST['cat_id'], FILTER_VALIDATE_INT) || $_POST['cat_id'] < 1) failure('Invalid cat_id');

$link = $_POST['link'];
$module = $_POST['module'];
$id = $_POST['id'];
$cat_id = $_POST['cat_id'];

// ---------------------------------------------------------------------------
// Secondary error checking
// ---------------------------------------------------------------------------

$suxLink = new suxLink();
$nb = new suxUserNaiveBayesian();
$user = new suxUser();
$log = new suxLog();

if (!$nb->isCategoryTrainer($cat_id, $_SESSION['users_id'])) failure('User is not authorized to train category.'); // Something is wrong, abort

// ---------------------------------------------------------------------------
// Create $body based on $link type
// ---------------------------------------------------------------------------

$body = getBody($link, $id);
if ($body === false) failure('No $body, nothing to train.'); // Something is wrong, abort.

// ---------------------------------------------------------------------------
// Go!
// ---------------------------------------------------------------------------

// Get all the bayes_documents linked to this message where user is trainer
// Also get associated vectors

$link_table = $suxLink->buildTableName($link, 'bayes_documents');
$innerjoin = "
INNER JOIN {$link_table} ON {$link_table}.bayes_documents_id = bayes_documents.id
INNER JOIN {$link} ON {$link_table}.{$link}_id = {$link}.id
INNER JOIN bayes_categories ON bayes_categories.id = bayes_documents.bayes_categories_id
INNER JOIN bayes_auth ON bayes_categories.bayes_vectors_id = bayes_auth.bayes_vectors_id
";

$query = "
SELECT bayes_documents.id, bayes_auth.bayes_vectors_id FROM bayes_documents
{$innerjoin}
WHERE {$link}.id = ?
AND bayes_auth.users_id = ? AND (bayes_auth.owner = true OR bayes_auth.trainer = true)
"; // Note: bayes_auth WHERE condition equivilant to nb->isCategoryTrainer()

$db = suxDB::get();
$st = $db->prepare($query);
$st->execute(array($id, $_SESSION['users_id']));
$tmp = $st->fetchAll(PDO::FETCH_ASSOC);

// Since we are only training one category/vector at a time, we need to make
// sure we don't untrain other unrelated vectors here.

$vec_id = $nb->getVectorByCategory($cat_id);
foreach ($tmp as $val) {
    if (isset($vec_id[$val['bayes_vectors_id']])) {
        $nb->untrainDocument($val['id']);
    }
}

// Recategorize
$doc_id = $nb->trainDocument($body, $cat_id);
$suxLink->saveLink($link_table, 'bayes_documents', $doc_id, $link, $id);


// Log
$log->write($_SESSION['users_id'], "sux0r::bayes::train() doc_id: $doc_id, cat_id: $cat_id", 1); // Private

// ---------------------------------------------------------------------------
// Clear template caches
// ---------------------------------------------------------------------------

$tpl = new suxTemplate($module);
$tpl->clearCache(null, "{$_SESSION['nickname']}"); // clear all caches with "nickname" as the first cache_id group

