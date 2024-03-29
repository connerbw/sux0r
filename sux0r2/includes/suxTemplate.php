<?php

/**
* suxTemplate
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.fsf.org/licensing/licenses/gpl-3.0.html
*/

require_once(__DIR__ . '/symbionts/Smarty/libs/Smarty.class.php');

class suxTemplate extends Smarty {

    public $module;
    public $partition;
    public $template_dir_fallback;

    /**
    * Constructor
    *
    * @global string $CONFIG['PATH']
    * @global string $CONFIG['PARTITION']
    * @param string $module
    * @param string $partition
    */
    function __construct($module) {

        // Call parent
        parent::__construct();

        // Set seperate error reporting for templates
        $this->error_reporting = $GLOBALS['CONFIG']['SMARTY_ERROR_REPORTING'];

        // --------------------------------------------------------------------
        // Plugins directory
        // --------------------------------------------------------------------

        $this->plugins_dir = array(
            $GLOBALS['CONFIG']['PATH'] . '/includes/symbionts/Smarty/libs/plugins',
            $GLOBALS['CONFIG']['PATH'] . '/includes/symbionts/SmartyAddons/plugins',
            );

        // --------------------------------------------------------------------
        // Setup
        // --------------------------------------------------------------------

        if (!empty($_SESSION['partition'])) $partition = $_SESSION['partition'];
        else $partition = $GLOBALS['CONFIG']['PARTITION'];

        $this->setModule($module, $partition);
        $this->setToken();
    }


    /**
    * Set the template for a module
    *
    * @global string $CONFIG['PATH']
    * @global string $CONFIG['CACHE_LIFETIME']
    * @param string $module
    * @param string $partition
    */
    function setModule($module, $partition = 'sux0r') {

        // --------------------------------------------------------------------
        // Compile directory
        // --------------------------------------------------------------------

        $compile_dir = $GLOBALS['CONFIG']['PATH'] . "/temporary/templates_c/$partition/$module/";
        if(!is_dir($compile_dir) && !mkdir($compile_dir, 0777, true)) {
            throw new Exception('Missing compile dir ' . $compile_dir);
        }
        $this->setCompileDir($compile_dir);


        // --------------------------------------------------------------------
        // Cache directory and variables
        // --------------------------------------------------------------------

        $cache_dir = $GLOBALS['CONFIG']['PATH'] . "/temporary/cache/$partition/$module/";
        if(!is_dir($cache_dir) && !mkdir($cache_dir, 0777, true)) {
            throw new Exception('Missing cache dir ' . $cache_dir);
        }
        $this->cache_dir = $cache_dir;
        $this->cache_lifetime = $GLOBALS['CONFIG']['CACHE_LIFETIME'];
        $this->caching = 0; // Caching off by default, enable in module if needed

        // --------------------------------------------------------------------
        // Config dir
        // --------------------------------------------------------------------

        $config_dir = $GLOBALS['CONFIG']['PATH'] . "/templates/$partition/globals/";
        $config_dir_fallback = $GLOBALS['CONFIG']['PATH'] . '/templates/sux0r/globals/';

        if($partition != 'sux0r' && !is_file($config_dir . 'my.conf')) {
            // We didn't find anything, but the partition wasn't default, let's try with default
            $config_dir = $config_dir_fallback;
        }

        $this->setConfigDir($config_dir);

        // --------------------------------------------------------------------
        // Template directory
        // --------------------------------------------------------------------


        $this->module = $module;
        $this->partition = $partition;

        $this->setTemplateDir(array(
        		$GLOBALS['CONFIG']['PATH'] . "/templates/$partition/$module/",
            $GLOBALS['CONFIG']['PATH'] . "/templates/sux0r/$module/"
            ));

    }


    /**
    * htmLawed Tidy
    *
    * @see http://www.bioinformatics.org/phplabware/internal_utilities/htmLawed/htmLawed_README.htm#s3.3.5
    * @param string $html
    * @param int|string $tidy
    * @return string
    */
    function tidy($html, $tidy) {

        /*
        htmLawed is meant for input that goes into the body of HTML documents.
        HTML's head-level elements are not supported, nor are the frameset
        elements frameset, frame and noframes.
        */

        require_once(__DIR__ . '/symbionts/htmLawed/htmLawed.php');
        $config = array(
            'tidy' => $tidy,
            );
        return htmLawed($html, $config);

    }


    /**
    * Set a random token that we can check against (for form spoofing.)
    * Will be available as {$token} in Smarty template
    *
    * @global array $_SESSION['_sux0r_tokens']
    */
    function setToken() {

        $token = md5(uniqid(random_int(0, mt_getrandmax()), true));
        $this->assign('token', $token);

        // Store token for later
        if (!isset($_SESSION['_sux0r_tokens'])) $_SESSION['_sux0r_tokens'] = array();
        array_unshift($_SESSION['_sux0r_tokens'], $token);
        $_SESSION['_sux0r_tokens'] = array_slice($_SESSION['_sux0r_tokens'], 0, 20); // maximum 20
    }


}

// -------------------------------------------------------------------------
// Smarty capitalize modifier doesn't work well with UTF-8, substitute it
// -------------------------------------------------------------------------

if (!function_exists('mb_ucwords')) {
    function mb_ucwords($string) {
        $string = mb_convert_case($string, MB_CASE_TITLE);
        $string = mb_ereg_replace('Sux0R', 'Sux0r', $string); // Exception
        return $string;
    }
}


function flash_encode($string) {

    $string = rawurlencode(utf8_encode($string));

    $string = str_replace("%C2%96", "-", $string);
    $string = str_replace("%C2%91", "%27", $string);
    $string = str_replace("%C2%92", "%27", $string);
    $string = str_replace("%C2%82", "%27", $string);
    $string = str_replace("%C2%93", "%22", $string);
    $string = str_replace("%C2%94", "%22", $string);
    $string = str_replace("%C2%84", "%22", $string);
    $string = str_replace("%C2%8B", "%C2%AB", $string);
    $string = str_replace("%C2%9B", "%C2%BB", $string);

    return $string;
}


