<?php

/**
* feedsSuggest
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @copyright  2008 sux0r development group
* @license    http://www.gnu.org/licenses/agpl.html
*
*/

require_once(dirname(__FILE__) . '/../../includes/suxRSS.php');
require_once(dirname(__FILE__) . '/../../includes/suxTemplate.php');
require_once(dirname(__FILE__) . '/../../includes/suxValidate.php');
require_once(dirname(__FILE__) . '/../../includes/suxRenderer.php');

class feedsSuggest  {

    // Variables
    public $gtext = array();
    protected $prev_url_preg = '#^feeds/[suggest|admin]#i';
    private $module = 'feeds';

    // Objects
    public $tpl;
    public $r;
    protected $user;
    protected $rss;

    /**
    * Constructor
    *
    */
    function __construct() {

        $this->rss = new suxRSS();
        $this->user = new suxUser(); // User
        $this->tpl = new suxTemplate($this->module); // Template
        $this->r = new suxRenderer($this->module); // Renderer
        $this->gtext = suxFunct::gtext($this->module); // Language
        $this->r->text =& $this->gtext;
        suxValidate::register_object('this', $this); // Register self to validator
        
        // Redirect if not logged in
        $this->user->loginCheck(suxfunct::makeUrl('/user/register'));        

    }


    /**
    * Validate the form
    *
    * @param array $dirty reference to unverified $_POST
    * @return bool
    */
    function formValidate(&$dirty) {

        if(!empty($dirty) && suxValidate::is_registered_form()) {
            // Validate
            suxValidate::connect($this->tpl);
            if(suxValidate::is_valid($dirty)) {
                suxValidate::disconnect();
                return true;
            }
        }
        return false;

    }


    /**
    * Build the form and show the template
    *
    * @param array $dirty reference to unverified $_POST
    */
    function formBuild(&$dirty) {

        if (!empty($dirty)) $this->tpl->assign($dirty);
        else suxValidate::disconnect();

        if (!suxValidate::is_registered_form()) {

            suxValidate::connect($this->tpl, true); // Reset connection

            // Register our additional criterias
            suxValidate::register_criteria('isDuplicateFeed', 'this->isDuplicateFeed');
            suxValidate::register_criteria('isValidFeed', 'this->isValidFeed');
            
            // Register our validators
            // register_validator($id, $field, $criteria, $empty = false, $halt = false, $transform = null, $form = 'default')
            suxValidate::register_validator('url', 'url', 'notEmpty', false, false, 'trim');
            suxValidate::register_validator('url2', 'url', 'isURL');
            suxValidate::register_validator('url3', 'url', 'isDuplicateFeed');

        }

        // Urls
        $this->r->text['form_url'] = suxFunct::makeUrl('/feeds/suggest');
        $this->r->text['back_url'] = suxFunct::getPreviousURL($this->prev_url_preg);

        // Template
        $this->tpl->assign_by_ref('r', $this->r);
        $this->tpl->display('suggest.tpl');

    }
    
    
    /**
    * Process the form
    *
    * @param array $clean reference to validated $_POST
    */
    function formProcess(&$clean) {

        // TODO

    }


    /**
    * The form was successfuly processed
    */
    function formSuccess() {

        // TODO 
        
    }



    /**
    * for suxValidate, check if a duplicate url exists
    *
    * @return bool
    */
    function isDuplicateFeed($value, $empty, &$params, &$formvars) {

        if (empty($formvars['url'])) return false;

        // TODO
        
        return true;

    }
    
    
    /**
    * for suxValidate, check if a RSS feed is valid
    *
    * @return bool
    */
    function isValidFeed($value, $empty, &$params, &$formvars) {

        if (empty($formvars['url'])) return false;

        // TODO
        
        return true;

    }    


}


?>