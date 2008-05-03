<?php

/**
* suxOpenID
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
* Inspired by:
* CJ Niemira: http://siege.org/projects/phpMyID/
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @copyright  2008 sux0r development group
* @license    http://www.gnu.org/licenses/agpl.html
*
*/

class suxOpenID {

    public $profile = array();
    public $sreg = array ();

    protected $db;
    protected $inTransaction = false;

    protected $assoc_types = array();
    protected $session_types = array();
    protected $bcmath_types = array();

    private $g;
    private $p;

    /**
    * Constructor
    */
    function __construct($key = null) {


        // --------------------------------------------------------------------
        // Sanity Check
        // --------------------------------------------------------------------

        if (!isset($GLOBALS['CONFIG'])) {
            die("Something is wrong, can't initialize without configuration.");
        }


        if (!session_id()) {
            die("Something is wrong, can't initialize without session_start().");
        }

        // --------------------------------------------------------------------
        // Setup
        // --------------------------------------------------------------------

    	$this->db = suxDB::get($key);
        set_exception_handler(array($this, 'logAndDie'));

        // Defined by OpenID spec
        // http://openid.net/specs/openid-authentication-1_1.html
        // http://openid.net/specs/openid-authentication-1_1.html#pvalue
        $this->assoc_types = array('HMAC-SHA1');
        $this->session_types = array('', 'DH-SHA1');
        $this->g = 2;
        $this->p =
        '1551728981814736974712322577637155' . '3991572480196691540447970779531405' .
        '7629378541917580651227423698188993' . '7278161526466314385615958256881888' .
        '8995127215884267541995034125870655' . '6549803580104870537681476726513255' .
        '7470407658574792912915723345106432' . '4509471500722962109419434978392598' .
        '4760375594985848253359305585439638443';

        // OpenID Setup user
        $this->profile = array(

            // Basic Config - Required
            // auth_password is md5(username:realm:password)
            'auth_username'	=> 	'test',
            'auth_password' =>	md5("test:{$GLOBALS['CONFIG']['REALM']}:test"),

            // Advanced Config
            'auth_realm'	=>$GLOBALS['CONFIG']['REALM'],
            'idp_url'	=>	$this->getIdpUrl(),
            'auth_domain' => $this->getReqUrl() . ' ' . $this->getIdpUrl(),
            'lifetime'	=>	1440,
            'use_bcmath' => true,

            // Debug
            'debug'		=>	true,
            'logfile'	=>	'/tmp/suxOpenID.debug.log',

            // Do not override
            'authorized'    => false,
            'req_url' => $this->getReqUrl(),

            // Optional Config
            // 	'microid'	=>	array('user@site.com', 'http://delegator.url'),
            //	'pavatar'	=>	'http://your.site.com/path/pavatar.img',

            );

        $this->sreg = array (
            //	'nickname'		=> 'Joe',
            //	'email'			=> 'joe@example.com',
            //	'fullname'		=> 'Joe Example',
            //	'dob'			=> '1970-10-31',
            //	'gender'		=> 'M',
            //	'postcode'		=> '22000',
            //	'country'		=> 'US',
            //	'language'		=> 'en',
            //	'timezone'		=> 'America/New_York'
            );

    }

    // ----------------------------------------------------------------------------
    // Runmode functions / OpenID Authentication 1.1 / are not camelCase()
    // ----------------------------------------------------------------------------

    /**
    * Perform an association with a consumer, establish a shared secret
    */
    function associate_mode() {

        // Validate the request
        if (empty($_POST['openid_mode']) || $_POST['openid_mode'] != 'associate') {
            $this->error400();
        }

        /* Get the OpenID Request Parameters */

        $assoc_type = 'HMAC-SHA1';
        if (!empty($_POST['openid_assoc_type']) && in_array($_POST['openid_assoc_type'], $this->assoc_types)) {
            $assoc_type = $_POST['openid_assoc_type'];
        }

        $session_type = '';
        if (!empty($_POST['openid_session_type']) && in_array($_POST['openid_session_type'], $this->session_types)) {
            $session_type = $_POST['openid_session_type'];
        }

        $dh_modulus = null;
        if (!empty($_POST['openid_dh_modulus'])) {
            $dh_modulus = $this->long(base64_decode($_POST['openid_dh_modulus']));
        }
        else if ($session_type == 'DH-SHA1') {
            $dh_modulus = $this->p;
        }

        $dh_gen = null;
        if (!empty($_POST['openid_dh_gen'])) {
            $dh_gen = $this->long(base64_decode($_POST['openid_dh_gen']));
        }
        else if ($session_type == 'DH-SHA1') {
            $dh_gen = $this->g;
        }

        $dh_consumer_public = null;
        if (!empty($_POST['openid_dh_consumer_public'])) {
            $dh_consumer_public = $_POST['openid_dh_consumer_public'];
        }
        else if ($session_type == 'DH-SHA1') {
            $this->errorPost('dh_consumer_public was not specified');
        }

        $lifetime = time() + $this->profile['lifetime'];

        // Create standard keys
        $keys = array(
            'assoc_type' => $assoc_type,
            'expires_in' => $this->profile['lifetime']
            );


        // If I don't handle bcmath, default to plaintext sessions
        if ($this->profile['use_bcmath'] === false) {
            $session_type = '';
        }

        // Add response keys based on the session type
        switch ($session_type) {

        case 'DH-SHA1':
            // Create the associate id and shared secret now
            list ($assoc_handle, $shared_secret) = $this->newAssoc($lifetime);

            // Compute the Diffie-Hellman stuff
            $private_key = $this->random($dh_modulus);
            $public_key = bcpowmod($dh_gen, $private_key, $dh_modulus);
            $remote_key = $this->long(base64_decode($dh_consumer_public));
            $ss = bcpowmod($remote_key, $private_key, $dh_modulus);

            $keys['assoc_handle'] = $assoc_handle;
            $keys['session_type'] = $session_type;
            $keys['dh_server_public'] = base64_encode($this->bin($public_key));
            $keys['enc_mac_key'] = base64_encode($this->x_or(sha1($this->bin($ss), true), $shared_secret));

            break;

        default:

            // Create the associate id and shared secret now
            list ($assoc_handle, $shared_secret) = $this->newAssoc($lifetime);

            $keys['assoc_handle'] = $assoc_handle;
            $keys['mac_key'] = base64_encode($shared_secret);

        }

        // Return the keys
        $this->wrapKv($keys);
    }


    /**
    * Handle a consumer's request to see if the user is already logged in
    */
    function checkid_immediate_mode () {
        if (empty($_GET['openid_mode']) || $_GET['openid_mode'] != 'checkid_immediate') {
            $this->error500();
        }
        $this->checkid(false);
    }


    /**
    * Handle a consumer's request to see if the user is logged in, but be willing
    * to wait for them to perform a login if they're not
    */
    function checkid_setup_mode () {
        if (empty($_GET['openid_mode']) || $_GET['openid_mode'] != 'checkid_setup') {
            $this->error500();
        }
        $this->checkid(true);
    }


    /**
    * Handle a consumer's request to see if the end user is logged in
    * @param bool $wait
    */
    private function checkid($wait) {

        $this->debug("checkid: wait? $wait");

        // This is a user session
        $this->userSession();

        /* Get the OpenID Request Parameters */

        $identity = $_GET['openid_identity'];
        if (empty($identity)) {
            $this->errorGet('Missing identity');
        }

        $assoc_handle = null;
        if (!empty($_GET['openid_assoc_handle'])) {
            $assoc_handle = $_GET['openid_assoc_handle'];
        }

        $return_to = $_GET['openid_return_to'];
        if (empty($return_to)) {
            $this->error400('Missing return_to');
        }

        $trust_root = $return_to;
        if (!empty($_GET['openid_trust_root'])) {
            $trust_root = $_GET['openid_trust_root'];
        }

        $sreg_required = '';
        if (!empty($_GET['openid_sreg_required'])) {
            $sreg_required = $_GET['openid_sreg_required'];
        }

        $sreg_optional = '';
        if (!empty($_GET['openid_sreg_optional'])) {
            $sreg_optional = $_GET['openid_sreg_optional'];
        }

        // concatenate required and optional, if they want it we give it
        $sreg_required .= ',' . $sreg_optional;

        // do the trust_root analysis
        if ($trust_root != $return_to) {
            // the urls are not the same, be sure return decends from trust
            if (! $this->urlDescends($return_to, $trust_root))
                $this->error500('Invalid trust_root: "' . $trust_root . '"');
        }


        if ($wait && (! session_is_registered('openid_accepted_url') || $_SESSION['openid_accepted_url'] != $trust_root)) {

            // checkid_setup_mode()

            $_SESSION['openid_cancel_accept_url'] = $return_to;
            $_SESSION['openid_post_accept_url'] = $this->profile['req_url'];
            $_SESSION['openid_unaccepted_url'] = $trust_root;

            $this->debug('Transferring to acceptance mode.');
            $this->debug('Cancel URL: ' . $_SESSION['openid_cancel_accept_url']);
            $this->debug('Post URL: ' . $_SESSION['openid_post_accept_url']);

            $q = mb_strpos($this->profile['idp_url'], '?') ? '&' : '?';
            $this->wrapRefresh($this->profile['idp_url'] . $q . 'openid.mode=accept');
        }

        // make sure i am this identifier
        if ($identity != $this->profile['idp_url']) {

            $this->debug("Invalid identity: $identity");
            $this->debug("IdP URL: " . $this->profile['idp_url']);

            $this->errorGet($return_to, "Invalid identity: '$identity'");
        }

        // begin setting up return keys
        $keys = array(
            'mode' => 'id_res'
            );

        // if the user is not logged in, transfer to the authorization mode
        if ($this->profile['authorized'] === false || $identity != $_SESSION['openid_auth_url']) {

            // Currently users can only be logged in to one url at a time
            $_SESSION['openid_auth_username'] = null;
            $_SESSION['openid_auth_url'] = null;

            if ($wait) {
                unset($_SESSION['openid_uniqid']);

                $_SESSION['openid_cancel_auth_url'] = $return_to;
                $_SESSION['openid_post_auth_url'] = $this->profile['req_url'];

                $this->debug('Transferring to authorization mode.');
                $this->debug('Cancel URL: ' . $_SESSION['openid_cancel_auth_url']);
                $this->debug('Post URL: ' . $_SESSION['openid_post_auth_url']);

                $q = mb_strpos($this->profile['idp_url'], '?') ? '&' : '?';
                $this->wrapRefresh($this->profile['idp_url'] . $q . 'openid.mode=authorize');
            }
            else {

                $keys['user_setup_url'] = $this->profile['idp_url'];
            }


        }
        else {

            // the user is logged in
            // remove the refresh URLs if set
            unset($_SESSION['openid_cancel_auth_url']);
            unset($_SESSION['openid_post_auth_url']);

            // check the assoc handle
            list($shared_secret, $expires) = $this->secret($assoc_handle);

            // if I can't verify the assoc_handle, or if it's expired
            if (!$shared_secret || (is_numeric($expires) && $expires < time())) {

                $this->debug("Session expired or missing key: $expires < " . time());

                if ($assoc_handle != null) {
                    $keys['invalidate_handle'] = $assoc_handle;
                    $this->destroyAssocHandle($assoc_handle);
                }

                $lifetime = time() + $this->profile['lifetime'];
                list ($assoc_handle, $shared_secret) = $this->newAssoc($lifetime);
            }

            $keys['identity'] = $this->profile['idp_url'];
            $keys['assoc_handle'] = $assoc_handle;
            $keys['return_to'] = $return_to;

            $fields = array_keys($keys);
            $tokens = '';
            foreach ($fields as $key)
                $tokens .= sprintf("%s:%s\n", $key, $keys[$key]);

            // add sreg keys
            foreach (explode(',', $sreg_required) as $key) {

                if (empty($this->sreg[$key])) continue;

                $skey = 'sreg.' . $key;
                $tokens .= sprintf("%s:%s\n", $skey, $this->sreg[$key]);
                $keys[$skey] = $this->sreg[$key];
                $fields[] = $skey;
            }

            $keys['signed'] = implode(',', $fields);
            $keys['sig'] = base64_encode(hash_hmac('sha1', $tokens, $shared_secret, true));
        }

        $this->wrapLocation($return_to, $keys);
    }


    /**
    * Handle a consumer's request to see if the user is authenticated
    */
    function check_authentication_mode() {

        // Validate the request
        if (empty($_POST['openid_mode']) || $_POST['openid_mode'] != 'check_authentication') {
            $this->error400();
        }

        /* Get the OpenID Request Parameters */

        $assoc_handle = $_POST['openid_assoc_handle'];
        if (empty($assoc_handle)) {
            $this->errorPost('Missing assoc_handle');
        }

        $sig = $_POST['openid_sig'];
        if (empty($sig)) {
            $this->errorPost('Missing sig');
        }

        $signed = $_POST['openid_signed'];
        if (empty($signed)) {
            $this->errorPost('Missing signed');
        }

        // Prepare the return keys
        $keys = array(
            'openid.mode' => 'id_res'
            );

        // Invalidate the assoc handle if we need to
        if (!empty($_POST['openid_invalidate_handle'])) {
            $this->destroyAssocHandle($_POST['openid_invalidate_handle']);
            $keys['invalidate_handle'] = $_POST['openid_invalidate_handle'];
        }

        // Validate the sig by recreating the kv pair and signing
        $_POST['openid_mode'] = 'id_res';
        $tokens = '';
        foreach (explode(',', $signed) as $param) {
            $post = preg_replace('/\./', '_', $param);
            $tokens .= sprintf("%s:%s\n", $param, $_POST['openid_' . $post]);
        }

        // Look up the consumer's shared_secret and timeout
        list ($shared_secret, $expires) = $this->secret($assoc_handle);

        // if I can't verify the assoc_handle, or if it's expired
        $ok = null;
        if (!$shared_secret || (is_numeric($expires) && $expires < time())) {
            $keys['is_valid'] = 'false';
        }
        else {
            $ok = base64_encode(hash_hmac('sha1', $tokens, $shared_secret, true));
            $keys['is_valid'] = ($sig == $ok) ? 'true' : 'false';
        }

        $this->debug("\$sig: $sig == \$ok: $ok");

        // Return the keys
        $this->wrapKv($keys);
    }


    // ----------------------------------------------------------------------------
    // Runmode functions / Not Sure
    // ----------------------------------------------------------------------------


    /**
    * Allow the user to accept trust on a URL
    */
    function accept_mode() {

        // this is a user session
        $this->userSession();

        // the user needs refresh urls in their session to access this mode
        if (empty($_SESSION['openid_post_accept_url']) || empty($_SESSION['openid_cancel_accept_url']) || empty($_SESSION['openid_unaccepted_url']))
            $this->error500('You may not access this mode directly.');

        // has the user accepted the trust_root?
        $accepted = (!empty($_REQUEST['accepted'])) ? $_REQUEST['accepted'] : null;

        if ($accepted === 'yes') {
            // refresh back to post_accept_url
            $_SESSION['openid_accepted_url'] = $_SESSION['openid_unaccepted_url'];
            $this->wrapRefresh($_SESSION['openid_post_accept_url']);
        }
        elseif ($accepted === 'no') {
            // They rejected it, return to the client
            $q = mb_strpos($_SESSION['openid_cancel_accept_url'], '?') ? '&' : '?';
            $this->wrapRefresh($_SESSION['openid_cancel_accept_url'] . $q . 'openid.mode=cancel');
        }

        // if neither, offer the trust request
        $q = mb_strpos($this->profile['req_url'], '?') ? '&' : '?';
        $yes = $this->profile['req_url'] . $q . 'accepted=yes';
        $no  = $this->profile['req_url'] . $q . 'accepted=no';

        $this->wrapHtml('The client site you are attempting to log into has requested that you trust the following URL:<br/><b>' . $_SESSION['openid_unaccepted_url'] . '</b><br/><br/>Do you wish to continue?<br/><a href="' . $yes . '">Yes</a> | <a href="' . $no . '">No</a>');
    }


    /**
    * Perform a user authorization
    */
    function authorize_mode() {

        // this is a user session
        $this->userSession();

        // the user needs refresh urls in their session to access this mode
        if (empty($_SESSION['openid_post_auth_url']) || empty($_SESSION['openid_cancel_auth_url']))
            $this->error500('You may not access this mode directly.');

        // try to get the digest headers - what a PITA!
        if (function_exists('apache_request_headers') && ini_get('safe_mode') == false) {
            $arh = apache_request_headers();
            $hdr = (isset($arh['Authorization']) ? $arh['Authorization'] : null);

        } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
            $hdr = $_SERVER['PHP_AUTH_DIGEST'];

        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $hdr = $_SERVER['HTTP_AUTHORIZATION'];

        } elseif (isset($_ENV['PHP_AUTH_DIGEST'])) {
            $hdr = $_ENV['PHP_AUTH_DIGEST'];

        } elseif (isset($_REQUEST['auth'])) {
            $hdr = stripslashes(urldecode($_REQUEST['auth']));

        } else {
            $hdr = null;
        }

        $this->debug('Authorization header: ' . $hdr);
        $digest = mb_substr($hdr,0,7) == 'Digest '
		?  mb_substr($hdr, mb_strpos($hdr, ' ') + 1)
		: $hdr;

        $stale = false;

        // is the user trying to log in?
        if (! is_null($digest) && $this->profile['authorized'] === false) {
            $this->debug('Digest headers: ' . $digest);
            $hdr = array();

            // decode the Digest authorization headers
            $mtx = array();
            preg_match_all('/(\w+)=(?:"([^"]+)"|([^\s,]+))/', $digest, $mtx, PREG_SET_ORDER);

            foreach ($mtx as $m)
                $hdr[$m[1]] = $m[2] ? $m[2] : $m[3];

            $this->debug($hdr, 'Parsed digest headers:');

            if (isset($_SESSION['openid_uniqid']) && $hdr['nonce'] != $_SESSION['openid_uniqid']) {
                $stale = true;
                unset($_SESSION['openid_uniqid']);
            }

            if (! isset($_SESSION['openid_failures']))
                $_SESSION['openid_failures'] = 0;

            if ($this->profile['auth_username'] == $hdr['username'] && ! $stale) {

                // the entity body should always be null in this case
                $entity_body = '';
                $a1 = mb_strtolower($this->profile['auth_password']);
                $a2 = $hdr['qop'] == 'auth-int'
				? md5(implode(':', array($_SERVER['REQUEST_METHOD'], $hdr['uri'], md5($entity_body))))
				: md5(implode(':', array($_SERVER['REQUEST_METHOD'], $hdr['uri'])));
                $ok = md5(implode(':', array($a1, $hdr['nonce'], $hdr['nc'], $hdr['cnonce'], $hdr['qop'], $a2)));

                // successful login!
                if ($hdr['response'] == $ok) {

                    $this->debug('Authentication successful');
                    $this->debug('User session is: ' . session_id());

                    $_SESSION['openid_auth_username'] = $hdr['username'];
                    $_SESSION['openid_auth_url'] = $this->profile['idp_url'];

                    $this->profile['authorized'] = true;

                    // return to the refresh url if they get in
                    $this->wrapRefresh($_SESSION['openid_post_auth_url']);


                }
                elseif (strcmp($hdr['nc'], 4) > 0 || $_SESSION['openid_failures'] > 4) {
                    // too many failures
                    $this->debug('Too many password failures');

                    $this->errorGet($_SESSION['openid_cancel_auth_url'], 'Too many password failures. Double check your authorization realm. You must restart your browser to try again.');

                }
                else {
                    // failed login
                    $_SESSION['openid_failures']++;

                    $this->debug('Login failed: ' . $hdr['response'] . ' != ' . $ok);
                    $this->debug('Fail count: ' . $_SESSION['openid_failures']);
                }
            }

        }
        elseif (is_null($digest) && $this->profile['authorized'] === false && isset($_SESSION['openid_uniqid'])) {
            $this->error500('Missing expected authorization header.');
        }

        // if we get this far the user is not authorized, so send the headers
        $uid = uniqid(mt_rand(1,9));
        $_SESSION['openid_uniqid'] = $uid;

        $this->debug('Prompting user to log in. Stale? ' . $stale);

        if (headers_sent())
            throw new Exception('authorize_mode: Headers already sent');

        header('HTTP/1.0 401 Unauthorized');
        header(sprintf('WWW-Authenticate: Digest qop="auth-int, auth", realm="%s", domain="%s", nonce="%s", opaque="%s", stale="%s", algorithm="MD5"', $this->profile['auth_realm'], $this->profile['auth_domain'], $uid, md5($this->profile['auth_realm']), $stale ? 'true' : 'false'));
        $q = mb_strpos($_SESSION['openid_cancel_auth_url'], '?') ? '&' : '?';

        $this->wrapRefresh($_SESSION['openid_cancel_auth_url'] . $q . 'openid.mode=cancel');
    }


    /**
    *  Handle a consumer's request for cancellation.
    */
    function cancel_mode () {
        $this->wrapHtml('Request cancelled.');
    }


    /**
    * Handle errors
    */
    function error_mode () {
        isset($_REQUEST['openid_error']) ? $this->wrapHtml($_REQUEST['openid_error']) : $this->error500();
    }


    /**
    * Show a user if they are logged in or not
    */
    function id_res_mode () {

        $this->userSession();

        if ($this->profile['authorized'])
            $this->wrapHtml('You are logged in as ' . $_SESSION['openid_auth_username']);

        $this->wrapHtml('You are not logged in');
    }


    /**
    * Allow a user to perform a static login
    */
    function login_mode () {

        $this->userSession();

        if ($this->profile['authorized']) $this->id_res_mode();

        $keys = array(
            'mode' => 'checkid_setup',
            'identity' => $this->profile['idp_url'],
            'return_to' => $this->profile['idp_url']
            );

        $this->wrapLocation($this->profile['idp_url'], $keys);

    }


    /**
    * Allow a user to perform a static logout
    */
    function logout_mode () {

        $this->userSession();

        if (!$this->profile['authorized']) $this->wrapHtml('You were not logged in');

        if (isset($_SESSION) && is_array($_SESSION)) {
            foreach ($_SESSION as $key => $val) {
                if (preg_match('/^openid_/', $key)) {
                    unset($_SESSION[$key]);
                }
            }
        }

        $this->debug('User session destroyed.');

        if (headers_sent())
            throw new Exception('logout_mode: Headers already sent');

        header('HTTP/1.0 401 Unauthorized');
        $this->wrapRefresh($this->profile['idp_url']);
    }


    /**
    * The default information screen
    */
    function no_mode () {

        $this->wrapHtml('This is an OpenID server endpoint. For more information, see http://openid.net/<br/>Server: <b>' . $this->profile['idp_url'] . '</b><br/>Realm: <b>' . $this->profile['auth_realm'] . '</b><br/><a href="' . $this->profile['idp_url'] . '?openid.mode=login">Login</a>  | <a href="' . $this->profile['idp_url'] . '?openid.mode=test">Test</a>');
    }


    /**
    * Testing for setup
    */
    function test_mode () {

        @ini_set('max_execution_time', 180);

        $test_expire = time() + 120;
        $test_ss_enc = 'W7hvmld2yEYdDb0fHfSkKhQX+PM=';
        $test_ss = base64_decode($test_ss_enc);
        $test_token = "alpha:bravo\ncharlie:delta\necho:foxtrot";
        $test_server_private = '11263846781670293092494395517924811173145217135753406847875706165886322533899689335716152496005807017390233667003995430954419468996805220211293016296351031812246187748601293733816011832462964410766956326501185504714561648498549481477143603650090931135412673422192550825523386522507656442905243832471167330268';
        $test_client_public = base64_decode('AL63zqI5a5p8HdXZF5hFu8p+P9GOb816HcHuvNOhqrgkKdA3fO4XEzmldlb37nv3+xqMBgWj6gxT7vfuFerEZLBvuWyVvR7IOGZmx0BAByoq3fxYd3Fpe2Coxngs015vK37otmH8e83YyyGo5Qua/NAf13yz1PVuJ5Ctk7E+YdVc');

        $res = array();

        // bcmath
        $res['bcmath'] = extension_loaded('bcmath')
		? 'pass' : 'warn - not loaded';

        // sys_get_temp_dir
        $res['logfile'] = is_writable($this->profile['logfile'])
		? 'pass' : "warn - log is not writable";

        // secret
        list($test_assoc, $test_new_ss) = $this->newAssoc($test_expire);
        list($check, $check2) = $this->secret($test_assoc);
        $res['secret'] = ($check == $test_new_ss)
		? 'pass' : 'fail';

        // expire
        $res['expire'] = ($check2 <= $test_expire)
		? 'pass' : 'fail';

        // base64
        $res['base64'] = (base64_encode($test_ss) == $test_ss_enc)
		? 'pass' : 'fail';

        // hmac
        $test_sig = base64_decode('/VXgHvZAOdoz/OTa5+XJXzSGhjs=');
        $check = hash_hmac('sha1', $test_token, $test_ss, true);
        $res['hmac'] = ($check == $test_sig)
		? 'pass' : sprintf("fail - '%s'", base64_encode($check));

        if ($this->profile['use_bcmath']) {
            // bcmath powmod
            $test_server_public = '102773334773637418574009974502372885384288396853657336911033649141556441102566075470916498748591002884433213640712303846640842555822818660704173387461364443541327856226098159843042567251113889701110175072389560896826887426539315893475252988846151505416694218615764823146765717947374855806613410142231092856731';
            $check = bcpowmod($this->g, $test_server_private, $this->p);
            $res['bmpowmod-1'] = ($check == $test_server_public)
			? 'pass' : sprintf("fail - '%s'", $check);

            // long
            $test_client_long = '133926731803116519408547886573524294471756220428015419404483437186057383311250738749035616354107518232016420809434801736658109316293127101479053449990587221774635063166689561125137927607200322073086097478667514042144489248048756916881344442393090205172004842481037581607299263456852036730858519133859409417564';
            $res['long'] = ($this->long($test_client_public) == $test_client_long)
			? 'pass' : 'fail';

            // bcmath powmod 2
            $test_client_share = '19333275433742428703546496981182797556056709274486796259858099992516081822015362253491867310832140733686713353304595602619444380387600756677924791671971324290032515367930532292542300647858206600215875069588627551090223949962823532134061941805446571307168890255137575975911397744471376862555181588554632928402';
            $check = bcpowmod($test_client_long, $test_server_private, $this->p);
            $res['bmpowmod-2'] = ($check == $test_client_share)
			? 'pass' : sprintf("fail - '%s'", $check);

            // bin
            $test_client_mac_s1 = base64_decode('G4gQQkYM6QmAzhKbVKSBahFesPL0nL3F2MREVwEtnVRRYI0ifl9zmPklwTcvURt3QTiGBd+9Dn3ESLk5qka6IO5xnILcIoBT8nnGVPiOZvTygfuzKp4tQ2mXuIATJoa7oXRGmBWtlSdFapH5Zt6NJj4B83XF/jzZiRwdYuK4HJI=');
            $check = $this->bin($test_client_share);
            $res['bin'] = ($check == $test_client_mac_s1)
			? 'pass' : sprintf("fail - '%s'", base64_encode($check));

        } else {
            $res['bcmath'] = 'fail - big math functions are not available.';
        }

        // sha1_20
        $test_client_mac_s1 = base64_decode('G4gQQkYM6QmAzhKbVKSBahFesPL0nL3F2MREVwEtnVRRYI0ifl9zmPklwTcvURt3QTiGBd+9Dn3ESLk5qka6IO5xnILcIoBT8nnGVPiOZvTygfuzKp4tQ2mXuIATJoa7oXRGmBWtlSdFapH5Zt6NJj4B83XF/jzZiRwdYuK4HJI=');
        $test_client_mac_s2 = base64_decode('0Mb2t9d/HvAZyuhbARJPYdx3+v4=');
        $check = sha1($test_client_mac_s1, true);
        $res['sha1_20'] = ($check == $test_client_mac_s2)
		? 'pass' : sprintf("fail - '%s'", base64_encode($check));

        // x_or
        $test_client_mac_s3 = base64_decode('i36ZLYAJ1rYEx1VEHObrS8hgAg0=');
        $check = $this->x_or($test_client_mac_s2, $test_ss);
        $res['x_or'] = ($check == $test_client_mac_s3)
		? 'pass' : sprintf("fail - '%s'", base64_encode($check));

        $out = "<table border=1 cellpadding=4>\n";
        foreach ($res as $test => $stat) {
            $code = mb_substr($stat, 0, 4);
            $color = ($code == 'pass') ? '#9f9'
			: (($code == 'warn') ? '#ff9' : '#f99');
            $out .= sprintf("<tr><th>%s</th><td style='background:%s'>%s</td></tr>\n", $test, $color, $stat);
        }
        $out .= "</table>";

        $this->wrapHtml( $out );
    }


    // ----------------------------------------------------------------------------
    // Support functions
    // ----------------------------------------------------------------------------


    /**
    * Prefix the keys of an array with  'openid.'
    * @param array $array
    * @return array
    */
    function appendOpenID($array) {

        $r = array();
        foreach ($array as $key => $val) {
            $r['openid.' . $key] = $val;
        }
        return $r;

    }


    /**
    * Debug logging
    * @param mixed $x
    * @param string $m
    */
    function debug($x, $m = null) {

        if (empty($this->profile['debug']) || $this->profile['debug'] === false) return true;

        if (is_array($x)) {
            ob_start();
            print_r($x);
            $x = $m . ($m != null ? "\n" : '') . ob_get_clean();

        } else {
            $x .= "\n";
        }

        error_log($x . "\n", 3, $this->profile['logfile']);
    }


    /**
    * Destroy a consumer's assoc handle
    * @param string $id
    */
    function destroyAssocHandle($id) {

        if (!filter_var($id, FILTER_VALIDATE_INT)) return false;

        $this->debug("Destroying session: $id");

        // While we're in here, delete expired associations
        $st = $this->db->prepare('DELETE FROM openid_associations WHERE expiration < ? ');
        $st->execute(array(time()));

        // Delete association
        $st = $this->db->prepare('DELETE FROM openid_associations WHERE id = ? ');
        $st->execute(array($id));

    }


    /**
    * Create a new consumer association
    * @param integer $expiration
    * @return array
    */
    function newAssoc($expiration) {

        if (!filter_var($expiration, FILTER_VALIDATE_INT)) return array(false, false);

        // While we're in here, delete expired associations
        $st = $this->db->prepare('DELETE FROM openid_associations WHERE expiration < ? ');
        $st->execute(array(time()));

        // Establish a shared secret
        $shared_secret = $this->newSecret();
        $st = $this->db->prepare('INSERT INTO openid_associations (expiration, shared_secret) VALUES (?, ?) ');
        $st->execute(array($expiration, base64_encode($shared_secret)));
        $id = $this->db->lastInsertId();

        $this->debug('Started new assoc session: ' . $id);

        return array($id, $shared_secret);
    }


    /**
    * Get the shared secret and expiration time for the specified assoc_handle
    * @param string $handle assoc_handle to look up
    * @return array (shared_secret, expiration_time)
    */
    function secret($id) {

        $st = $this->db->prepare('SELECT expiration, shared_secret FROM openid_associations WHERE id = ? ');
        $st->execute(array($id));
        $row = $st->fetch();

        $secret = !empty($row['shared_secret'])
		? base64_decode($row['shared_secret'])
		: false;

        $expiration = !empty($row['expiration'])
		? $row['expiration']
		: null;

        $this->debug("Found key: hash = '" . md5($secret) . "', length = '" . mb_strlen($secret) . "', expiration = '$expiration'");

        return array($secret, $expiration);

    }


    /**
    * Create a new shared secret
    * @return string
    */
    function newSecret() {

        $r = '';
        for($i=0; $i<20; $i++)
            $r .= chr(mt_rand(0, 255));

        $this->debug("Generated new key: hash = '" . md5($r) . "', length = '" . mb_strlen($r) . "'");

        return $r;

    }


    /**
    * Determine if a child URL actually decends from the parent, and that the
    * parent is a good URL.
    * THIS IS EXPERIMENTAL
    * @param string $parent
    * @param string $child
    * @return bool
    */
    function urlDescends($child, $parent) {


        if ($child == $parent) return true;

        $keys = array();
        $parts = array();
        $req = array('scheme', 'host');
        $bad = array('fragment', 'pass', 'user');

        foreach (array('parent', 'child') as $name) {
            $parts[$name] = @parse_url($$name);
            if ($parts[$name] === false)
                return false;

            $keys[$name] = array_keys($parts[$name]);

            if (array_intersect($keys[$name], $req) != $req)
                return false;

            if (array_intersect($keys[$name], $bad) != array())
                return false;

            if (! preg_match('/^https?$/i', strtolower($parts[$name]['scheme'])))
                return false;

            if (! array_key_exists('port', $parts[$name]))
                $parts[$name]['port'] = (strtolower($parts[$name]['scheme']) == 'https') ? 443 : 80;

            if (! array_key_exists('path', $parts[$name]))
                $parts[$name]['path'] = '/';
        }

        // port and scheme must match
        if ($parts['parent']['scheme'] != $parts['child']['scheme'] ||
            $parts['parent']['port'] != $parts['child']['port'])
		return false;

        // compare the hosts by reversing the strings
        $cr_host = mb_strtolower(strrev($parts['child']['host']));
        $pr_host = mb_strtolower(strrev($parts['parent']['host']));

        $break = $this->strDiffAt($cr_host, $pr_host);
        if ($break >= 0 && ($pr_host[$break] != '*' || substr_count($pr_host, '.', 0, $break) < 2)) {
            return false;
        }

        // now compare the paths
        $break = $this->strDiffAt($parts['child']['path'], $parts['parent']['path']);
        @($pb_char = $parts['parent']['path'][$break]);
        if ($break >= 0 && ($break < strlen($parts['parent']['path']) && $pb_char != '*') || ($break > strlen($parts['child']['path']))) {
            return false;
        }

        return true;
    }


    /**
    * Look for the point of differentiation in two strings
    * @param string $a
    * @param string $b
    * @return int
    */
    function strDiffAt($a, $b) {

        if ($a == $b) return -1;

        $a_len = mb_strlen($a);
        $b_len = mb_strlen($b);

        for ($i = 0; $i < $a_len; $i++) {
            if ($b_len <= $i || $a[$i] != $b[$i]) {
                break;
            }
        }

        if (strlen($b) > strlen($a)) $i++;

        return $i;
    }



    /**
    * Create a user session
    */
    function userSession() {

        $this->profile['authorized'] = (isset($_SESSION['openid_auth_username']) && $_SESSION['openid_auth_username'] == $this->profile['auth_username'])
        ? true
        : false;

        $this->debug('Started user session: ' . session_id() . ' Auth? ' . $this->profile['authorized']);
    }


    /**
    * Get the URL of the current script
    * @return string url
    */
    function getIdpUrl() {

        $s = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 's' : '';
        $host = $_SERVER['SERVER_NAME'];
        $port = $_SERVER['SERVER_PORT'];
        $path = $_SERVER['PHP_SELF'];

        if (($s && $port == "443") || (!$s && $port == "80") || preg_match("/:$port\$/", $host)) {
            $p = '';
        } else {
            $p = ':' . $port;
        }

        return "http$s://$host$p$path";
    }


    /**
    * Get the requested url
    * @return string url
    */
    function getReqUrl() {

        $s = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 's' : '';
        $host = $_SERVER['HTTP_HOST'];
        $port = $_SERVER['SERVER_PORT'];
        $path = $_SERVER['REQUEST_URI'];

        if (($s && $port == "443") || (!$s && $port == "80") || preg_match("/:$port\$/", $host)) {
            $p = '';
        } else {
            $p = ':' . $port;
        }

        return "http$s://$host$p$path";
    }


    // ----------------------------------------------------------------------------
    // Maths
    // ----------------------------------------------------------------------------

    /**
    * Random number generation
    * @param integer max
    * @return integer
    */
    function random($max) {
        if (strlen($max) < 4)
            return mt_rand(1, $max - 1);

        $r = '';
        for($i=1; $i < strlen($max) - 1; $i++)
            $r .= mt_rand(0,9);
        $r .= mt_rand(1,9);

        return $r;
    }


    /**
    * Get a binary value
    * @param integer $n
    * @return string
    * @url http://openidenabled.com Borrowed from PHP-OpenID
    */
    function bin($n) {
        $bytes = array();
        while (bccomp($n, 0) > 0) {
            array_unshift($bytes, bcmod($n, 256));
            $n = bcdiv($n, bcpow(2,8));
        }

        if ($bytes && ($bytes[0] > 127))
            array_unshift($bytes, 0);

        $b = '';
        foreach ($bytes as $byte)
            $b .= pack('C', $byte);

        return $b;
    }


    /**
    * Turn a binary back into a long
    * @param string $b
    * @return integer
    * @url http://openidenabled.com Borrowed from PHP-OpenID
    */
    function long($b) {
        $bytes = array_merge(unpack('C*', $b));
        $n = 0;
        foreach ($bytes as $byte) {
            $n = bcmul($n, bcpow(2,8));
            $n = bcadd($n, $byte);
        }
        return $n;
    }


    /**
    * Implement binary x_or
    * @param string $a
    * @param string $b
    * @return string
    */
    function x_or($a, $b) {

        $r = '';
        for ($i = 0; $i < strlen($b); $i++) {
            $r .= $a[$i] ^ $b[$i];
        }

        $this->debug("Xor size: " . strlen($r));

        return $r;
    }

    // ----------------------------------------------------------------------------
    // Wrap
    // ----------------------------------------------------------------------------

    /**
    * Return a key-value pair in plain text
    * @param array $keys
    */
    function wrapKv($keys) {

        $this->debug($keys, 'Wrapped key/vals');

        if (headers_sent())
            throw new Exception('wrapKv: Headers already sent');

        header('Content-Type: text/plain; charset=utf-8');
        foreach ($keys as $key => $value) {
            printf("%s:%s\n", $key, $value);
        }

        exit(0);
    }


    /**
    * Return an HTML refresh, with OpenID keys
    * @param string $url
    * @param array $keys
    */
    function wrapLocation($url, $keys) {

        $keys = $this->appendOpenID($keys);
        $this->debug($keys, 'Location keys');
        $q = mb_strpos($url, '?') ? '&' : '?';

        if (headers_sent())
            throw new Exception('wrapLocation: Headers already sent');

        header('Location: ' . $url . $q . http_build_query($keys));

        $this->debug('Location: ' . $url . $q . http_build_query($keys));

        exit(0);
    }


    /**
    * Return HTML
    * @global string $charset
    * @param string $message
    */
    function wrapHtml($message) {

        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
        <html>
        <head>
        <title>suxOpenID</title>
        <link rel="openid.server" href="' . $this->profile['req_url'] . '" />
        <link rel="openid.delegate" href="' . $this->profile['idp_url'] . '" />
        <meta content="text/html; charset=utf-8" http-equiv="content-type" />
        <meta name="robots" content="noindex,nofollow" />
        </head>
        <body>
        <p>' . $message . '</p>
        </body>
        </html>
        ';

        exit(0);
    }


    /**
    * Return an HTML refresh
    * @global string $charset
    * @param string $url
    */
    function wrapRefresh($url) {

        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
        <html>
        <head>
        <title>suxOpenID</title>
        <meta content="text/html; charset=utf-8" http-equiv="content-type" />
        <meta http-equiv="refresh" content="0;url=' . $url . '">
        <meta name="robots" content="noindex,nofollow" />
        </head>
        <body>
        <p>Redirecting to <a href="' . $url . '">' . $url . '</a></p>
        </body>
        </html>
        ';

        $this->debug('Refresh: ' . $url);

        exit(0);
    }


    // ----------------------------------------------------------------------------
    // Errors
    // ----------------------------------------------------------------------------

    /**
    * Return an error message to the consumer
    * @param string $message
    */
    function errorGet($url, $message = 'Bad Request') {
        $this->wrapLocation($url, array('mode' => 'error', 'error' => $message));
    }


    /**
    * Return an error message to the consumer
    * @param string $message
    */
    function errorPost($message = 'Bad Request') {

        if (headers_sent())
            throw new Exception('errorPost: Headers already sent');

        header("HTTP/1.1 400 Bad Request");
        echo ('error:' . $message);
        exit(0);

    }


    /**
    * Return an error message to the user
    * @param string $message
    */
    function error400 ( $message = 'Bad Request' ) {
        header("HTTP/1.1 400 Bad Request");
        $this->wrapHtml($message);
    }


    /**
    * Return an error message to the user
    * @param string $message
    */
    function error403 ( $message = 'Forbidden' ) {
        header("HTTP/1.1 403 Forbidden");
        $this->wrapHtml($message);
    }


    /**
    * Return an error message to the user
    * @param string $message
    */
    function error500($message = 'Internal Server Error' ) {
        header("HTTP/1.1 500 Internal Server Error");
        $this->wrapHtml($message);
    }


    // ----------------------------------------------------------------------------
    // Exception Handler
    // ----------------------------------------------------------------------------


    /**
    * @param Exception $e an Exception class
    */
    function logAndDie(Exception $e) {

        if ($this->db && $this->inTransaction) {
            $this->db->rollback();
            $this->inTransaction = false;
        }

        $message = "suxOpenID Error: \n";
        $message .= $e->getMessage() . "\n";
        $message .= "File: " . $e->getFile() . "\n";
        $message .= "Line: " . $e->getLine() . "\n\n";
        $message .= "Backtrace: \n" . print_r($e->getTrace(), true) . "\n\n";
        die("<pre>{$message}</pre>");

    }


}

/*

-- Database

CREATE TABLE `openid_associations` (
  `id` int(11) NOT NULL auto_increment,
  `expiration` int(11) NOT NULL,
  `shared_secret` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1

*/


?>