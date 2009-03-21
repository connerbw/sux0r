<?php

/**
* admin
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.fsf.org/licensing/licenses/gpl-3.0.html
*/

require_once(dirname(__FILE__) . '/../../includes/suxPager.php');
require_once(dirname(__FILE__) . '/../../includes/suxTemplate.php');

require_once('adminRenderer.php');
require_once(dirname(__FILE__) . '/../feeds/feedsRenderer.php');
require_once(dirname(__FILE__) . '/../bookmarks/bookmarksRenderer.php');

class admin {

    // Variables
    public $per_page = 100;
    private $module = 'admin';

    // Objects
    public $r;
    public $tpl;
    private $pager;


    /**
    * Constructor
    *
    */
    function __construct() {

        $this->tpl = new suxTemplate($this->module); // Template

        $this->r = new adminRenderer($this->module); // Renderer
        $this->tpl->assign_by_ref('r', $this->r); // Renderer referenced in template

        $this->user = new suxUser();
        $this->pager = new suxPager();

        // Redirect if not logged in
        if (empty($_SESSION['users_id'])) suxFunct::redirect(suxFunct::makeUrl('/user/register'));

        // Security check
        if (!$this->user->isRoot()) suxFunct::redirect(suxFunct::makeUrl('/home'));

    }


    function userlist() {

        // -------------------------------------------------------------------
        // Sort / Order
        // -------------------------------------------------------------------

        $sort = null;
        if (isset($_GET['sort'])) $sort = $_GET['sort'];

        $order = 'DESC';
        if (!empty($_GET['order'])) $order = $_GET['order'];


        // -------------------------------------------------------------------
        // Pager
        // -------------------------------------------------------------------

        $params = array(); // Extra params
        if ($sort) $params = array('sort' => $sort, 'order' => $order);

        $this->pager->limit = $this->per_page;
        $this->pager->setStart();
        $this->pager->setPages($this->user->count());
        $this->r->text['pager'] = $this->pager->pageList(suxFunct::makeUrl('/admin', $params));

        // -------------------------------------------------------------------
        // Template
        // -------------------------------------------------------------------

        $valid = array('users_id', 'nickname', 'email', 'root', 'banned', 'ts');
        if (in_array(mb_strtolower($sort), $valid)) {
            if ($sort == 'ts') $this->user->setOrder('last_active', $order);
           else $this->user->setOrder($sort, $order);
        }

        $this->r->arr['ulist'] = $this->user->get($this->pager->limit, $this->pager->start);

        $this->tpl->assign('sort', $sort);
        $this->r->text['sort_url'] = suxFunct::makeUrl('/admin', array('order' => (mb_strtolower($order) == 'desc' ? 'ASC' : 'DESC')));

        $this->r->title .= " | {$this->r->gtext['admin']}";

        $this->tpl->display('userlist.tpl');

    }


}


?>