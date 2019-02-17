<?php

define( 'BOTVERSION', '0.1 Alpha' );
define( 'BOTNICK', 'WPBot' );
define( 'BOTNAME', 'WordPress Bot' );
define( 'BOTPASS', '' );

define( 'IRC_NETWORK', 'irc.freenode.net' );
define( 'IRC_PORT', 6667 );
define( 'IRC_CHANNELS', '#wordpress' );
define( 'HELP_URL', 'http://www.wp-bot.net/');

/**
 * Configuration options for spam protection
 */
define( 'SPAM_REPEATS', 5 ); // How many times a line can be repeated before they are automatically kicked.
define( 'SPAM_AUTO_BAN', 2 ); // How many times a user can be kicked before they are instead banned.

/**
 * Comma separated list of strings used to show appreciation
 */
define( 'APPRECIATION', 'thx,thank,cheers' );

/**
 * Database definitions
 */
define( 'DB_HOST', '' );
define( 'DB_USER', '' );
define( 'DB_PASS', '' );
define( 'DB_NAME', '' );
