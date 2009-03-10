<?php

/**
* feedsEdit
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.fsf.org/licensing/licenses/gpl-3.0.html
*/

require_once(dirname(__FILE__) . '/../../includes/suxLink.php');
require_once(dirname(__FILE__) . '/../../includes/suxTemplate.php');
require_once(dirname(__FILE__) . '/../../includes/suxRSS.php');
require_once(dirname(__FILE__) . '/../../includes/suxValidate.php');
require_once('feedsRenderer.php');

class feedsEdit {

    // Variables
    public $gtext = array();
    private $id;
    private $prev_skip;
    private $module = 'feeds';

    // Objects
    public $tpl;
    public $r;
    private $user;
    private $rss;


    /**
    * Constructor
    *
    * @param int $id message id
    */
    function __construct($id = null) {

        if ($id) {
            if (!filter_var($id, FILTER_VALIDATE_INT) || $id < 1)
                suxFunct::redirect(suxFunct::makeURL('/feeds')); // Invalid id
        }

        $this->tpl = new suxTemplate($this->module); // Template
        $this->r = new feedsRenderer($this->module); // Renderer
        $this->tpl->assign_by_ref('r', $this->r); // Renderer referenced in template
        suxValidate::register_object('this', $this); // Register self to validator

        // Objects
        $this->user = new suxUser();
        $this->rss = new suxRSS();

        // Redirect if not logged in
        if (empty($_SESSION['users_id'])) suxFunct::redirect(suxFunct::makeUrl('/user/register'));

        // Security check
        if (!$this->user->isRoot()) {
            $access = $this->user->getAccess($this->module);
            if ($access < $GLOBALS['CONFIG']['ACCESS'][$this->module]['admin'])
                suxFunct::redirect(suxFunct::makeUrl('/feeds'));
        }

        // This module can fallback on approve module
        foreach ($GLOBALS['CONFIG']['PREV_SKIP'] as $val) {
            if (mb_strpos($val, 'feeds/approve') === false)
                $this->prev_skip[] = $val;
        }

        // Assign id:
        $this->id = $id;

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

        unset($dirty['id']); // Don't allow spoofing
        $feed = array();

        if ($this->id) {

            // Editing a feed

            $tmp = $this->rss->getFeed($this->id, true);

            $feed['id'] = $tmp['id'];
            $feed['title'] = $tmp['title'];
            $feed['url'] = $tmp['url'];
            $feed['body'] = htmlentities($tmp['body_html'], ENT_QUOTES, 'UTF-8'); // Textarea fix
            $feed['draft'] = $tmp['draft'];

        }

        // Assign feed
        // new dBug($feed);
        $this->tpl->assign($feed);

        // --------------------------------------------------------------------
        // Form logic
        // --------------------------------------------------------------------

        if (!empty($dirty)) $this->tpl->assign($dirty);
        else suxValidate::disconnect();

        if (!suxValidate::is_registered_form()) {

            suxValidate::connect($this->tpl, true); // Reset connection

            // Register our additional criterias
            suxValidate::register_criteria('isDuplicateFeed', 'this->isDuplicateFeed');
            suxValidate::register_criteria('isValidFeed', 'this->isValidFeed');

            // Register our validators
            if ($this->id) suxValidate::register_validator('integrity', 'integrity:id', 'hasIntegrity');

            suxValidate::register_validator('url', 'url', 'notEmpty', false, false, 'trim');
            suxValidate::register_validator('url2', 'url', 'isURL');
            suxValidate::register_validator('url3', 'url', 'isDuplicateFeed');
            suxValidate::register_validator('url4', 'url', 'isValidFeed');

            suxValidate::register_validator('title', 'title', 'notEmpty', false, false, 'trim');
            suxValidate::register_validator('body', 'body', 'notEmpty', false, false, 'trim');


        }

        // Additional variables
        $this->r->text['form_url'] = suxFunct::makeUrl('/feeds/edit/' . $this->id);
        $this->r->text['back_url'] = suxFunct::getPreviousURL($this->prev_skip);

        $this->r->title .= " | {$this->r->gtext['edit_2']}";

        // Template
        $this->tpl->display('edit.tpl');

    }



    /**
    * Process the form
    *
    * @param array $clean reference to validated $_POST
    */
    function formProcess(&$clean) {

        // Draft
        $clean['draft'] = isset($clean['draft']) ? true: false;

        // --------------------------------------------------------------------
        // Create $feed array
        // --------------------------------------------------------------------

        $feed = array(
                'url' => $clean['url'],
                'title' => $clean['title'],
                'body' => $clean['body'],
                'draft' => $clean['draft'],
            );

        // --------------------------------------------------------------------
        // Id
        // --------------------------------------------------------------------

        if (isset($clean['id']) && filter_var($clean['id'], FILTER_VALIDATE_INT) && $clean['id'] > 0) {
            $feed['id'] = $clean['id'];
        }

        // --------------------------------------------------------------------
        // Put $feed in database
        // --------------------------------------------------------------------

        $id = $this->rss->saveFeed($_SESSION['users_id'], $feed);

        $this->user->log("sux0r::feedsEdit() feeds_id: {$id}", $_SESSION['users_id'], 1); // Private

        // clear all caches, cheap and easy
        $this->tpl->clear_all_cache();


    }


    /**
    * The form was successfuly processed
    */
    function formSuccess() {

        // Redirect
        suxFunct::redirect(suxFunct::getPreviousURL($this->prev_skip));

    }


    /**
    * for suxValidate, check if a duplicate url exists
    *
    * @return bool
    */
    function isDuplicateFeed($value, $empty, &$params, &$formvars) {

        if (empty($formvars['url'])) return false;

        $tmp = $this->rss->getFeed($formvars['url']);
        if ($tmp === false ) return true; // No duplicate found

        if ($this->id) {
            // This is an RSS editing itself, this is OK
            if ($tmp['id'] == $this->id) return true;
        }

        return false;

    }


    /**
    * for suxValidate, check if a RSS feed is valid
    *
    * @return bool
    */
    function isValidFeed($value, $empty, &$params, &$formvars) {

        if (empty($formvars['url'])) return false;
        $feed = $this->rss->fetchRSS($formvars['url']);
        if (!isset($feed['items_count']) || $feed['items_count'] < 1) return false;
        return true;

    }



}


?>