<?php

/**
* feeds
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.fsf.org/licensing/licenses/gpl-3.0.html
*/

class feeds extends bayesComponent {

    // Module name
    protected $module = 'feeds';

    // Object: suxRSS()
    protected $rss;

    // Object: suxUserNaiveBayesian()
    protected $nb;

    // Var: used by filter() method
    public $users_id;


    /**
    * Constructor
    *
    */
    function __construct() {

        // Declare objects
        $this->nb = new suxUserNaiveBayesian();
        $this->rss = new suxRSS();
        $this->r = new feedsRenderer($this->module); // Renderer
        parent::__construct(); // Let the parent do the rest

        // Declare properties
        $this->r->bool['analytics'] = true; // Turn on analytics

    }


    function user($nickname) {

        // Get users_id based on nickname
        $user = $this->user->getByNickname($nickname);
        if (!$user) suxFunct::redirect(suxFunct::makeUrl('/feeds'));
        $this->users_id = $user['users_id']; // Needs to be in externally accessible variable for filter()
        unset($user);

        // Assign stuff
        $this->r->text['form_url'] = suxFunct::makeUrl("/feeds/user/$nickname"); // Forum Url
        $cache_id = null;

        $this->r->title .= " | {$this->r->gtext['feeds']} | $nickname";

        if ([$vec_id, $cat_id, $threshold, $start, $search] = $this->nb->isValidFilter()) {

            // ---------------------------------------------------------------
            // Filtered results
            // ---------------------------------------------------------------

            // User has subscriptions, we need special JOIN queries
            $max = $this->countUserItems($this->users_id);
            $eval = '$this->getUserItems($this->users_id, $this->pager->limit, $start)';
            $this->r->arr['feeds']  = $this->filter($max, $vec_id, $cat_id, $threshold, $start, $eval, $search); // Important: $start is a reference

            if ($start < $max) {
                if ($threshold !== false) $params = array('threshold' => $threshold, 'filter' => $cat_id);
                else $params = array('filter' => $cat_id);
                $params['search'] = $search;
                $url = suxFunct::makeUrl("/feeds/user/$nickname", $params);
                $this->r->text['pager'] = $this->pager->continueURL($start, $url);
            }


        }
        else {

            // ---------------------------------------------------------------
            // Paged results, cached
            // ---------------------------------------------------------------

            // Get nickname
            if (isset($_SESSION['nickname'])) $nn = $_SESSION['nickname'];
            else $nn = 'nobody';

            $this->pager->setStart(); // Start pager

            // "Cache Groups" using a vertical bar |
            $cache_id = "$nn|user|$nickname|{$this->pager->start}";
            $this->tpl->caching = 1;

            if (!$this->tpl->isCached('scroll.tpl', $cache_id)) {

                // User has subscriptions, we need special JOIN queries
                $this->pager->setPages($this->countUserItems($this->users_id));
                $this->r->arr['feeds'] = $this->getUserItems($this->users_id, $this->pager->limit, $this->pager->start);

                $this->r->text['pager'] = $this->pager->pageList(suxFunct::makeUrl("/feeds/user/$nickname"));
                if (!(is_countable($this->r->arr['feeds']) ? count($this->r->arr['feeds']) : 0)) $this->tpl->caching = 0; // Nothing to cache, avoid writing to disk

            }

        }

        $this->tpl->assign('users_id', $this->users_id);

        $this->tpl->display('scroll.tpl', $cache_id);

    }



    /**
    * Listing
    *
    * @param int $feeds_id a feed id
    */
    function listing($feeds_id = null) {

        // Check if the user has any subscriptions
        $subscriptions = array();
        if (isset($_SESSION['users_id'])) {
            $subscriptions = $this->link->getLinks('link__rss_feeds__users', 'users', $_SESSION['users_id']);
            $this->tpl->assign('users_id', $_SESSION['users_id']);
        }

        // Assign stuff
        $this->r->text['form_url'] = suxFunct::makeUrl("/feeds/$feeds_id"); // Forum Url
        $cache_id = null;

        // Title
        if ($feeds_id) {
            $this->r->title .= " | {$this->r->gtext['feed']}";
            $tmp = $this->rss->getFeedByID($feeds_id);
            if ($tmp) $this->r->title .= " | {$tmp['title']}";
        }
        else {
            $this->r->title .= " | {$this->r->gtext['feeds']}";
        }

        if ([$vec_id, $cat_id, $threshold, $start, $search] = $this->nb->isValidFilter()) {

            // ---------------------------------------------------------------
            // Filtered results
            // ---------------------------------------------------------------

            if ($feeds_id || !(is_countable($subscriptions) ? count($subscriptions) : 0)) {
                // Regular queries
                $max = $this->rss->countItems($feeds_id);
                $eval = '$this->rss->getItems($this->pager->limit, $start, ' . ($feeds_id ?: 'null') . ')';
            }
            else {
                // User has subscriptions, we need special JOIN queries
                $max = $this->countUserItems($_SESSION['users_id']);
                $eval = '$this->getUserItems($_SESSION[\'users_id\'], $this->pager->limit, $start)';
            }

            $this->r->arr['feeds']  = $this->filter($max, $vec_id, $cat_id, $threshold, $start, $eval, $search);  // Important: $start is a reference

            if ($start < $max) {
                if ($threshold !== false) $params = array('threshold' => $threshold, 'filter' => $cat_id);
                else $params = array('filter' => $cat_id);
                $params['search'] = $search;
                $url = suxFunct::makeUrl("/feeds/$feeds_id", $params);
                $this->r->text['pager'] = $this->pager->continueURL($start, $url);
            }


        }
        else {

            // ---------------------------------------------------------------
            // Paged results, cached
            // ---------------------------------------------------------------

            // Get nickname
            if (isset($_SESSION['nickname'])) $nn = $_SESSION['nickname'];
            else $nn = 'nobody';

            $this->pager->setStart(); // Start pager

            // "Cache Groups" using a vertical bar |
            $cache_id = "$nn|listing|$feeds_id|{$this->pager->start}";
            $this->tpl->caching = 1;

            if (!$this->tpl->isCached('scroll.tpl', $cache_id)) {

                if ($feeds_id || !(is_countable($subscriptions) ? count($subscriptions) : 0)) {
                    // Regular queries
                    $this->pager->setPages($this->rss->countItems($feeds_id));
                    $this->r->arr['feeds'] = $this->rss->getItems($this->pager->limit, $this->pager->start, $feeds_id);
                }
                else {
                    // User has subscriptions, we need special JOIN queries
                    $this->pager->setPages($this->countUserItems($_SESSION['users_id']));
                    $this->r->arr['feeds'] = $this->getUserItems($_SESSION['users_id'], $this->pager->limit, $this->pager->start);
                }

                $this->r->text['pager'] = $this->pager->pageList(suxFunct::makeUrl("/feeds/$feeds_id"));
                if (!(is_countable($this->r->arr['feeds']) ? count($this->r->arr['feeds']) : 0)) $this->tpl->caching = 0; // Nothing to cache, avoid writing to disk

            }

        }

        $this->tpl->display('scroll.tpl', $cache_id);

    }


    // -----------------------------------------------------------------------
    // Protected functions for $this->user() & this->listing()
    // -----------------------------------------------------------------------

    protected function countUserItems($users_id) {

        $db = suxDB::get();

        // Count
        $query = "
        SELECT COUNT(*) FROM rss_items
        INNER JOIN rss_feeds on rss_feeds.id = rss_items.rss_feeds_id
        INNER JOIN link__rss_feeds__users ON link__rss_feeds__users.rss_feeds_id = rss_feeds.id
        WHERE link__rss_feeds__users.users_id = ?
        ";
        $st = $db->prepare($query);
        $st->execute(array($users_id));
        return $st->fetchColumn();

    }


    protected function getUserItems($users_id, $limit, $start) {

        $db = suxDB::get();

        // Get Items
        $query = "
        SELECT rss_items.* FROM rss_items
        INNER JOIN rss_feeds on rss_feeds.id = rss_items.rss_feeds_id
        INNER JOIN link__rss_feeds__users ON link__rss_feeds__users.rss_feeds_id = rss_feeds.id
        WHERE link__rss_feeds__users.users_id = ?
        ORDER BY rss_items.published_on DESC, rss_items.id DESC
        LIMIT {$limit} OFFSET {$start}
        ";

        $st = $db->prepare($query);
        $st->execute(array($users_id));
        return $st->fetchAll(PDO::FETCH_ASSOC);

    }


}


