<?php

/**
* controller
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.fsf.org/licensing/licenses/gpl-3.0.html
*/

function sux($action, $params = null) {

    switch($action)
    {

    default:

        include_once('bayesEdit.php');
        $reg = new bayesEdit();
        if ($reg->formValidate($_POST)) $reg->formProcess($_POST);
        $reg->formBuild($_POST);

    }

}

?>