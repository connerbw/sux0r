<?php

/**
* feedsPurge
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.fsf.org/licensing/licenses/gpl-3.0.html
*/

class feedsPurge extends component  {

    // Module name
    protected $module = 'feeds';

    // Form name
    protected $form_name = 'feedsPurge';

    // Object: suxRss()
    protected $rss;


    /**
    * Constructor
    *
    */
    function __construct() {

        // Declare objects
        $this->rss = new suxRSS();
        $this->r = new feedsRenderer($this->module); // Renderer
        (new suxValidate())->register_object('this', $this); // Register self to validator
        parent::__construct(); // Let the parent do the rest


        // Redirect if not logged in
        if (empty($_SESSION['users_id'])) suxFunct::redirect(suxFunct::makeUrl('/user/register'));

        // Security check
        if (!$this->user->isRoot()) {
            $access = $this->user->getAccess($this->module);
            if ($access < $GLOBALS['CONFIG']['ACCESS'][$this->module]['admin'])
                suxFunct::redirect(suxFunct::makeUrl('/home'));
        }

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

        if (!empty($dirty)) $this->tpl->assign($dirty);
        else (new suxValidate())->disconnect();

        if (!(new suxValidate())->is_registered_form()) {

            (new suxValidate())->connect($this->tpl, true); // Reset connection

            // Register our validators
            // register_validator($id, $field, $criteria, $empty = false, $halt = false, $transform = null, $form = 'default')

            (new suxValidate())->register_validator('date', 'Date:Date_Year:Date_Month:Date_Day', 'isDate', false, false, 'makeDate');

        }

        if (!$this->tpl->getTemplateVars('Date_Year')) {
            // Today's Date
            $this->tpl->assign('Date_Year', date('Y'));
            $this->tpl->assign('Date_Month', date('m'));
            $this->tpl->assign('Date_Day', date('j'));
        }

        // Urls
        $this->r->text['form_url'] = suxFunct::makeUrl('/feeds/purge');
        $this->r->text['back_url'] = suxFunct::getPreviousURL();

        $this->r->title .= " | {$this->r->gtext['purge']}";

        // Template
        $this->tpl->display('purge.tpl');

    }


    /**
    * Process the form
    *
    * @param array $clean reference to validated $_POST
    */
    function formProcess(&$clean) {

        // Purge
        $this->rss->purgeFeeds($clean['Date']);

        // clear all caches, cheap and easy
        $this->tpl->clearAllCache();

        // Log, private
        $this->log->write($_SESSION['users_id'], "sux0r::feedsPurge() ", 1);

    }


    /**
    * The form was successfuly processed
    */
    function formSuccess() {

        // Template
        $this->r->text['back_url'] = suxFunct::getPreviousURL();

        $this->r->title .= " | {$this->r->gtext['success']}";
        $this->r->gtext['success2'] = $this->r->gtext['success3']; // Overwrite

        $this->tpl->display('success.tpl');

    }


}


