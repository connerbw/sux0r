<?php

/* Error reporting */

error_reporting (E_ALL | E_STRICT); // Error reporting for developers
// error_reporting (E_ALL ^ E_NOTICE); // Error reporting for hosts


/* Configuration variables */

// The auto-detected path to your sux0r installation.
// If you set this yourself, make sure to use a trailing slash!

$CONFIG['PATH'] = dirname(__FILE__) . '/';

// The url suffix to your site. For example, if your site is
// http://www.sux0r.org/ then '/' is appropriate. If your site is
// http://domain.com/my/sux0r/ then '/my/sux0r/ is the correct value.
// Make sure to use a trailing slash!

$CONFIG['URL'] = '/';

// An array containing PDO compatible database DSN [key => value] pairs.
// Sux0r is theoretically able to span multiple databases, but ordinarily
// there should be one ['default' => $dsn] pointing to your one database..

$CONFIG['DSN'] =  array(
    'default' => array('mysql:host=localhost;dbname=sux0r', 'root', 'root'),
    );

// The realm for Digest Access Authentication. This value, along with a
// username, encrypts and stores passwords as HA1 = MD5(username:realm:password)
// The realm should contain at least the name of the host performing the
// authentication and might additionally indicate the collection of users who
// have access. An example might be 'users@sux0r.org'
//
// *** WARNING ***
// If you change this value after the fact, all stored passwords will
// become invalid and need to be reset.
//
// For more infom see:
// http://en.wikipedia.org/wiki/Digest_access_authentication

$CONFIG['REALM'] = 'users@sux0r.org';

// Sux0r modules may cache templates, set the duration in seconds below.

$CONFIG['CACHE_LIFETIME'] = 3600;

// Timzeone, pick yours from the list available at http://php.net/manual/en/timezones.php

$CONFIG['TIMEZONE'] = 'America/Montreal';


/* Extra */

// Debug
include_once($CONFIG['PATH'] . 'includes/symbionts/dBug.php');

?>