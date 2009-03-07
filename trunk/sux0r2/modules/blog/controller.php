<?php

/**
* controller
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.gnu.org/licenses/agpl.html
*/

function sux($action, $params = null) {

    switch($action)
    {

    case 'admin' :

        // --------------------------------------------------------------------
        // Admin
        // --------------------------------------------------------------------

        include_once('blogAdmin.php');
        $admin = new blogAdmin();

        if ($admin->formValidate($_POST)) {
            $admin->formProcess($_POST);
            $admin->formSuccess();
        }
        else {
            $admin->formBuild($_POST);
        }


        break;


    case 'edit' :

        // --------------------------------------------------------------------
        // Edit
        // --------------------------------------------------------------------

        $id = !empty($params[0]) ? $params[0]: null;

        include_once('blogEdit.php');
        $edit = new blogEdit($id);

        if ($edit->formValidate($_POST)) {
            $edit->formProcess($_POST);
            $edit->formSuccess();
        }
        else {
            $edit->formBuild($_POST);
        }

        break;


    case 'bookmarks' :

        // --------------------------------------------------------------------
        // Scan for bookmarks
        // --------------------------------------------------------------------

        if (empty($params[0]) || !filter_var($params[0], FILTER_VALIDATE_INT)) {
            suxFunct::redirect(suxFunct::makeUrl('/blog'));
        }

        include_once('blogBookmarks.php');
        $bm = new blogBookmarks($params[0]);

        if ($bm->formValidate($_POST)) {
            $bm->formProcess($_POST);
            $bm->formSuccess();
        }
        else {
            $bm->formBuild($_POST);
        }

        break;


    case 'reply' :

        // --------------------------------------------------------------------
        // Reply
        // --------------------------------------------------------------------

        if (empty($params[0]) || !filter_var($params[0], FILTER_VALIDATE_INT)) {
            suxFunct::redirect(suxFunct::makeUrl('/blog'));
        }

        include_once('blogReply.php');
        $reply = new blogReply($params[0]);

        if ($reply->formValidate($_POST)) {
            $reply->formProcess($_POST);
            $reply->formSuccess();
        }
        else {
            $reply->formBuild($_POST);
        }

        break;


    case 'view' :

        // --------------------------------------------------------------------
        // View
        // --------------------------------------------------------------------

        if (empty($params[0]) || !filter_var($params[0], FILTER_VALIDATE_INT)) {
            suxFunct::redirect(suxFunct::makeUrl('/blog'));
        }

        include_once('blog.php');
        $blog = new blog();
        $blog->view($params[0]);
        break;


    case 'author' :

        // --------------------------------------------------------------------
        // Author
        // --------------------------------------------------------------------

        if (empty($params[0])) {
            suxFunct::redirect(suxFunct::makeUrl('/blog'));
        }

        include_once('blog.php');
        $blog = new blog();
        $blog->author($params[0]);
        break;


    case 'tag' :

        // --------------------------------------------------------------------
        // Tag
        // --------------------------------------------------------------------

        if (empty($params[0])) {
            suxFunct::redirect(suxFunct::makeUrl('/blog'));
        }

        include_once('blog.php');
        $blog = new blog();

        if ($params[0] == 'cloud') $blog->tagcloud();
        else $blog->tag($params[0]);

        break;


    case 'category' :

        // --------------------------------------------------------------------
        // Category
        // --------------------------------------------------------------------

        if (empty($params[0])) {
            suxFunct::redirect(suxFunct::makeUrl('/blog'));
        }

        include_once('blog.php');
        $blog = new blog();
        $blog->category($params[0]);
        break;


    case 'month' :

        // --------------------------------------------------------------------
        // Month
        // --------------------------------------------------------------------

        $date = !empty($params[0]) ? $params[0]: date('Y-m-d');

        include_once('blog.php');
        $blog = new blog();
        $blog->month($date);
        break;


    case 'rss':

        // --------------------------------------------------------------------
        // RSS
        // --------------------------------------------------------------------

        include_once('blog.php');
        $blog = new blog();
        $blog->rss();
        break;


    default:

        // --------------------------------------------------------------------
        // Default
        // --------------------------------------------------------------------

        include_once('blog.php');
        $blog = new blog();
        $blog->listing();
        break;

    }

}

?>