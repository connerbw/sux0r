<?php

/**
* blog
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.fsf.org/licensing/licenses/gpl-3.0.html
*/

class blog extends bayesComponent {

    // Module name
    protected $module = 'blog';

    // Object: suxThreadedMessages()
    protected $msg;

    // Var: used by filter() method
    public $tag_id;

    // Var: used by filter() method
    public $cat_id;



    /**
    * Constructor
    *
    */
    function __construct() {

        // Declare objects
        $this->nb = new suxUserNaiveBayesian();
        $this->msg = new suxThreadedMessages();
        $this->r = new blogRenderer($this->module); // Renderer
        parent::__construct(); // Let the parent do the rest

        // Declare properties
        $this->r->bool['analytics'] = true; // Turn on analytics

    }


    /**
    * Author
    */
    function author($author) {

        $this->r->text['form_url'] = suxFunct::makeUrl('/blog/author/' . $author); // Form Url
        $cache_id = null;

        $u = $this->user->getByNickname($author);
        if(!$u) suxFunct::redirect(suxFunct::makeUrl('/blog'));

        $this->r->title .= " | {$this->r->gtext['blog']} | $author";

        if ([$vec_id, $cat_id, $threshold, $start, $search] = $this->nb->isValidFilter()) {

            // ---------------------------------------------------------------
            // Filtered results
            // ---------------------------------------------------------------

            $max = $this->msg->countFirstPostsByUser($u['users_id'], 'blog');
            $eval = '$this->msg->getFirstPostsByUser(' .$u['users_id'] . ', $this->pager->limit, $start, \'blog\')';
            $this->r->arr['fp']  = $this->blogs($this->filter($max, $vec_id, $cat_id, $threshold, $start, $eval, $search)); // Important: $start is a reference

            if ($start < $max) {
                if ($threshold !== false) $params = array('threshold' => $threshold, 'filter' => $cat_id);
                else $params = array('filter' => $cat_id);
                $params['search'] = $search;
                $url = suxFunct::makeUrl('/blog/author/'. $author, $params);
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
            $cache_id = $nn . '|author|' . $author . '|' . $this->pager->start;
            $this->tpl->caching = 1;

            if (!$this->tpl->isCached('scroll.tpl', $cache_id)) {

                $this->pager->setPages($this->msg->countFirstPostsByUser($u['users_id'], 'blog'));
                $this->r->text['pager'] = $this->pager->pageList(suxFunct::makeUrl('/blog/author/' . $author));
                $this->r->arr['fp'] = $this->blogs($this->msg->getFirstPostsByUser($u['users_id'], $this->pager->limit, $this->pager->start, 'blog'));

                if (!count($this->r->arr['fp'])) $this->tpl->caching = 0; // Nothing to cache, avoid writing to disk

            }

        }

        // ---------------------------------------------------------------
        // Sidelist
        // ---------------------------------------------------------------

        if (!$this->tpl->isCached('scroll.tpl', $cache_id)) {

            $this->r->arr['sidelist'] = $this->msg->getFirstPostsByUser($u['users_id'], null, 0, 'blog'); // TODO: Too many blogs?
            $this->r->text['sidelist'] = ucwords($author);

        }

        $this->tpl->display('scroll.tpl', $cache_id);

    }


    /**
    * Tag
    */
    function tag($tag_id) {

        $this->r->text['form_url'] = suxFunct::makeUrl('/blog/tag/' . $tag_id); // Form Url
        $cache_id = null;

        $tag = $this->tags->getByID($tag_id);
        if (!$tag) suxFunct::redirect(suxFunct::makeUrl('/blog'));
        $this->tag_id = $tag_id; // Needs to be in externally accessible variable for filter()

        $count = $this->countTaggedItems($this->tag_id);

        $this->r->title .= " | {$this->r->gtext['blog']} | {$this->r->gtext['tag']} | {$tag['tag']}";

        if ([$vec_id, $cat_id, $threshold, $start, $search] = $this->nb->isValidFilter()) {

            // ---------------------------------------------------------------
            // Filtered results
            // ---------------------------------------------------------------

            $eval = '$this->getTaggedItems($this->tag_id, $this->pager->limit, $start)';
            $this->r->arr['fp']  = $this->blogs($this->filter($count, $vec_id, $cat_id, $threshold, $start, $eval, $search)); // Important: $start is a reference

            if ($start < $count) {
                if ($threshold !== false) $params = array('threshold' => $threshold, 'filter' => $cat_id);
                else $params = array('filter' => $cat_id);
                $params['search'] = $search;
                $url = suxFunct::makeUrl('/blog/tag/'. $this->tag_id, $params);
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
            $cache_id = $nn . '|tag|' . $this->tag_id . '|' . $this->pager->start;
            $this->tpl->caching = 1;

            if (!$this->tpl->isCached('scroll.tpl', $cache_id)) {

                $this->pager->setPages($count);
                $this->r->text['pager'] = $this->pager->pageList(suxFunct::makeUrl('/blog/tag/' . $this->tag_id));
                $this->r->arr['fp'] = $this->blogs($this->getTaggedItems($this->tag_id, $this->pager->limit, $this->pager->start));

                if (!count($this->r->arr['fp'])) $this->tpl->caching = 0; // Nothing to cache, avoid writing to disk

            }

        }

        // ---------------------------------------------------------------
        // Sidelist
        // ---------------------------------------------------------------

        if (!$this->tpl->isCached('scroll.tpl', $cache_id)) {

            $this->r->arr['sidelist'] = $this->getTaggedSidelist($this->tag_id);
            $this->r->text['sidelist'] = $tag['tag'];

        }

        $this->tpl->display('scroll.tpl', $cache_id);

    }


    /**
    * Tag cloud
    */
    function tagcloud() {

        // ---------------------------------------------------------------
        // Tagcloud, cached
        // ---------------------------------------------------------------

        // Get nickname
        if (isset($_SESSION['nickname'])) $nn = $_SESSION['nickname'];
        else $nn = 'nobody';

        $cache_id = "$nn|tagcloud";
        $this->tpl->caching = 1;

        if (!$this->tpl->isCached('cloud.tpl', $cache_id)) {

            $link = $this->link->buildTableName('messages', 'tags');
            $query = "
            SELECT tags.tag AS tag, tags.id AS id, COUNT(tags.id) AS quantity FROM tags
            INNER JOIN {$link} ON {$link}.tags_id = tags.id
            INNER JOIN messages ON {$link}.messages_id = messages.id
            WHERE messages.blog = true AND {$this->msg->sqlPublished()}
            GROUP BY tag, tags.id ORDER BY tag ASC
            ";
            $this->r->arr['tc'] = $this->tags->cloud($query);

            $this->r->title .= " | {$this->r->gtext['blog']} | {$this->r->gtext['tag_cloud']} ";

        }

        $this->tpl->display('cloud.tpl', $cache_id);

    }


    /**
    * Category
    */
    function category($cat_id) {

        $this->r->text['form_url'] = suxFunct::makeUrl('/blog/category/' . $cat_id); // Form Url
        $cache_id = null;

        $c = $this->nb->getCategory($cat_id);
        if (!$c) suxFunct::redirect(suxFunct::makeUrl('/blog'));
        $this->cat_id = $cat_id; // Needs to be in externally accessible variable for filter()

        $count = $this->countCategorizedItems($this->cat_id);

        $this->r->title .= " | {$this->r->gtext['blog']}  | {$this->r->gtext['category']} | {$c['category']}";

        if ([$vec_id, $cat_id2, $threshold, $start, $search] = $this->nb->isValidFilter()) {

            // ---------------------------------------------------------------
            // Filtered results
            // ---------------------------------------------------------------

            $eval = '$this->getCategorizedItems($this->cat_id, $this->pager->limit, $start)';
            $this->r->arr['fp']  = $this->blogs($this->filter($count, $vec_id, $cat_id2, $threshold, $start, $eval, $search)); // Important: $start is a reference

            if ($start < $count) {
                if ($threshold !== false) $params = array('threshold' => $threshold, 'filter' => $cat_id2);
                else $params = array('filter' => $cat_id2);
                $params['search'] = $search;
                $url = suxFunct::makeUrl('/blog/category/'. $this->cat_id, $params);
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
            $cache_id = $nn . '|category|' . $this->cat_id . '|' . $this->pager->start;
            $this->tpl->caching = 1;

            if (!$this->tpl->isCached('scroll.tpl', $cache_id)) {

                $this->pager->setPages($count);
                $this->r->text['pager'] = $this->pager->pageList(suxFunct::makeUrl('/blog/category/' . $this->cat_id));
                $this->r->arr['fp'] = $this->blogs($this->getCategorizedItems($this->cat_id, $this->pager->limit, $this->pager->start));

                if (!count($this->r->arr['fp'])) $this->tpl->caching = 0; // Nothing to cache, avoid writing to disk

            }
        }

        // ---------------------------------------------------------------
        // Sidelist
        // ---------------------------------------------------------------

        if (!$this->tpl->isCached('scroll.tpl', $cache_id)) {

            $this->r->arr['sidelist'] = $this->getCategorizedSidelist($this->cat_id);
            $this->r->text['sidelist'] = $c['category'];

        }


        $this->tpl->display('scroll.tpl', $cache_id);

    }


    /**
    * Month
    */
    function month($date) {

        // Sanity check, YYYY-MM-DD
        $matches = array();
        $regex = '/^(\d{4})-(0[0-9]|1[0,1,2])-([0,1,2][0-9]|3[0,1])$/';
        if (!preg_match($regex, (string) $date)) $date = date('Y-m-d');
        $datetime = $date . ' ' . date('H:i:s'); // Append current time

        $this->r->text['form_url'] = suxFunct::makeUrl('/blog/month/' . $date); // Form Url
        $cache_id = null;

        $this->r->title .= " | {$this->r->gtext['blog']}  | " .  date('F Y', strtotime((string) $date));

        if ([$vec_id, $cat_id, $threshold, $start, $search] = $this->nb->isValidFilter()) {

            // ---------------------------------------------------------------
            // Filtered results
            // ---------------------------------------------------------------

            $max = $this->msg->countFirstPostsByMonth($datetime, 'blog');
            $eval = '$this->msg->getFirstPostsByMonth(\'' . $datetime . '\', $this->pager->limit, $start, \'blog\')';
            $this->r->arr['fp']  = $this->blogs($this->filter($max, $vec_id, $cat_id, $threshold, $start, $eval, $search)); // Important: $start is a reference

            if ($start < $max) {
                if ($threshold !== false) $params = array('threshold' => $threshold, 'filter' => $cat_id);
                else $params = array('filter' => $cat_id);
                $params['search'] = $search;
                $url = suxFunct::makeUrl('/blog/month/'. $date, $params);
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
            $cache_id = $nn . '|month|' . date('Y-m', strtotime((string) $date)) . '|' . $this->pager->start;
            $this->tpl->caching = 1;

            if (!$this->tpl->isCached('scroll.tpl', $cache_id)) {

                $this->pager->setPages($this->msg->countFirstPostsByMonth($datetime, 'blog'));
                $this->r->text['pager'] = $this->pager->pageList(suxFunct::makeUrl('/blog/month/' . $date));
                $this->r->arr['fp'] = $this->blogs($this->msg->getFirstPostsByMonth($datetime, $this->pager->limit, $this->pager->start, 'blog'));

                if (!count($this->r->arr['fp'])) $this->tpl->caching = 0; // Nothing to cache, avoid writing to disk

            }

        }

        // ---------------------------------------------------------------
        // Sidelist
        // ---------------------------------------------------------------

        if (!$this->tpl->isCached('scroll.tpl', $cache_id)) {

            $this->r->arr['sidelist'] = $this->msg->getFirstPostsByMonth($datetime, null, 0, 'blog');
            $this->r->text['sidelist'] = date('F Y', strtotime((string) $date));

        }

        $this->tpl->display('scroll.tpl', $cache_id);

    }


    /**
    * Listing
    */
    function listing() {

        $this->r->text['form_url'] = suxFunct::makeUrl('/blog'); // Form Url
        $cache_id = null;

        $this->r->title .= " | {$this->r->gtext['blog']}";

        if ([$vec_id, $cat_id, $threshold, $start, $search] = $this->nb->isValidFilter()) {

            // ---------------------------------------------------------------
            // Filtered results
            // ---------------------------------------------------------------

            $max = $this->msg->countFirstPosts('blog');
            $eval = '$this->msg->getFirstPosts($this->pager->limit, $start, \'blog\')';
            $this->r->arr['fp']  = $this->blogs($this->filter($max, $vec_id, $cat_id, $threshold, $start, $eval, $search)); // Important: $start is a reference

            if ($start < $max) {
                if ($threshold !== false) $params = array('threshold' => $threshold, 'filter' => $cat_id);
                else $params = array('filter' => $cat_id);
                $params['search'] = $search;
                $url = suxFunct::makeUrl('/blog/', $params);
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
            $cache_id = $nn . '|listing|' . $this->pager->start;
            $this->tpl->caching = 1;

            if (!$this->tpl->isCached('scroll.tpl', $cache_id)) {

                $this->pager->setPages($this->msg->countFirstPosts('blog'));
                $this->r->text['pager'] = $this->pager->pageList(suxFunct::makeUrl('/blog'));
                $this->r->arr['fp'] = $this->blogs($this->msg->getFirstPosts($this->pager->limit, $this->pager->start, 'blog'));

                if (!count($this->r->arr['fp'])) $this->tpl->caching = 0; // Nothing to cache, avoid writing to disk

            }

        }

        $this->tpl->display('scroll.tpl', $cache_id);


    }


    /**
    * View
    */
    function view($thread_id) {

        $fp = [];
        // Get nickname
        if (isset($_SESSION['nickname'])) $nn = $_SESSION['nickname'];
        else $nn = 'nobody';

        // Start pager
        $this->pager->limit = 100;
        $this->pager->setStart();

        // "Cache Groups" using a vertical bar |
        $cache_id = $nn . "|{$thread_id}|" . $this->pager->start;
        $this->tpl->caching = 1;

        if (!$this->tpl->isCached('view.tpl', $cache_id)) {

            $fp[] = $this->msg->getFirstPost($thread_id);

            if ($fp[0] === false) {
                // This is not a blog post, redirect
                suxFunct::redirect(suxFunct::makeUrl('/blog'));
            }

            $this->r->title .= " | {$this->r->gtext['blog']} | {$fp[0]['title']}";

            $this->pager->setPages($this->msg->countThread($thread_id, 'blog'));
            $this->r->text['pager'] = $this->pager->pageList(suxFunct::makeUrl('/blog/view/' . $thread_id));

            if ($this->pager->start == 0) {
                $thread = $this->msg->getThread($this->pager->limit, $this->pager->start, $thread_id, 'blog');
                unset($fp);
                $fp[] = array_shift($thread);
            }
            else {
                $thread = $this->msg->getThread($this->pager->limit, $this->pager->start, $thread_id, 'blog');
            }

            // Assign
            $this->r->arr['fp'] = $this->blogs($fp);
            $this->r->arr['comments'] = $this->comments($thread);

        }

        $this->tpl->display('view.tpl', $cache_id);

    }


    /**
    * Display RSS Feed
    */
    function rss() {

        // Cache
        $cache_id = 'rss';
        $this->tpl->caching = 1;

        if (!$this->tpl->isCached('rss.tpl', $cache_id)) {

            $fp = $this->blogs($this->msg->getFirstPosts($this->pager->limit, 0, 'blog'));
            if ($fp) {                
                $rss = new suxRSS();
                $title = "{$this->r->title} | {$this->r->gtext['blog']}";
                $url = suxFunct::makeUrl('/blog', null, true);
                $rss->outputRSS($title, $url, null);

                foreach($fp as $item) {
                    $url = suxFunct::makeUrl('/blog/view/' . $item['thread_id'], null, true);
                    $rss->addOutputItem($item['title'], $url, $item['body_html']);
                }

                $this->tpl->assign('xml', $rss->saveXML());
            }

        }

        // Template
        header('Content-type: text/xml; charset=utf-8');
        $this->tpl->display('rss.tpl', $cache_id);

    }




    /**
    * @param array threaded messages
    * @return array
    */
    private function blogs($msgs) {

        foreach($msgs as &$val) {
            $val['comments'] = $this->msg->getCommentsCount($val['thread_id']);
            $user = $this->user->getByID($val['users_id']);
            $val['nickname'] = $user['nickname'];
        }
        return $msgs;

    }


    /**
    * @param array threaded messages
    * @return array
    */
    private function comments($msgs) {

        foreach($msgs as &$val) {
            $user = $this->user->getByID($val['users_id']);
            $val['nickname'] = $user['nickname'];
        }
        return $msgs;

    }


    // -----------------------------------------------------------------------
    // Protected functions for $this->tag()
    // -----------------------------------------------------------------------

    protected function countTaggedItems($id) {

        $db = suxDB::get();

        // Count
        $count_query = "
        SELECT COUNT(*) FROM messages
        INNER JOIN link__messages__tags ON link__messages__tags.messages_id = messages.id
        WHERE link__messages__tags.tags_id = ? AND messages.thread_pos = 0 AND messages.blog = true AND {$this->msg->sqlPublished()}
        ";

        $st = $db->prepare($count_query);
        $st->execute(array($id));
        return $st->fetchColumn();

    }


    protected function getTaggedItems($id, $limit, $start) {

        $db = suxDB::get();

        // Get Items
        $query = "
        SELECT messages.* FROM messages
        INNER JOIN link__messages__tags ON link__messages__tags.messages_id = messages.id
        WHERE link__messages__tags.tags_id = ? AND messages.thread_pos = 0 AND messages.blog = true AND {$this->msg->sqlPublished()}
        ORDER BY {$this->msg->sqlOrder()}
        LIMIT {$limit} OFFSET {$start}
        ";

        $st = $db->prepare($query);
        $st->execute(array($id));
        return $st->fetchAll(PDO::FETCH_ASSOC);

    }


    protected function getTaggedSidelist($id) {

        $db = suxDB::get();

        // Get Items
        $query = "
        SELECT messages.id, messages.thread_id, messages.title FROM messages
        INNER JOIN link__messages__tags ON link__messages__tags.messages_id = messages.id
        WHERE link__messages__tags.tags_id = ? AND messages.thread_pos = 0 AND messages.blog = true AND {$this->msg->sqlPublished()}
        ORDER BY {$this->msg->sqlOrder()}
        ";

        $st = $db->prepare($query);
        $st->execute(array($id));
        return $st->fetchAll(PDO::FETCH_ASSOC);

    }


    // -----------------------------------------------------------------------
    // Protected functions for $this->category()
    // -----------------------------------------------------------------------


    protected function countCategorizedItems($id) {

        $db = suxDB::get();

        // Count
        $count_query = "
        SELECT COUNT(*) FROM messages
        INNER JOIN link__bayes_documents__messages ON link__bayes_documents__messages.messages_id = messages.id
        INNER JOIN bayes_documents ON link__bayes_documents__messages.bayes_documents_id = bayes_documents.id
        INNER JOIN bayes_categories ON bayes_documents.bayes_categories_id = bayes_categories.id
        WHERE bayes_categories.id = ? AND messages.thread_pos = 0 AND messages.blog = true AND {$this->msg->sqlPublished()}
        ";
        $st = $db->prepare($count_query);
        $st->execute(array($id));
        return $st->fetchColumn();

    }


    protected function getCategorizedItems($id, $limit, $start) {

        $db = suxDB::get();

        // Get Items
        $query = "
        SELECT messages.* FROM messages
        INNER JOIN link__bayes_documents__messages ON link__bayes_documents__messages.messages_id = messages.id
        INNER JOIN bayes_documents ON link__bayes_documents__messages.bayes_documents_id = bayes_documents.id
        INNER JOIN bayes_categories ON bayes_documents.bayes_categories_id = bayes_categories.id
        WHERE bayes_categories.id = ? AND messages.thread_pos = 0 AND messages.blog = true AND {$this->msg->sqlPublished()}
        ORDER BY {$this->msg->sqlOrder()}
        LIMIT {$limit} OFFSET {$start}
        ";

        $st = $db->prepare($query);
        $st->execute(array($id));
        return $st->fetchAll(PDO::FETCH_ASSOC);

    }


    protected function getCategorizedSidelist($id) {

        $db = suxDB::get();

        // Get Items
        $query = "
        SELECT messages.id, messages.thread_id, messages.title FROM messages
        INNER JOIN link__bayes_documents__messages ON link__bayes_documents__messages.messages_id = messages.id
        INNER JOIN bayes_documents ON link__bayes_documents__messages.bayes_documents_id = bayes_documents.id
        INNER JOIN bayes_categories ON bayes_documents.bayes_categories_id = bayes_categories.id
        WHERE bayes_categories.id = ? AND messages.thread_pos = 0 AND messages.blog = true AND {$this->msg->sqlPublished()}
        ORDER BY {$this->msg->sqlOrder()}
        ";

        $st = $db->prepare($query);
        $st->execute(array($id));
        return $st->fetchAll(PDO::FETCH_ASSOC);

    }


}


