<?php

/**
 * Class DocBot
 *
 * Replace some frequently used doc-bot commands if the bot is
 * missing from the channel for whatever reason
 */
class WPBot Extends Bot {
	private $theme_details       = array();
	public  $predefined_messages = array();

	private $plugin;

	function __construct() {
		parent::__construct();

		$this->plugin = new \WPBot\Plugins();

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
		if ( isset( $this->theme_details[ $msg->message ] ) ) {
			$message = sprintf(
				'%s: %s',
				$msg->user,
				$this->theme_details[ $msg->message ]
			);
		}
		else {
			$url    = 'https://wordpress.org/themes/' . str_replace( ' ', '-', $msg->message );
			$search = 'https://api.wordpress.org/themes/info/1.2/?action=query_themes&request[per_page]=1&request[fields][description]=0&request[search]=';

			if ( preg_match( "/-l\b/i", $msg->message ) ) {
				$msg->message = trim( str_replace( '-l', '', $msg->message ) );
				$cache = sprintf(
					'See a list of themes relating to %s at %s',
					$msg->message,
					$search . str_replace( ' ', '+', $msg->message )
				);
				$message      = sprintf(
					'%s: %s',
					$msg->user,
					$cache
				);

				$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );

				$this->theme_details[ $msg->message ] = $cache;

				return;
			}

			$first_pass = get_headers( $url, true );

			if ( isset( $first_pass['Status'] ) && ! stristr( $first_pass['Status'], '404 Not Found' ) ) {
				$message = sprintf(
					'%s: %s',
					$msg->user,
					$url
				);
				$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );

				$this->theme_details[ $msg->message ] = $url;

				return;
			}

			$resp = file_get_contents( $search . str_replace( ' ', '+', $msg->message ) );
			$result = json_decode( $resp );

			if ( isset( $result->themes ) && count( $result->themes ) > 0 ) {
				$cache = sprintf(
					'%s - %s',
					html_entity_decode( $result->themes[0]->name ),
					$result->themes[0]->homepage
				);

				$message = sprintf(
					'%s: %s',
					$msg->user,
					$cache
				);

				$this->theme_details[ $msg->message ] = $cache;
			} else {
				$message = sprintf(
					'%s: No results found',
					$msg->user
				);
			}
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

	function wpvulndb( $irc, $data ) {
		$msg = $this->message_split( $data );

		$api = file_get_contents( 'https://wpvulndb.com/api/v2/plugins/' . $msg->message );
		$api = json_decode( $api );

		if ( isset( $api->{ $msg->message } ) ) {
			$entity = $api->{ $msg->message };
		}

		if ( ! isset( $entity ) || empty( $entity->vulnerabilities ) ) {
			$message = sprintf(
				'%s: %s',
				$msg->user,
				'There are no known vulnerabilities for this plugin'
			);
		}
		else {
			$latest = end( $entity->vulnerabilities );

			$message = sprintf(
				'%s: %s',
				$msg->user,
				sprintf(
					'%s: %s%s',
					$latest->vuln_type,
					$latest->title,
					( ! empty( $latest->vuln ) ? sprintf( '(Fixed in %s)', $latest->vuln ) : '' )
				)
			);
		}

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function sucuri_scan( $irc, $data ) {
		$msg = $this->message_split( $data );
		$site = 'https://sitecheck.sucuri.net/';

		$params = array(
				'scan'   => $msg->message,
				'clear'  => 1,
				'json'   => 1
		);

		$request = new \cURL\Request( sprintf( '%s?%s', $site, http_build_query( $params ) ) );
		$request->getOptions()
			->set( CURLOPT_FOLLOWLOCATION, true )
			->set( CURLOPT_USERAGENT, 'IRC #WordPress - WPBot' )
			->set( CURLOPT_TIMEOUT, 30 )
			->set( CURLOPT_RETURNTRANSFER, true );

		$request->addListener( 'complete', function ( \cURL\Event $event ) use ( $irc, $data, $msg ) {
			$response = $event->response;
			$feed = json_decode( $response->getContent(), true );

			$problems = array();

			$scan           = $response->SCAN;
			$system         = $response->SYSTEM;
			$webapp         = $response->WEBAPP;
			$recomendations = $response->RECOMMENDATIONS;
			$blacklist      = $response->BLACKLIST;

			if ( isset( $webapp->WARN ) ) {
				foreach( $webapp->WARN AS $warn ) {
					$problems[] = $warn;
				}
			}
			foreach( $blacklist->INFO AS $bl ) {
				if ( ! stristr( $bl[0], 'Domain clean' ) ) {
					$problems[] = sprintf( '%s: %s', $bl[0], $bl[1] );
				}
			}

			if ( empty( $problems ) ) {
				$reply = 'No problems discovered on ' . $msg->message;
			}
			else {
				if ( count( $problems ) > 2 ) {
					$reply = sprintf(
						'Multiple issues detected, please visit https://sitecheck.sucuri.net/results/%s for details',
						$msg->message
					);
				}
				else {
					$reply = sprintf(
						'Problems detected (%s)',
						$msg->message
					);

					foreach( $problems AS $problem ) {
						$reply .= ' - ' . $problem;
					}
				}
			}

			$message = sprintf(
					'%s: %s',
					$msg->user,
					$reply
			);

			$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
		} );

		$id = -1;
		$waitForCurl = function( $irc ) use ( $request, &$id ) {
			if ( $id !== -1 ) {
				try {
					if ( $request->socketPerform() ) {
						$request->socketSelect();
					}
				} catch ( \cURL\Exception $e ) {
					$irc->unregisterTimeId( $id );
					$id = -1;
				}
			}
		};
		$id = $irc->registerTimeHandler( 1000, $waitForCurl );

		$request->socketPerform();
	}
}
