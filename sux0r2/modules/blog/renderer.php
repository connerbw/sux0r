<?php

/**
* renderer
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

require_once(dirname(__FILE__) . '/../../includes/suxUser.php');
require_once(dirname(__FILE__) . '/../../includes/suxThreadedMessages.php');
require_once(dirname(__FILE__) . '/../bayes/suxNbUser.php');
require_once(dirname(__FILE__) . '/../../includes/suxLink.php');
require_once(dirname(__FILE__) . '/../../includes/suxRenderer.php');


class renderer extends suxRenderer {

    // Arrays
    public $fp; // First Posts

    // Objects
    private $user;
    private $msg;
    private $nb;
    private $link;


    /**
    * Constructor
    *
    * @param string $module
    */
    function __construct($module) {

        parent::__construct($module); // Call parent
        $this->user = new suxUser();
        $this->msg = new suxThreadedMessages();
        $this->nb = new suxNbUser();
        $this->link = new suxLink();

    }


    /**
    * @return string html
    */
    function tags($category_ids, $users_id) {

        if (!$category_ids) return null;

        // Find all bayes categories/tags associated to this document by author

        $html = '[ Tags: ';
        foreach($category_ids as $key => $val) {
            if ($this->nb->isCategoryOwner($val, $users_id)) {
                $tmp = $this->nb->getCategory($val);
                $html .= "{$tmp['category']}, ";
            }
        }
        $html = rtrim($html, ', ');

        return $html . ' ]';

    }


    function articles($date) {

        static $tmp = array();
        if (count($tmp)) return $tmp; // Cache

        $tmp = $this->msg->getFirstPostsByMonth($date, 'blog', true);

        foreach($tmp as &$val) {
            $val['comments'] = $this->msg->getCommentsCount($val['thread_id']);
            $tmp2 = $this->user->getUser($val['users_id']);
            $val['nickname'] = $tmp2['nickname'];

            /*
            1) Get the `link_bayes_messages` matching this messages_id
            2) Foreach linking bayes_document_id
            3) get the categories I can use (nb::isCategoryUser($cat_id, $users_id)
            4) stuff them into {$category_id} for template, append doc_id to {$link} string
            */

            $val['linked'] = '';
            $links = $this->link->getLinks('link_bayes_messages', 'messages', $val['id']);
            foreach($links as $val2) {
                $cat = $this->nb->getCategoriesByDocument($val2);
                foreach ($cat as $key => $val3) {
                    if ($this->nb->isCategoryUser($key, $_SESSION['users_id'])) {
                        $val['linked'] .= "$val2, ";
                        $val['category_id'][] = $key;
                    }
                }
            }
            $val['linked'] = rtrim($val['linked'], ', '); // Remove trailing comma

        }

        // new dBug($tmp);

        return $tmp;

    }


    function recent() {

        $tmp = $this->msg->getRececentComments('blog');

        foreach($tmp as &$val) {
            $tmp2 = $this->user->getUser($val['users_id']);
            $val['nickname'] = $tmp2['nickname'];
            $tmp2 = $this->msg->getFirstPost($val['thread_id']);
            $val['title_fp'] = $tmp2['title'];
        }

        // new dBug($tmp);

        return $tmp;

    }


    // ------------------------------------------------------------------------
    // suxEdit
    // ------------------------------------------------------------------------

    /**
    * TinyMCE Initialization
    *
    * @see http://tinymce.moxiecode.com/
    * @global string $CONFIG['URL']
    * @global string $CONFIG['PATH']
    * @global string $CONFIG['LANGUAGE']
    * @param int $width optional width parameter for editor window
    * @param int $height optional height parameter for editor window
    * @return string the javascript code
    */
    function tinyMceInit() {

        // TinyMCE Path
        $path = $GLOBALS['CONFIG']['URL'] . '/includes/symbionts/tinymce/jscripts/tiny_mce/tiny_mce.js';

        // TinyMCE Language
        if (!empty($_SESSION['language'])) $lang = $_SESSION['language'];
        else $lang = $GLOBALS['CONFIG']['LANGUAGE'];
        // Sanity check
        $test = $GLOBALS['CONFIG']['PATH'] . "/includes/symbionts/tinymce/jscripts/tiny_mce/langs/{$lang}.js";
        if (!is_file($test)) $lang = 'en'; // Revert back to english

        // Javascript
        $js = '<script type="text/javascript" src="' . $path . '"></script>
        <script language="javascript" type="text/javascript">
        // <![CDATA[

        tinyMCE.init({
            mode : "textareas",
            theme : "advanced",
            editor_selector : "mceEditor",
            plugins : "paste,media,table,fullscreen,inlinepopups,autosave,safari",
            width: "100%",
            height: 400,
            theme_advanced_toolbar_location : "top",
            theme_advanced_toolbar_align : "left",
            theme_advanced_buttons1 : "bold,italic,underline,justifyleft,justifycenter,justifyright,justifyfull,numlist,bullist,outdent,indent,forecolor,backcolor,fontselect,fontsizeselect",
            theme_advanced_buttons2 : "undo,redo,pastetext,pasteword,selectall,link,unlink,table,image,media,removeformat,cleanup,code,fullscreen",
            theme_advanced_buttons3 : "",
            theme_advanced_statusbar_location : "bottom",
            entity_encoding : "raw",
            language : "' . $lang . '",
            relative_urls : false
        });

        // ]]>
        </script>' . "\n";

        return $js;

    }


    /**
    * @return array
    */
    function getUserVectors() {

        $vectors = array();
        if (!empty($_SESSION['users_id'])) foreach ($this->nb->getVectorsByUser($_SESSION['users_id']) as $key => $val) {
            $vectors[$key] = $val['vector'];
        }
        return $vectors;

    }



    /**
    * @return array
    */
    function getTrainerVectors() {

        $vectors = array();
        foreach ($this->nb->getVectorsByTrainer($_SESSION['users_id']) as $key => $val) {
            $vectors[$key] = $val['vector'];
        }
        return $vectors;

    }


    /**
    * @return array
    */
    function getCategoriesByVector($vector_id) {

        $categories[''] = '---';
        foreach ($this->nb->getCategoriesByVector($vector_id) as $key => $val) {
            $categories[$key] = $val['category'];
        }
        return $categories;

    }



}


?>