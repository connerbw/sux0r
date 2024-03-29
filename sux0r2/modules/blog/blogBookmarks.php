<?php

/**
* blogBookmarks
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.fsf.org/licensing/licenses/gpl-3.0.html
*/

class blogBookmarks extends component {

    // Module name
    protected $module = 'blog';

    // Form name
    protected $form_name = 'blogBookmarks';

    // Object: suxThreadedMessages()
    protected $msg;


    // Object: suxBookmarks()
    protected $bookmarks;


    /**
    * Constructor
    *
    * @param string $key PDO dsn key
    */
    function __construct(private $id) {

        // Declare objects
        $this->msg = new suxThreadedMessages();
        $this->bookmarks = new suxBookmarks();
        $this->r = new blogRenderer($this->module); // Renderer
        (new suxValidate())->register_object('this', $this); // Register self to validator
        parent::__construct();
        $this->msg->setPublished(null);
        $this->bookmarks->setPublished(null);

        // If feature is turned off, then redirect
        if ($GLOBALS['CONFIG']['FEATURE']['auto_bookmark'] == false) suxFunct::redirect(suxFunct::getPreviousURL());

        // Redirect if not logged in
        if (empty($_SESSION['users_id'])) suxFunct::redirect(suxFunct::makeUrl('/user/register'));

        // --------------------------------------------------------------------
        // Scan post for href links
        // --------------------------------------------------------------------

        $msg = $this->msg->getByID($id);

        if (!$msg)
            suxFunct::redirect(suxFunct::getPreviousURL()); // No message, skip

        if ($msg['users_id'] != $_SESSION['users_id'])
            suxFunct::redirect(suxFunct::getPreviousURL()); // Not the user's message, skip

        $matches = array();
        $pattern = '#<a[\s]+[^>]*?href[\s]?=[\s"\']+(.*?)["\']+.*?>([^<]+|.*?)?</a>#si'; // href pattern
        preg_match_all($pattern, (string) $msg['body_html'], $matches);

        $count = is_countable($matches[1]) ? count($matches[1]) : 0;
        if (!$count) suxFunct::redirect(suxFunct::getPreviousURL()); //  No links, skip

        // Limit the amount of time we wait for a connection to a remote server to 5 seconds
        ini_set('default_socket_timeout', 5);
        for ($i = 0; $i < $count; ++$i) {
            if (mb_substr($matches[1][$i], 0, 7) == 'http://' || mb_substr($matches[1][$i], 0, 8) == 'https://') {

                // Basic info
                $url = suxFunct::canonicalizeUrl($matches[1][$i]);

                if (!filter_var($url, FILTER_VALIDATE_URL) || $this->bookmarks->getByID($url))
                    continue; // skip it

                $title = strip_tags($matches[2][$i]);
                $body = null;

                if (!$this->r->detectPOST()) {
                    $tmp = $this->bookmarks->fetchUrlInfo($url);
                    if ($tmp) {
                        $title = $tmp['title'];
                        $body = $tmp['description'];
                    }
                }

                // Add to array for use in template
                $this->arr['found_links'][$url] = array('title' => $title, 'body' => $body);

            }
        }

        $count = is_countable(@$this->arr['found_links']) ? count(@$this->arr['found_links']) : 0;
        if (!$count) suxFunct::redirect(suxFunct::getPreviousURL()); //  No links, skip

    }


    /**
    * Validate the form
    *
    * @param array $dirty reference to unverified $_POST
    * @return bool
    */
    function formValidate(&$dirty) {
        return suxValidate::formValidate($dirty, $this->tpl);
    }


    /**
    * Build the form and show the template
    *
    * @param array $dirty reference to unverified $_POST
    */
    function formBuild(&$dirty) {

        // --------------------------------------------------------------------
        // Replace what we have with what the user submitted.
        // --------------------------------------------------------------------

        $count = 0;
        if (isset($dirty['url']) && is_array($dirty['url'])) {
            $count = is_countable($this->arr['found_links']) ? count($this->arr['found_links']) : 0; // Original count
            $this->arr['found_links'] = array(); // Clear array
            for ($i = 0; $i < $count; ++$i) {
                if (!empty($dirty['url'][$i]) && !isset($this->arr['found_links'][$dirty['url'][$i]])) {
                    $this->arr['found_links'][$dirty['url'][$i]] = array('title' => $dirty['title'][$i], 'body' => $dirty['body'][$i]);
                }
                else {
                    $title = $dirty['title'][$i] ?? null;
                    $body = $dirty['body'][$i] ?? null;
                    $this->arr['found_links'][] = array('title' => $title, 'body' => $body);
                }
            }
        }

        // --------------------------------------------------------------------
        // Form logic
        // --------------------------------------------------------------------

        if (!empty($dirty)) $this->tpl->assign($dirty);
        else (new suxValidate())->disconnect();

        if (!(new suxValidate())->is_registered_form()) {

            (new suxValidate())->connect($this->tpl, true); // Reset connection

            // Register our validators
            $count = is_countable($this->arr['found_links']) ? count($this->arr['found_links']) : 0;
            for ($i = 0; $i < $count; ++$i) {
                (new suxValidate())->register_validator("url[$i]", "url[$i]", 'notEmpty', false, false, 'trim');
                (new suxValidate())->register_validator("url2[$i]", "url[$i]", 'isURL');
                (new suxValidate())->register_validator("title[$i]", "title[$i]", 'notEmpty', false, false, 'trim');
                (new suxValidate())->register_validator("body[$i]", "body[$i]", 'notEmpty', false, false, 'trim');
            }

        }

        // Additional variables
        $this->r->text['form_url'] = suxFunct::makeUrl('/blog/bookmarks/' . $this->id);
        $this->r->text['back_url'] = suxFunct::getPreviousURL();

        $this->r->title .= " | {$this->r->gtext['suggest_bookmarks']}  ";

        // Template
        $this->r->arr['found_links'] = $this->arr['found_links'];
        $this->tpl->display('bookmarks.tpl');

    }



    /**
    * Process the form
    *
    * @param array $clean reference to validated $_POST
    */
    function formProcess(&$clean) {

        if (isset($clean['url']) && is_array($clean['url'])) {
            $count = count($clean['url']);
            for ($i = 0; $i < $count; ++$i) {
                $bookmark = array();
                if (!$this->bookmarks->getByID($clean['url'][$i])) {
                    $bookmark['url'] = $clean['url'][$i];
                    $bookmark['title'] = $clean['title'][$i];
                    $bookmark['body'] = $clean['body'][$i];
                    $bookmark['draft'] = true; // Admin approves bookmarks, like dmoz.org
                    $this->bookmarks->save($_SESSION['users_id'], $bookmark);
                }
            }
        }

        $this->log->write($_SESSION['users_id'], "sux0r::blogBookmarks()", 1); // Private

    }


    /**
    * The form was successfuly processed
    */
    function formSuccess() {

        // TODO: Same message as suggest bookmark from bookmarks module

        suxFunct::redirect(suxFunct::getPreviousURL()); // Redirect

    }

}


