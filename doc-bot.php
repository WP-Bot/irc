<?php

/**
 * Class DocBot
 *
 * Replace some frequently used doc-bot commands if the bot is
 * missing from the channel for whatever reason
 */
class WPBot Extends Bot {
	public  $predefined_messages = array();

	private $plugin;
	private $theme;

	function __construct() {
		parent::__construct();

		$this->plugin = new \WPBot\Plugins();
		$this->theme = new \WPBot\Themes();

		$this->prepare_predefined_messages();
	}

	function prepare_predefined_messages() {
		$wpbot_api = curl_init( 'https://wp-bot.net/wp-json/wpbot/v1/commands' );
		curl_setopt( $wpbot_api, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $wpbot_api, CURLOPT_SSL_VERIFYPEER, false );

		$result = curl_exec( $wpbot_api );
		curl_close( $wpbot_api );

		$this->predefined_messages = json_decode( $result );
	}

	function is_predefined_message( $irc, $data ) {
		if ( $data->message[0] == '.' || $data->message[0] == '!' ) {
			foreach ( $this->predefined_messages AS $predef ) {
				if ( empty( $predef->command ) ) {
					continue;
				}
				if ( preg_match( sprintf( "/^(!|\.)%s\b/i", $predef->command ), $data->message ) ) {
					$msg = $this->message_split( $data );

					$message = sprintf(
							'%s: %s',
							$msg->user,
							$predef->response
					);

					$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );

					return true;
				}
			}
		}
	}

	function google_result( $string ) {
		$search = 'http://www.google.com/search?q=%s&btnI';

		$string = urlencode( $string );
		$search = str_replace( '%s', $string , $search );

		$headers = get_headers( $search, true );
		return $headers['Location'][1];
	}

	function developer( $irc, $data ) {
		$msg = $this->message_split( $data );
		$string = trim( $msg->message );

		$search = 'https://developer.wordpress.org/?s=%s';
		$lookup = false;
		if ( stristr( $string, '-f' ) ) {
			$lookup = true;
			$string = str_replace( '-f', '', $string );
			$search .= '&post_type%5B%5D=wp-parser-function';
		}
		if ( stristr( $string, '-h' ) ) {
			$lookup = true;
			$string = str_replace( '-h', '', $string );
			$search .= '&post_type%5B%5D=wp-parser-hook';
		}
		if ( stristr( $string, '-c' ) ) {
			$lookup = true;
			$string = str_replace( '-c', '', $string );
			$search .= '&post_type%5B%5D=wp-parser-class';
		}
		if ( stristr( $string, '-m' ) ) {
			$lookup = true;
			$string = str_replace( '-m', '', $string );
			$search .= '&post_type%5B%5D=wp-parser-method';
		}

		if ( ! $lookup ) {
			$search .= '&post_type%5B%5D=wp-parser-function';
		}

		$string = trim( $string );
		$string = str_replace( array( ' ' ), array( '+' ), $string );
		$search = str_replace( '%s', $string , $search );

		$headers = get_headers( $search, true );

		if ( ! isset( $headers['Location'] ) || empty( $headers['Location'] ) ) {
			$message = sprintf(
				'%s: No exact match found for \'%s\' - See the full set of results at %s',
				$msg->user,
				$string,
				$search
			);
		}
		else {
			$message = sprintf(
				'%s: %s',
				$msg->user,
				$headers['Location']
			);
		}

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function codex( $irc, $data ) {
		$msg = $this->message_split( $data );

		$google = $this->google_result( $msg->message . ' site:codex.wordpress.org' );

		if ( preg_match( '/codex\.wordpress\.org\/(.{2,5}:).+?/i', $google, $language ) ) {
			$google = str_ireplace( $language[1], '', $google );
		}

		$message = sprintf(
			'%s: %s',
			$msg->user,
			$google
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function plugin( $irc, $data ) {
		$msg = $this->message_split( $data );

		$plugin = $this->plugin->search( $msg->message );

		if ( false === $plugin ) {
			$message = sprintf(
				'%s: No results found',
				$msg->user
			);
		} else {
			$message = sprintf(
				'%s: %s - %s',
				$msg->user,
				$plugin->name,
				sprintf(
					'https://wordpress.org/plugins/%s',
					$plugin->slug
				)
			);
		}

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function theme( $irc, $data ) {
		$msg = $this->message_split( $data );

		$theme = $this->theme->search( $msg->message );

		if ( false === $theme ) {
			$message = sprintf(
				'%s: No results found',
				$msg->user
			);
		} else {
			$message = sprintf(
				'%s: %s - %s',
				$msg->user,
				$theme->name,
				sprintf(
					'https://wordpress.org/themes/%s',
					$theme->slug
				)
			);
		}

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function google( $irc, $data ) {
		$msg = $this->message_split( $data );

		$google = $this->google_result( $msg->message );

		$message = sprintf(
			'%s: Google result for %s - %s',
			$msg->user,
			$msg->message,
			$google
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function lmgtfy( $irc, $data ) {
		$msg = $this->message_split( $data );

		$query = urlencode( $msg->message );

		$message = sprintf(
			'%s: http://lmgtfy.com/?q=%s',
			$msg->user,
			$query
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function language( $irc, $data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Please help us keep %s a family friendly room, and avoid using foul language.',
			$msg->user,
			$data->channel
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function count( $irc, $data ) {
		$counter = file_get_contents( 'https://wordpress.org/download/counter/?ajaxupdate=1' );

		$message = sprintf(
			'The latest version of WordPress has been downloaded %s times',
			$counter
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function md5( $irc, $data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: %s',
			$msg->user,
			md5( $msg->message )
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function donthack( $irc, $data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: http://codex.wordpress.org/images/b/b3/donthack.jpg',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function trac_ticket( $irc, $data ) {
		$msg = $this->message_split( $data );

		preg_match_all( '/#([0-9]+?)\b/si', $msg->message, $tickets );

		foreach( $tickets[1] AS $ticket ) {
			$url = sprintf( 'https://core.trac.wordpress.org/ticket/%d', $ticket );

			$message = sprintf(
				'%s: %s',
				$msg->user,
				$url
			);

			$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
		}
	}

	function trac_changeset( $irc, $data ) {
		$msg = $this->message_split( $data );

		preg_match_all( '/r([0-9]+?)\b/si', $msg->message, $changes );

		foreach( $changes[1] AS $change ) {
			$url = sprintf( 'https://core.trac.wordpress.org/changeset/%d', $change );

			$message = sprintf(
				'%s: %s',
				$msg->user,
				$url
			);

			$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
		}
	}
}
