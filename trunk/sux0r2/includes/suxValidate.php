<?php

/**
* suxValidate
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
*/


/*

suxValidate extends SmartyValidate.

suxValidate protects against form spoofing and makes sure that a form submission
is intentional by adding a hidden form field with a one-time token, storing this
token in the user's SmartyValidate session.

*/

require_once(dirname(__FILE__) . '/symbionts/SmartyAddons/libs/SmartyValidate.class.php');

class suxValidate extends SmartyValidate {

    static function check_validators(array $v) {

        foreach ($v as $key => $val) {
            if (SmartyValidate::is_registered_validator($val, $key)  === false) {
                return false;
            }
        }
        return true;

    }



    // Static class, no cloning or instantiating allowed
    final private function __construct() { }
    final private function __clone() { }


    /**
    * Protect against form spoofing, make sure that a form submission is valid
    * and intentional, by adding a hidden form field with a one-time token, and
    * storing this token in the user's SmartyValidate session
    *
    * @param string $form the name of the form being validated
    */
    private static function token() {

        $_SESSION['SmartyValidate'][SMARTY_VALIDATE_DEFAULT_FORM]['token'] = md5(uniqid(mt_rand(), true));
        $_smarty_obj =& SmartyValidate::_object_instance('Smarty', $_dummy);
        $_smarty_obj->assign('token', $_SESSION['SmartyValidate'][SMARTY_VALIDATE_DEFAULT_FORM]['token']);

    }





    /**
    * Override connect(): initialize the validator
    *
    * @param obj    $smarty the smarty object
    * @param string $reset reset the default form?
    */
    static function connect(&$smarty, $reset = false) {
        if(SmartyValidate::is_valid_smarty_object($smarty)) {
            SmartyValidate::_object_instance('Smarty', $smarty);
            self::register_form(SMARTY_VALIDATE_DEFAULT_FORM, $reset);
        } else {
            trigger_error("SmartyValidate: [connect] I need a valid Smarty object.");
            return false;
        }
    }


    /**
    * Override register_form(): initialize the session data
    *
    * @param string $form the name of the form being validated
    * @param string $reset reset an already registered form?
    */
    static function register_form($form, $reset = false) {

        $_ret = false;

        if (!(SmartyValidate::is_registered_form($form) && !$reset)) {

            $_SESSION['SmartyValidate'][$form] = array();
            $_SESSION['SmartyValidate'][$form]['registered_funcs']['criteria'] = array();
            $_SESSION['SmartyValidate'][$form]['registered_funcs']['transform'] = array();
            $_SESSION['SmartyValidate'][$form]['validators'] = array();
            $_SESSION['SmartyValidate'][$form]['is_error'] = false;
            $_SESSION['SmartyValidate'][$form]['is_init'] = true;
            SmartyValidate::_smarty_assign();
            self::token();
            $_ret = true;

        }
        return $_ret;

    }


    /**
    * Override is_valid(): validate the form
    *
    * @param string $formvars the array of submitted for variables
    * @param string $form the name of the form being validated
    */
    static function is_valid(&$formvars, $form = SMARTY_VALIDATE_DEFAULT_FORM) {

        // ------------------------------------------------------------------
        // Token validation
        // ------------------------------------------------------------------

        $_ret = null;
        if (empty($formvars['token']) || empty($_SESSION['SmartyValidate'][SMARTY_VALIDATE_DEFAULT_FORM]['token'])) {
            trigger_error("SmartyValidate: [token] is not set.");
            $_ret = false;
        }
        else if ($formvars['token'] != $_SESSION['SmartyValidate'][SMARTY_VALIDATE_DEFAULT_FORM]['token']) {
            $_ret = false;
        }
        unset($formvars['token']);
        self::token();
        if ($_ret === false) {
            // We need this disconnect() here to fix problem with multiple 'default'
            // forms open in multiple browser tabs, otherwise shit is broken
            SmartyValidate::disconnect();
            return false;
        }


        // ------------------------------------------------------------------
        // And now, back to your regular scheduled program
        // ------------------------------------------------------------------

        static $_is_valid = array();

        if(isset($_is_valid[$form])) {
            // already validated the form
            return $_is_valid[$form];
        }

        $_smarty_obj =& SmartyValidate::_object_instance('Smarty', $_dummy);
        if(!SmartyValidate::is_valid_smarty_object($_smarty_obj)) {
            trigger_error("SmartyValidate: [is_valid] No valid smarty object, call connect() first.");
            return false;
        }

        if(!SmartyValidate::is_registered_form($form)) {
            trigger_error("SmartyValidate: [is_valid] form '$form' is not registered.");
            return false;
        } elseif ($_SESSION['SmartyValidate'][$form]['is_init']) {
            // first run, skip validation
            return false;
        } elseif (count($_SESSION['SmartyValidate'][$form]['validators']) == 0) {
            // nothing to validate
            return true;
        }

        // check for failed fields
        $_failed_fields = SmartyValidate::_failed_fields($formvars, $form);
        $_ret = is_array($_failed_fields) && count($_failed_fields) == 0;

        // set validation state of form
        $_SESSION['SmartyValidate'][$form]['is_error'] = !$_ret;

        $_is_valid[$form] = $_ret;

        return $_ret;

    }


}

?>