<?php

/**
* bookmarksEdit
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.fsf.org/licensing/licenses/gpl-3.0.html
*/

class bookmarksEdit extends component {

    // Module name
    protected $module = 'bookmarks';

    // Form name
    protected $form_name = 'bookmarksEdit';

    // Object: suxBookmarks()
    protected $bm;

    // Var
    private $id;

    // Var
    private $prev_skip;


    /**
    * Constructor
    *
    * @param int $id message id
    */
    function __construct($id = null) {

        // Declare objects
        $this->bm = new suxBookmarks();
        $this->r = new bookmarksRenderer($this->module); // Renderer
        (new suxValidate())->register_object('this', $this); // Register self to validator
        parent::__construct(); // Let the parent do the rest

        // Declare properties
        $this->bm->setPublished(null);

        if ($id) {
            if (!filter_var($id, FILTER_VALIDATE_INT) || $id < 1)
                suxFunct::redirect(suxFunct::makeURL('/bookmarks')); // Invalid id
        }

        // Redirect if not logged in
        if (empty($_SESSION['users_id'])) suxFunct::redirect(suxFunct::makeUrl('/user/register'));

        // Check that the user is allowed to be here
        if (!$this->user->isRoot()) {
            $access = $this->user->getAccess($this->module);
            if ($access < $GLOBALS['CONFIG']['ACCESS'][$this->module]['admin'])
                suxFunct::redirect(suxFunct::makeUrl('/bookmarks'));
        }

        // This module can fallback on approve module
        foreach ($GLOBALS['CONFIG']['PREV_SKIP'] as $val) {
            if (mb_strpos($val, 'bookmarks/approve') === false)
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
        $bookmark = array();

        if ($this->id) {

            // Editing a bookmark post
            $tmp = $this->bm->getByID($this->id);

            $bookmark['id'] = $tmp['id'];
            $bookmark['title'] = $tmp['title'];
            $bookmark['url'] = $tmp['url'];
            $bookmark['body'] = htmlentities($tmp['body_html'], ENT_QUOTES, 'UTF-8'); // Textarea fix
            $bookmark['draft'] = $tmp['draft'];

            // Get publish date
            // regex must match '2008-06-18 16:53:29' or '2008-06-18T16:53:29-04:00'
            $matches = array();
            $regex = '/^(\d{4})-(0[0-9]|1[0,1,2])-([0,1,2][0-9]|3[0,1]).+(\d{2}):(\d{2}):(\d{2})/';
            preg_match($regex, (string) $tmp['published_on'], $matches);
            $bookmark['Date_Year'] = @$matches[1]; // year
            $bookmark['Date_Month'] = @$matches[2]; // month
            $bookmark['Date_Day'] = @$matches[3]; // day
            $bookmark['Time_Hour']  = @$matches[4]; // hour
            $bookmark['Time_Minute']  = @$matches[5]; // minutes
            $bookmark['Time_Second'] = @$matches[6]; //seconds

            /* Tags */

            $links = $this->link->getLinks('link__bookmarks__tags', 'bookmarks', $bookmark['id']);
            $bookmark['tags'] = '';
            foreach($links as $val) {
                $tmp = $this->tags->getByID($val);
                $bookmark['tags'] .=  $tmp['tag'] . ', ';
            }
            $bookmark['tags'] = rtrim($bookmark['tags'], ', ');




        }

        // Assign bookmark
        // new dBug($bookmark);
        $this->tpl->assign($bookmark);

        // --------------------------------------------------------------------
        // Form logic
        // --------------------------------------------------------------------

        if (!empty($dirty)) $this->tpl->assign($dirty);
        else (new suxValidate())->disconnect();

        if (!(new suxValidate())->is_registered_form()) {

            (new suxValidate())->connect($this->tpl, true); // Reset connection

            // Register our additional criterias
            (new suxValidate())->register_criteria('isDuplicateBookmark', 'this->isDuplicateBookmark');
            (new suxValidate())->register_criteria('isValidBookmark', 'this->isValidBookmark');

            // Register our validators
            if ($this->id) (new suxValidate())->register_validator('integrity', 'integrity:id', 'hasIntegrity');

            (new suxValidate())->register_validator('url', 'url', 'notEmpty', false, false, 'trim');
            (new suxValidate())->register_validator('url2', 'url', 'isURL');
            (new suxValidate())->register_validator('url3', 'url', 'isDuplicateBookmark');
            (new suxValidate())->register_validator('url4', 'url', 'isValidBookmark');
            (new suxValidate())->register_validator('title', 'title', 'notEmpty', false, false, 'trim');
            (new suxValidate())->register_validator('body', 'body', 'notEmpty', false, false, 'trim');
            (new suxValidate())->register_validator('date', 'Date:Date_Year:Date_Month:Date_Day', 'isDate', false, false, 'makeDate');
            (new suxValidate())->register_validator('time', 'Time_Hour', 'isInt');
            (new suxValidate())->register_validator('time2', 'Time_Minute', 'isInt');
            (new suxValidate())->register_validator('time3', 'Time_Second', 'isInt');


        }

        // Additional variables
        $this->r->text['form_url'] = suxFunct::makeUrl('/bookmarks/edit/' . $this->id);
        $this->r->text['back_url'] = suxFunct::getPreviousURL($this->prev_skip);

        if (!$this->tpl->getTemplateVars('Date_Year')) {
            // Today's Date
            $this->tpl->assign('Date_Year', date('Y'));
            $this->tpl->assign('Date_Month', date('m'));
            $this->tpl->assign('Date_Day', date('j'));
        }

        if (!$this->tpl->getTemplateVars('Time_Hour')) {
            // Current Time
            $this->tpl->assign('Time_Hour', date('H'));
            $this->tpl->assign('Time_Minute', date('i'));
            $this->tpl->assign('Time_Second', date('s'));
        }

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

        // --------------------------------------------------------------------
        // Sanity check
        // --------------------------------------------------------------------

        // Date
        $clean['published_on'] = "{$clean['Date']} {$clean['Time_Hour']}:{$clean['Time_Minute']}:{$clean['Time_Second']}";
        $clean['published_on'] = date('Y-m-d H:i:s', strtotime($clean['published_on'])); // Sanitize

        // Draft
        $clean['draft'] = (isset($clean['draft']) && $clean['draft']) ? true : false;

        // --------------------------------------------------------------------
        // Create $bookmark array
        // --------------------------------------------------------------------

        $bookmark = array(
                'url' => $clean['url'],
                'title' => $clean['title'],
                'body' => $clean['body'],
                'published_on' => $clean['published_on'],
                'draft' => $clean['draft'],
            );

        // --------------------------------------------------------------------
        // Id
        // --------------------------------------------------------------------

        if (isset($clean['id']) && filter_var($clean['id'], FILTER_VALIDATE_INT) && $clean['id'] > 0) {
            $bookmark['id'] = $clean['id'];
        }

        // --------------------------------------------------------------------
        // Put $bookmark in database
        // --------------------------------------------------------------------

        $clean['id'] = $this->bm->save($_SESSION['users_id'], $bookmark);

        // --------------------------------------------------------------------
        // Tags procedure
        // --------------------------------------------------------------------

        // Parse tags
        $tags = suxTags::parse($clean['tags']);

        // Save tags into database
        $tag_ids = array();
        foreach($tags as $tag) {
            $tag_ids[] = $this->tags->save($_SESSION['users_id'], $tag);
        }

        //Delete current links
        $this->link->deleteLink('link__bookmarks__tags', 'bookmarks', $clean['id']);

        // Reconnect links
        foreach ($tag_ids as $id) {
            $this->link->saveLink('link__bookmarks__tags', 'bookmarks', $clean['id'], 'tags', $id);
        }

        $this->log->write($_SESSION['users_id'], "sux0r::bookmarksEdit() bookmarks_id: {$clean['id']}", 1); // Private


    }


    /**
    * The form was successfuly processed
    */
    function formSuccess() {

        // clear all caches with "nickname" as the first cache_id group
        $this->tpl->clearCache(null, "{$_SESSION['nickname']}");

        suxFunct::redirect(suxFunct::getPreviousURL($this->prev_skip));

    }


    /**
    * for suxValidate, check if a duplicate url exists
    *
    * @return bool
    */
    function isDuplicateBookmark($value, $empty, &$params, &$formvars) {

        if (empty($formvars['url'])) return false;

        $tmp = $this->bm->getByID($formvars['url']);
        if ($tmp === false ) return true; // No duplicate found

        if ($this->id) {
            // This is an Bookmark editing itself, this is OK
            if ($tmp['id'] == $this->id) return true;
        }

        return false;

    }


    /**
    * for suxValidate, check if a bookmark is valid
    *
    * @return bool
    */
    function isValidBookmark($value, $empty, &$params, &$formvars) {

        if (empty($formvars['url'])) return false;
        $bm = $this->bm->fetchUrlInfo($formvars['url']);
        if (!$bm) return false;
        return true;

    }



}


