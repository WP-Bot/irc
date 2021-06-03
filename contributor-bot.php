<?php

namespace WPBot;

define( 'ABSPATH', dirname( __FILE__ ) );

require_once ABSPATH . '/vendor/autoload.php';

// Check if the config exists one directory up
if ( file_exists( ABSPATH . '/../config.php' ) ) {
	require_once ABSPATH . '/../config.php';
} elseif ( file_exists( ABSPATH . '/config.php' ) ) {
	require_once ABSPATH . '/config.php';
} else {
	// If no config was found, throw an exception
	throw new \Exception( 'Could not find any config.php file.' );
}

/**
 * Grab dependencies
 */
require_once ABSPATH . '/includes/Plugins.php';
require_once ABSPATH . '/includes/Themes.php';
require_once ABSPATH . '/includes/News.php';
require_once ABSPATH . '/includes/Tools.php';
require_once ABSPATH . '/includes/DocBot.php';
require_once ABSPATH . '/includes/WPBot.php';

/**
 * Instantiate our bot class and the SmartIRC framework
 */
$bot = new DocBot();
$irc = new WPBot();

/**
 * Set connection-wide configurations
 */
$irc->setDebugLevel( SMARTIRC_DEBUG_ALL ); // Set debug mode
$irc->setUseSockets( true ); // We want to use actual sockets, if this is false fsock will be used, which is not as ideal
$irc->setChannelSyncing( true ); // Channel sync allows us to get user details which we use in our logs, this is how we can check if users are in the channel or not

/**
 * Spam protection
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/./', $bot, 'spam_protection' );

/**
 * Garbage collection for the spam protection.
 */
$irc->registerTimeHandler( 100000, $bot, 'spam_protection_gc' );

/**
 * Set up hooks for events to trigger on
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/./', $bot, 'channel_query' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/^(!|\.)tell\b/', $bot, 'add_tell' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/^(!|\.)ops/', $bot, 'request_ops' );
$irc->registerActionHandler( SMARTIRC_TYPE_QUERY, '/^(!|\.)ops/', $bot, 'request_ops' );
$irc->registerActionHandler( SMARTIRC_TYPE_ACTION, '/./', $bot, 'channel_query' );
$irc->registerActionHandler( SMARTIRC_TYPE_KICK, '/./', $bot, 'log_kick' );
$irc->registerActionHandler( SMARTIRC_TYPE_PART, '/./', $bot, 'log_part' );
$irc->registerActionHandler( SMARTIRC_TYPE_QUIT, '/./', $bot, 'log_quit' );
$irc->registerActionHandler( SMARTIRC_TYPE_JOIN, '/(.*)/', $bot, 'log_join' );
$irc->registerActionHandler( SMARTIRC_TYPE_JOIN, '/(.*)/', $bot, 'new_user_guidelines' );

/**
 * Generic commands associated purely with WPBot
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)h(elp)?\b', $bot, 'help_cmd' );

/**
 * DocBot class hooks
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)d(eveloper)?\b', $bot, 'developer' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)c(odex)?\b', $bot, 'developer' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)p(lugin)?\b', $bot, 'plugin' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)t(heme)?\b', $bot, 'theme' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)g(oogle)?\b', $bot, 'google' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)l(mgtfy)?\b', $bot, 'lmgtfy' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)language\b', $bot, 'language' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)count\b', $bot, 'count' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)md5\b', $bot, 'md5' );

/**
 * DocBot common replies
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/./', $bot, 'is_predefined_message' );
$irc->registerTimeHandler( 600000, $bot, 'prepare_predefined_messages' );

/**
 * Scheduled task runners
 */
$irc->registerTimeHandler( 60000, $bot, 'maybe_unmute_users' );
$irc->registerTimeHandler( 60000, $bot, 'maybe_self_update' );
$irc->registerTimeHandler( 900000, $bot, 'look_for_news' ); // Look for news every 5 minutes.

/**
 * Start the connection to an IRC server
 */
if ( defined( 'USE_SASL' ) && USE_SASL ) {
	$irc->connect( IRC_NETWORK, IRC_PORT );
	$irc->sendSASL();
} else {
	$irc->connect( IRC_NETWORK, IRC_PORT );
	$irc->login( BOTNICK, BOTNAME . ' - version ' . BOTVERSION, 0, BOTNICK, BOTPASS );
	$irc->join( explode( ',', IRC_CHANNELS ) );
}
$irc->listen();

/**
 * Shut down and clean up once we've disconnected
 */
$irc->disconnect();
