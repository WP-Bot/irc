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
require_once ABSPATH . '/includes/WPBot.php';

/**
 * Instantiate our bot class and the SmartIRC framework
 */
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
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/./', $irc, 'spam_protection' );

/**
 * Garbage collection for the spam protection.
 */
$irc->registerTimeHandler( 100000, $irc, 'spam_protection_gc' );

/**
 * Set up hooks for events to trigger on in the WPBot class.
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/./', $irc, 'channel_query' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/^(!|\.)tell\b/', $irc, 'add_tell' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/^(!|\.)ops/', $irc, 'request_ops' );
$irc->registerActionHandler( SMARTIRC_TYPE_QUERY, '/^(!|\.)ops/', $irc, 'request_ops' );
$irc->registerActionHandler( SMARTIRC_TYPE_ACTION, '/./', $irc, 'channel_query' );
$irc->registerActionHandler( SMARTIRC_TYPE_KICK, '/./', $irc, 'log_kick' );
$irc->registerActionHandler( SMARTIRC_TYPE_PART, '/./', $irc, 'log_part' );
$irc->registerActionHandler( SMARTIRC_TYPE_QUIT, '/./', $irc, 'log_quit' );
$irc->registerActionHandler( SMARTIRC_TYPE_JOIN, '/(.*)/', $irc, 'log_join' );
$irc->registerActionHandler( SMARTIRC_TYPE_JOIN, '/(.*)/', $irc, 'new_user_guidelines' );
$irc->registerActionHandler( SMARTIRC_TYPE_QUERY, '/^rejoin$/', $irc, 'maybe_rejoin_channels' );

/**
 * Generic commands associated purely with WPBot
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)h(elp)?\b', $irc, 'help_cmd' );

/**
 * DocBot class hooks
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)d(eveloper)?\b', $irc, 'developer' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)c(odex)?\b', $irc, 'developer' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)p(lugin)?\b', $irc, 'plugin' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)t(heme)?\b', $irc, 'theme' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)g(oogle)?\b', $irc, 'google' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)l(mgtfy)?\b', $irc, 'lmgtfy' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)language\b', $irc, 'language' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)count\b', $irc, 'count' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)md5\b', $irc, 'md5' );

/**
 * DocBot common replies
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/./', $irc, 'is_predefined_message' );
$irc->registerTimeHandler( 600000, $irc, 'prepare_predefined_messages' );

/**
 * Scheduled task runners
 */
$irc->registerTimeHandler( 60000, $irc, 'maybe_unmute_users' );
$irc->registerTimeHandler( 60000, $irc, 'maybe_self_update' );
$irc->registerTimeHandler( 900000, $irc, 'look_for_news' ); // Look for news every 5 minutes.
$irc->registerTimeHandler( 900000, $irc, 'maybe_rejoin_channels' );

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
