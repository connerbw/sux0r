<?php

/**
* cropper
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.fsf.org/licensing/licenses/gpl-3.0.html
*/

class cropper extends component {

    // Module name
    protected $module = 'cropper';

    // Form name
    protected $form_name = 'cropper';


    /**
    * Constructor
    *
    */
    function __construct() {

        // Declare objects
        $this->r = new cropperRenderer($this->module); // Renderer
        (new suxValidate())->register_object('this', $this); // Register self to validator
        parent::__construct(); // Let the parent do the rest

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
    * @global string $CONFIG['URL']
    * @param string $module
    * @param int $id
    * @param array $dirty reference to unverified $_POST
    */
    function formBuild($module, $id, &$dirty) {

        // Initialize width & height
        $width = 0;
        $height = 0;

        // Check $id
        if (!filter_var($id, FILTER_VALIDATE_INT) || $id < 1) throw new Exception ('Invalid $id');

        // Check $module, assign $table
        $table = $this->getTable($module);
        if (!$table) throw new Exception('Unsuported $module');

        // --------------------------------------------------------------------
        // Form logic
        // --------------------------------------------------------------------

        if (!empty($dirty)) $this->tpl->assign($dirty);
        else (new suxValidate())->disconnect();

        if (!(new suxValidate())->is_registered_form()) {
            (new suxValidate())->connect($this->tpl, true); // Reset connection
            (new suxValidate())->register_validator('integrity', 'integrity:module:id', 'hasIntegrity');
        }

        // --------------------------------------------------------------------
        // Get image from database
        // --------------------------------------------------------------------

        $query = "SELECT users_id, image FROM {$table} WHERE id = ? ";
        $db = suxDB::get();
        $st = $db->prepare($query);
        $st->execute(array($id));
        $image = $st->fetch(PDO::FETCH_ASSOC);

        if (!$image) throw new Exception('$image not found');

        if ($image['users_id'] != $_SESSION['users_id']) {
            // Check that the user is allowed to be here
            if (!$this->user->isRoot()) {
                $access = $this->user->getAccess($module);
                if (!isset($GLOBALS['CONFIG']['ACCESS'][$module]['admin']))
                    suxFunct::redirect(suxFunct::getPreviousURL('cropper'));
                elseif ($access < $GLOBALS['CONFIG']['ACCESS'][$module]['admin'])
                    suxFunct::redirect(suxFunct::getPreviousURL('cropper'));
            }
        }

        // Assign a url to the fullsize version of the image
        $image = $image['image'];
        $image = rawurlencode(suxPhoto::t2fImage($image));
        $image = "{$GLOBALS['CONFIG']['URL']}/data/{$module}/{$image}";
        $image = suxFunct::myHttpServer() . $image;

        // Double check
        if (!filter_var($image, FILTER_VALIDATE_URL)) $image = null;
        if (!preg_match('/\.(jpe?g|gif|png)$/i', (string) $image)) $image = null;
        if ($image) [$width, $height] = @getimagesize($image);

        // --------------------------------------------------------------------
        // Template
        // --------------------------------------------------------------------

        if ($image && $width && $height) {

            // Get config variables
            $this->tpl->configLoad('my.conf', $module);

            $this->tpl->assign('module', $module);
            $this->tpl->assign('id', $id);
            $this->tpl->assign('x2', $this->tpl->getConfigVars('thumbnailWidth')); // Pavatar
            $this->tpl->assign('y2', $this->tpl->getConfigVars('thumbnailHeight'));
            $this->tpl->assign('url_to_source', $image);
            $this->tpl->assign('width', $width);
            $this->tpl->assign('height', $height);

            $this->tpl->assign('form_url', suxFunct::makeUrl("/cropper/{$module}/{$id}"));
            $this->tpl->assign('prev_url', suxFunct::getPreviousURL('cropper'));

            $this->r->title .= " | {$this->r->gtext['crop_image']}";

            $this->tpl->display('cropper.tpl');

        }
        else throw new Exception('$image not found');

    }


    /**
    * Process the form
    *
    * @param array $clean reference to validated $_POST
    */
    function formProcess(&$clean) {

        // Check $module, assign $table
        $table = $this->getTable($clean['module']);
        if (!$table) throw new Exception('Unsuported $module');

        // --------------------------------------------------------------------
        // Get image from database
        // --------------------------------------------------------------------

        $query = "SELECT users_id, image FROM {$table} WHERE id = ? ";
        $db = suxDB::get();
        $st = $db->prepare($query);
        $st->execute(array($clean['id']));
        $image = $st->fetch(PDO::FETCH_ASSOC);

        if (!$image) throw new Exception('$image not found');

        if ($image['users_id'] != $_SESSION['users_id']) {
            // Security check
            if (!$this->user->isRoot()) {
                $access = $this->user->getAccess($clean['module']);
                if (!isset($GLOBALS['CONFIG']['ACCESS'][$this->module]['admin']))
                    suxFunct::redirect(suxFunct::getPreviousURL('cropper'));
                elseif ($access < $GLOBALS['CONFIG']['ACCESS'][$clean['module']]['admin'])
                    suxFunct::redirect(suxFunct::getPreviousURL('cropper'));
            }
        }

        $path_to_dest = "{$GLOBALS['CONFIG']['PATH']}/data/{$clean['module']}/{$image['image']}";
        $path_to_source = suxPhoto::t2fImage($path_to_dest);

        if (!is_writable($path_to_dest)) die('Destination is not writable? ' . $path_to_dest);

        // ----------------------------------------------------------------------------
        // Manipulate And Rewrite Image
        // ----------------------------------------------------------------------------

        // $image
        $format = explode('.', $path_to_source);
        $format = mb_strtolower(end($format));
        if ($format == 'jpg') $format = 'jpeg'; // fix stupid mistake
        if (!($format == 'jpeg' || $format == 'gif' || $format == 'png')) die('Invalid image format');

        // Try to adjust memory for big files
        suxPhoto::fudgeFactor($format, $path_to_source);

        $func = 'imagecreatefrom' . $format;
        $image = $func($path_to_source);
        if (!$image) die('Invalid image format');

        // $thumb
        $thumb = imagecreatetruecolor($clean['x2'] , $clean['y2']);

        $white = imagecolorallocate($thumb, 255, 255, 255);
        ImageFilledRectangle($thumb, 0, 0, $clean['x2'], $clean['y2'], $white);
        imagealphablending($thumb, true);

        // Output
        imagecopyresampled($thumb, $image, 0, 0, $clean['x1'], $clean['y1'], $clean['x2'], $clean['y2'], $clean['width'], $clean['height']);
        $func = 'image' . $format;
        $func($thumb, $path_to_dest);

        // Free memory
        imagedestroy($image);
        imagedestroy($thumb);

        $this->log->write($_SESSION['users_id'], "sux0r::cropper()  $table, id: {$clean['id']}", 1); // Private

    }

    function formSuccess() {

        suxFunct::redirect(suxFunct::getPreviousURL('cropper'));

    }


    /**
    * Check module, return table
    *
    * @param string $module
    * @return string
    */
    private function getTable($module) {

        if ($module == 'blog') $table = 'messages';
        elseif ($module == 'photos') $table = 'photos';
        elseif ($module == 'user') $table = 'users_info';
        else $table = false;

        return $table;

    }



}


