<?php

/**
* feedsRenderer
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
require_once(dirname(__FILE__) . '/../../includes/suxRenderer.php');
require_once(dirname(__FILE__) . '/../bayes/bayesRenderer.php');

class feedsRenderer extends suxRenderer {

    // Arrays
    public $fp = array(); // Array of first posts
    public $sidelist = array(); // Array of threads in sidebar

    // Objects
    private $bayesRenderer;
    private $rss;


    /**
    * Constructor
    *
    * @param string $module
    */
    function __construct($module) {

        parent::__construct($module); // Call parent
        $this->bayesRenderer = new bayesRenderer('bayes');
        $this->rss = new suxRSS();

    }


    /**
    * @return string javascript
    */
    function genericBayesInterfaceInit() {

        return $this->bayesRenderer->genericBayesInterfaceInit();

    }


    /**
    * @param int $id messages id
    * @param string $link link table
    * @param string $module sux0r module, used to clear cache
    * @param string $document document to train
    * @return string html
    */
    function genericBayesInterface($id, $link, $module, $document) {

        return $this->bayesRenderer->genericBayesInterface($id, $link, $module, $document);

    }


    // ------------------------------------------------------------------------
    // Stuff like recent(), archives(), authors() is in the renderer because
    // there's no point in initializing if they aren't in the template
    // ------------------------------------------------------------------------
    
    function isSubscribed($feed_id) {
        
        if (!$this->isLoggedIn())
            return  "<img src='{$this->url}/media/{$this->partition}/assets/sticky.gif' border='0' width='12' height='12' />";
        
        
        $image = 'sticky.gif';
        // IF SUBSCRIBED($_SESSION['users_id'], $feed_id) $image = 'subscribed.gif';
        
        $html = "<img src='{$this->url}/media/{$this->partition}/assets/{$image}' border='0' width='12' height='12'
        onclick=\"toggleSubscription('{$feed_id}');\" 
        style='cursor: pointer;'
        class='subscription{$feed_id}'
        />";
        
        return $html;
        
    }
    
    
    function feedLink($id) {
        
        $tmp = $this->rss->getFeed($id);
        if(!$tmp) return null;
        
        $url = suxFunct::makeUrl("/feeds/{$id}");
        $html = "<a href='{$url}'>{$tmp['title']}</a>";
        return $html;
        
    }
    
    


    /**
    *
    * @return array
    */
    function feeds() {

        // Cache
        static $tmp = null;
        if (is_array($tmp)) return $tmp;
        $tmp = array();

        $tmp = $this->rss->getFeeds();

        return $tmp;

    }



}


?>