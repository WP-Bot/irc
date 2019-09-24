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
 * Slack settings
 */
define( 'SLACK_API', '' );
define( 'SLACK_RICH_LOG', false );

/**
 * Configuration options for spam protection
 */
define( 'SPAM_REPEATS', 5 ); // How many times a line can be repeated before they are automatically kicked.
define( 'SPAM_AUTO_BAN', 2 ); // How many times a user can be kicked or muted before they are instead banned.
define( 'SPAM_MEMORY', 1 ); // How many lines of text to remember and compare against from each user.
define( 'SPAM_LINES', 3 ); // How many lines a user can say in rapid succession before they are muted.
define( 'SPAM_LINES_SECONDS', 1 ); // How many seconds the SPAM_LINES can occur within to trigger.

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
