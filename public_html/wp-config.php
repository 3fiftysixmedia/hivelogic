<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */



// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('WP_CACHE', true);
define( 'WPCACHEHOME', '/home/sites/hivelogic.co.uk/public_html/wp-content/plugins/wp-super-cache/' );
define('DB_NAME', 'cl21-angelos');

/** MySQL database username */
define('DB_USER', 'cl21-angelos');

/** MySQL database password */
define('DB_PASSWORD', 'r4ngenwH/');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

define('WP_HOME','http://www.hivelogic.co.uk');
define('WP_SITEURL','http://www.hivelogic.co.uk');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'D,}XdEdxLy.XNf6NEs/:M-Hm9;6luitF+w9%c|nEsA9I$asS^z]P N,|0f#FWJnR');
define('SECURE_AUTH_KEY',  '~r17X).lyfeL7EN?}H5T9qYUrQEUFa*)l.&E0#gfs8$-LJZO9Y![/+@vgQ)<SLej');
define('LOGGED_IN_KEY',    'A4+S8$hK_mCm3$(q6R8H@_wFKe1Q`,5QF&TnR(]It5S;8q&6}u1;+Taj#z>?3EMX');
define('NONCE_KEY',        'vmgJl dB/^V>gdh;|~EtUM=bRIs;#p8<3*fH?Rxmn$L|,]P06zX5<*N$FN;/6*$c');
define('AUTH_SALT',        'W~-_{I/^bPo[:eZ`&RVui;n0&B46Ta6zTrYd@v>W|6,?$@<;q93OF`{Dr*L</L5t');
define('SECURE_AUTH_SALT', '7NggPGn-k.2isGFWEod_vy`|M~IieA7zdhOuAT-~J2TUwc(VteDe u;-&|q_#tcJ');
define('LOGGED_IN_SALT',   '|r[l~K=(&#bu%4vm}:;u+|tJ2QOWF_,[|ety5rooG[C}mufX;u&VG8Pswgo8 D~B');
define('NONCE_SALT',       ' Ut=>!EHU~lrpQj@yY1eZ|6ivLqvJE><`vUZay:pPxUh_,,N>+ii:>BaTQ,N>L{a');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', true);
define( 'WP_MEMORY_LIMIT', '256M' );

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
