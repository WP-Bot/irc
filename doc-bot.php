<?php

/**
 * Class DocBot
 *
 * Replace some frequently used doc-bot commands if the bot is
 * missing from the channel for whatever reason
 */
class WPBot Extends Bot {
	private $plugin_details      = array();
	public  $predefined_messages = array();

	function __construct() {
		parent::__construct();

		$this->prepare_predefined_messages();
	}

	function prepare_predefined_messages() {
		$new_predefs = array();

		$this->pdo_ping();

		try {
			$entries = $this->db->query( "
				SELECT 
					p.post_title, 
					m.meta_value
				FROM 
					wp_posts p
				LEFT JOIN 
					wp_postmeta m 
						ON ( m.post_id = p.ID ) 
				WHERE 
					p.post_status =  'publish'
				AND 
					p.post_type =  'wpbot_commands'
				AND 
					m.meta_key =  '_wpbot_command'
			" );
			while ( $entry = $entries->fetchObject() ) {
				$meta = unserialize( $entry->meta_value );

				$new_predefs[] = array(
						'pattern'  => $meta['trigger'],
						'response' => $meta['response'],
						'uri'      => $meta['uri']
				);
			}

			$this->predefined_messages = $new_predefs;
		} catch( PDOException $e ) {
			echo 'PDO Exception: ' . $e->getMessage();
		}
	}

	function is_predefined_message( &$irc, &$data ) {
		if ( $data->message[0] == '.' || $data->message[0] == '!' ) {
			foreach ( $this->predefined_messages AS $predef ) {
				if ( empty( $predef['pattern'] ) ) {
					continue;
				}
				if ( preg_match( sprintf( "/^(!|\.)%s\b/i", $predef['pattern'] ), $data->message ) ) {
					$msg = $this->message_split( $data );

					$message = sprintf(
							'%s: %s',
							$msg->user,
							$predef['response']
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

	function developer( &$irc, &$data ) {
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

	function codex( &$irc, &$data ) {
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

	function plugin( &$irc, &$data ) {
		$msg = $this->message_split( $data );
		if ( isset( $this->plugin_details[ $msg->message ] ) ) {
			$message = sprintf(
				'%s: %s',
				$msg->user,
				$this->plugin_details[ $msg->message ]
			);
		}
		else {
			$url    = 'https://wordpress.org/plugins/' . str_replace( ' ', '-', $msg->message );
			$search = 'https://wordpress.org/plugins/search.php?q=';

			if ( preg_match( "/-l\b/i", $msg->message ) ) {
				$msg->message = trim( str_replace( '-l', '', $msg->message ) );
				$cache = sprintf(
					'See a list of plugins relating to %s at %s',
					$msg->message,
					$search . str_replace( ' ', '+', $msg->message )
				);
				$message      = sprintf(
					'%s: %s',
					$msg->user,
					$cache
				);

				$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );

				$this->plugin_details[ $msg->message ] = $cache;

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

				$this->plugin_details[ $msg->message ] = $url;

				return;
			}

			$page = file_get_contents( $search . str_replace( ' ', '+', $msg->message ) );
			preg_match_all( "/plugin-card-top.+?column-name.+?<a.+?href=\"(.+?)\">(.+?)</msi", $page, $matches );

			if ( ! empty( $matches[1] ) ) {
				$cache = sprintf(
					'%s - %s',
					$matches[2][0],
					$matches[1][0]
				);

				$message = sprintf(
					'%s: %s',
					$msg->user,
						$cache
				);

				$this->plugin_details[ $msg->message ] = $cache;
			} else {
				$message = sprintf(
					'%s: No results found',
					$msg->user
				);
			}
		}

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function google( &$irc, &$data ) {
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

	function lmgtfy( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$query = urlencode( $msg->message );

		$message = sprintf(
			'%s: http://lmgtfy.com/?q=%s',
			$msg->user,
			$query
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function language( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Please help us keep %s a family friendly room, and avoid using foul language.',
			$msg->user,
			$data->channel
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function count( &$irc, &$data ) {
		$counter = file_get_contents( 'https://wordpress.org/download/counter/?ajaxupdate=1' );

		$message = sprintf(
			'The latest version of WordPress has been downloaded %s times',
			$counter
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function md5( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: %s',
			$msg->user,
			md5( $msg->message )
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function donthack( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: http://codex.wordpress.org/images/b/b3/donthack.jpg',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function trac_ticket( &$irc, &$data ) {
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

	function trac_changeset( &$irc, &$data ) {
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

	function wpvulndb( &$irc, &$data ) {
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

	function sucuri_scan( &$irc, &$data ) {
		$msg = $this->message_split( $data );
		$site = 'https://sitecheck.sucuri.net/';

		$params = array(
				'scan'   => $msg->message,
				'clear'  => 1,
				'json'   => 1
		);

		$curl = curl_init();

		curl_setopt_array( $curl, array(
				CURLOPT_URL            => sprintf( '%s?%s', $site, http_build_query( $params ) ),
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_CUSTOMREQUEST  => 'GET',
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_SSL_VERIFYPEER => 0,
				CURLOPT_USERAGENT      => 'IRC #WordPress - WPBot',
		) );

		$response = curl_exec( $curl );
		$response = json_decode( $response );

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

		curl_close( $curl );

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
	}
}
