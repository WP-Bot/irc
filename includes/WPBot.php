<?php

namespace WPBot;

class WPBot extends \Net_SmartIRC {
	public $appreciation = array();
	public $tell         = array();
	public $db;

	private $spam_check         = array();
	private $spam_kicked        = array();
	private $spam_muted         = array();
	private $spam_repeats       = 5;
	private $spam_auto_ban      = 2;
	private $spam_lines         = 5;
	private $spam_lines_seconds = 3;

	private $news;

	private $mtime = array(
		'doc-bot'         => null,
		'contributor-bot' => null,
	);

	private $sasl_auth = false;

	/**
	 * The class construct prepares our functions and database connections
	 */
	function __construct() {
		/**
		 * Prepare our initial database connection
		 */
		$this->db_connector();

		/**
		 * We replace the comma separated list of appreciative terms with pipes
		 * This is done because we run a bit of regex over it to identify words for consistency
		 */
		$this->appreciation = str_replace( ',', '|', strtolower( APPRECIATION ) );

		/**
		 * Set modification time to allow for self updating when needed.
		 */
		foreach ( $this->mtime as $file => $mtime ) {
			if ( empty( $file ) ) {
				continue;
			}
			$this->mtime[ $file ] = filemtime( __DIR__ . '/' . $file . '.php' );
		}

		/**
		 * Add spam protection config overrides, if they are set.
		 */
		if ( defined( 'SPAM_REPEATS' ) ) {
			$this->spam_repeats = SPAM_REPEATS;
		}
		if ( defined( 'SPAM_AUTO_BAN' ) ) {
			$this->spam_auto_ban = SPAM_AUTO_BAN;
		}
		if ( defined( 'SPAM_LINES' ) ) {
			$this->spam_lines = SPAM_LINES;
		}
		if ( defined( 'SPAM_LINES_SECONDS' ) ) {
			$this->spam_lines_seconds = SPAM_LINES_SECONDS;
		}

		$this->news = new \WPBot\News();

		$this->prepare_tell_notifications();
	}

	function db_connector() {
		/**
		 * Prepare our database connection
		 */
		$attributes = array(
			\PDO::ATTR_PERSISTENT => true,
			\PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION
		);

		$this->db = new \PDO( 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS, $attributes );
	}

	function pdo_ping() {
		try {
			$this->db->query( "SELECT 1" );
		} catch ( \PDOException $e ) {
			$this->db_connector();
		}
	}

	/**
	 * Function for cleaning up nicknames. Clears out commonly used characters
	 * that are not valid in a nickname but are often used in relation with them
	 *
	 * @param $nick
	 *
	 * @return string
	 */
	function cleanNick( $nick ) {
		return str_replace( array( '@', '%', '+', '~', ':', ',', '<', '>' ), '', $nick );
	}

	function channel_query( $irc, $data ) {
		$is_docbot       = false;
		$is_question     = false;
		$is_appreciation = false;

		if ( '?' == substr( trim( $data->message ), - 1 ) ) {
			$is_question = true;
		}

		if ( preg_match( "/(" . $this->appreciation . ")/i", $data->message ) ) {
			$is_appreciation = array();

			$string = explode( " ", $data->message );
			foreach ( $string AS $word ) {
				$word = $this->cleanNick( $word );

				if ( $irc->isJoined( $data->channel, $word ) ) {
					$is_appreciation[] = $word;
				}
			}

			/**
			 * If no users are mentioned in the appreciative message,
			 * there's no reason for us to try and track it
			 */
			if ( empty( $is_appreciation ) ) {
				$is_appreciation = false;
			}
		}

		/**
		 * We look to identify doc-bot references only if we've not already done a successful match
		 */
		if ( ! $is_appreciation && ! $is_question ) {
			/**
			 * If block denoting if the first letter is the doc-bot command trigger
			 */
			if ( '.' == substr( $data->message, 0, 1 ) ) {
				$string  = explode( " ", $data->message );
				$is_nick = $this->cleanNick( array_pop( $string ) );

				/**
				 * If the last word is a user on the channel, this was a reference sent to help a user
				 */
				if ( $irc->isJoined( $data->channel, $is_nick ) ) {
					$is_appreciation = array( $data->nick );
					$is_docbot       = $is_nick;
				}
			}
		}

		/**
		 * Ping the server first to make sure we still have a connection
		 */
		$this->pdo_ping();

		try {
			/**
			 * Insert the log entry
			 */
			$this->db->query( "
				INSERT INTO
					messages (
						userhost,
						nickname,
						message,
						event,
						channel,
						is_question,
						is_docbot,
						is_appreciation,
						time
					)
				VALUES (
					" . $this->db->quote( $data->nick . "!" . $data->ident . "@" . $data->host ) . ",
					" . $this->db->quote( $data->nick ) . ",
					" . $this->db->quote( $data->message ) . ",
					'message',
					" . $this->db->quote( $data->channel ) . ",
					" . $this->db->quote( ( $is_question ? 1 : 0 ) ) . ",
					" . $this->db->quote( ( ! $is_docbot ? null : $is_docbot ) ) . ",
					" . $this->db->quote( ( is_array( $is_appreciation ) ? serialize( $is_appreciation ) : null ) ) . ",
					" . $this->db->quote( date( "Y-m-d H:i:s" ) ) . "
				)
			" );
		} catch ( PDOException $e ) {
			echo 'PDO Exception: ' . $e->getMessage();
		}
	}

	function message_split( $data ) {
		$message_parse = explode( ' ', $data->message, 2 );
		$command = $message_parse[0];
		$message_parse = ( count( $message_parse ) > 1 ? $message_parse[1] : '' );

		$user = $data->nick;

		$message_parse = explode( '>', $message_parse );
		if ( isset( $message_parse[1] ) && ! empty( $message_parse[1] ) ) {
			$send_to = trim( $message_parse[1] );
			$user = $send_to;
		}
		$message = trim( $message_parse[0] );

		$result = (object) array(
			'user'    => $user,
			'message' => $message,
			'command' => $command
		);

		return $result;
	}

	function verify_own_nickname( $irc ) {
		if ( BOTNICK != $irc->_nick ) {
			$irc->login( BOTNICK, BOTNAME . ' - version ' . BOTVERSION, 0, BOTNICK, BOTPASS );
		}
	}

	function log_event( $event, $irc, $data ) {
		$this->verify_own_nickname( $irc );
		$this->pdo_ping();

		try {
			$this->db->query( "
				INSERT INTO
					messages (
						userhost,
						nickname,
						message,
						event,
						channel,
						time
					)
				VALUES (
					" . $this->db->quote( $data->nick . "!" . $data->ident . "@" . $data->host ) . ",
					" . $this->db->quote( $data->nick ) . ",
					" . $this->db->quote( $data->message ) . ",
					" . $this->db->quote( $event ) . ",
					" . $this->db->quote( $data->channel ) . ",
					" . $this->db->quote( date( "Y-m-d H:i:s" ) ) . "
				)
			" );
		} catch ( \PDOException $e ) {
			echo 'PDO Exception: ' . $e->getMessage();
		}
	}

	function log_kick( $irc, $data ) {
		$this->log_event( 'kick', $irc, $data );
	}

	function log_part( $irc, $data ) {
		$this->log_event( 'part', $irc, $data );
	}

	function log_quit( $irc, $data ) {
		$this->log_event( 'quit', $irc, $data );
	}

	function log_join( $irc, $data ) {
		$this->log_event( 'join', $irc, $data );

		if ( $data->nick == $irc->_nick ) {
			$irc->message( SMARTIRC_TYPE_QUERY, 'ChanServ', 'op #WordPress ' . $irc->_nick );
		}

		$this->tell( $irc, $data );
	}

	function help_cmd( $irc, $data ) {
		$message = sprintf( 'For WPBot Help, see %s',
			HELP_URL
		);
		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function prepare_tell_notifications() {
		$this->tell = array();

		$this->pdo_ping();

		try {
			$entries = $this->db->query( "
				SELECT
					t.id,
					t.time,
					t.recipient,
					t.sender,
					t.message
				FROM
					tell t
				WEHERE
					t.told = 0
			" );

			while ( $entry = $entries->fetchObject() ) {
				$this->add_tell_notification( $entry->id, $entry->recipient, $entry->time, $entry->sender, $entry->message );
			}
		} catch( \PDOException $e ) {
			echo 'PDO Exception: ' . $e->getMessage();
		}
	}

	function add_tell_notification( $id, $recipient, $time, $sender, $message ) {
		if ( ! isset( $this->tell[ $recipient ] ) ) {
			$this->tell[ $recipient ] = array();
		}

		$this->tell[ $recipient ][] = (object) array(
			'id'      => $id,
			'time'    => $time,
			'sender'  => $sender,
			'message' => $message
		);
	}

	function add_tell( $irc, $data ) {
		$msg = Tools::message_split( $data );

		$words              = explode( ' ', $data->message );
		$delimited_position = count( $words ) - 2;

		if ( '>' != $words[ $delimited_position ] ) {
			$msg->user = $words[1];
			array_shift( $words );
			array_shift( $words );
			$msg->message = implode( ' ', $words );
		}

		$this->pdo_ping();

		$time = date( "Y-m-d H:i:s" );

		try {
			$this->db->query( "
				INSERT INTO
					tell (
						`time`,
						`recipient`,
						`sender`,
						`message`
					)
				VALUES (
					" . $this->db->quote( $time ) . ",
					" . $this->db->quote( $msg->user ) . ",
					" . $this->db->quote( $data->nick ) . ",
					" . $this->db->quote( $msg->message ) . "
				)
			" );

			$id = $this->db->lastInsertId();

			$this->add_tell_notification( $id, $msg->user, $time, $data->nick, $msg->message );

			$message = sprintf(
				'%s: I will relay your message to %s when I see them next.',
				$data->nick,
				$msg->user
			);
		} catch ( \PDOException $e ) {
			echo 'PDO Exception: ' . $e->getMessage();

			$message = sprintf(
				'%s: I cannot relay your message to %s right now. My database is broken.',
				$data->nick,
				$msg->user
			);
		}

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function tell( $irc, $data ) {
		if ( isset( $this->tell[ $data->nick ] ) ) {
			$unset = array();
			foreach( $this->tell[ $data->nick ] AS $tell ) {
				$message = sprintf(
					'(Tell) %s - %s @ %s: %s',
					$data->nick,
					$tell->sender,
					date( "Y-m-d H:i", strtotime( $tell->time ) ),
					$tell->message
				);

				$unset[] = $tell->id;

				$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
			}

			unset( $this->tell[ $data->nick ] );

			$this->pdo_ping();

			try {
				$this->db->query( "
					UPDATE
						tell t
					SET
						t.told = 1
					WHERE
						t.id IN (" . implode( ',', $unset ) . ")
				" );
			} catch ( \PDOException $e ) {
				echo 'PDO Exception: ' . $e->getMessage();
			}
		}
	}

	function new_user_guidelines( $irc, $data ) {
		$message = sprintf(
			'Welcome to #WordPress, %s. Please review our guidelines available at %s, and if you at any time see behavior you feel is inappropriate, you may utilize the %s command, either in the channel or in a private message to notify a Support Team member.',
			$data->nick,
			'https://wordpress.org/support/guidelines/irc/',
			chr(2) . '.ops' . chr(2)
		);

		$irc->message( SMARTIRC_TYPE_NOTICE, $data->nick, $message );
	}

	function request_ops( $irc, $data ) {
		// Break out early if there's no Slack API
		if ( ! defined( 'SLACK_API' ) || empty( SLACK_API ) ) {
			return;
		}

		try {
			// Get the most recent log event from the channel for use in our Slack message
			$last_entry = $this->db->query( "
				SELECT
					m.id
				FROM
					messages m
				WHERE
					event = 'message'
				ORDER BY
					m.id DESC
				LIMIT 1
			" );
			$last_entry = $last_entry->fetchObject();
		} catch ( \PDOException $e ) {
			echo 'PDO Exception: ' . $e->getMessage();
		}

		/*
		 * Log the use of the command
		 * This is to avoid abuse of the system and allow us to move in and block abusing users
		 */
		$this->log_event( 'mod_request', $irc, $data );

		$msg = Tools::message_split( $data );

		$log_link = sprintf(
			'http://logs.wp-bot.net/?date=%s#%d',
			date( "Y-m-d" ),
			$last_entry->id
		);

		$note = ( ! empty( $msg->message ) ? sprintf( ' [%s]', $msg->message ) : '' );

		$simple_message = sprintf(
			'*IRC* assistance requested in <%s|#WordPress>%s - <%s|See logs>',
			'https://webchat.freenode.net/?channels=#wordpress',
			$note,
			$log_link
		);

		if ( defined( 'SLACK_RICH_LOG' ) && SLACK_RICH_LOG ) {
			$this->send_rich_slack_alert( $message, $log_link, $note );
		} else {
			$this->send_slack_alert( $simple_message );
		}

		$reply = "Your request for an operator to look into the current channel situation has been forwarded to the WordPress Support Team.";

		$irc->message( SMARTIRC_TYPE_QUERY, $data->nick, $reply );
	}


	function send_slack_alert( $message ) {
		// Break out early if there's no Slack API
		if ( ! defined( 'SLACK_API' ) ) {
			return;
		}

		$request = array(
			'channel'    => '#forums',
			'username'   => 'IRC WPBot Notifier',
			'icon_emoji' => ':hash:',
			'text'       => $message,
		);

		$request = json_encode( $request );

		$slack = curl_init( SLACK_API );
		curl_setopt( $slack, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $slack, CURLOPT_POSTFIELDS, "payload=" . $request );
		curl_setopt( $slack, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $slack, CURLOPT_SSL_VERIFYPEER, false );

		$result = curl_exec( $slack );
		if ( false === $result ) {
			echo "Slack cURL error: " . curl_error( $slack ) . "\n";
			echo $request . "\n";
		}

		curl_close( $slack );
	}

	function send_rich_slack_alert( $message, $log_link, $note = null ) {
		// Break out early if there's no Slack API
		if ( ! defined( 'SLACK_API' ) || empty( SLACK_API ) ) {
			return;
		}

		$logs = $this->get_logs( 5 );
		$logs = array_reverse( $logs );

		$fields = array();
		if ( ! empty( $note ) ) {
			$fields[] = array(
				'title'  => 'Note',
				'value'  => $note,
				'short'  => false
			);
		}

		$request = array(
			'channel'     => '#forums',
			'username'    => 'IRC WPBot Notifier',
			'icon_emoji'  => ':hash:',
			'attachments' => array(
				array(
					'fallback'   => $message,
					'color'      => 'warning',
					'pretext'    => $message,
					'title'      => 'Log excerpt - View logs',
					'title_link' => $log_link,
					'text'       => implode( "\n", $logs ),
					'mrkdwn_in'  => array( 'pretext' ),
					'fields'     => $fields
				)
			)
		);

		$request = json_encode( $request );

		$slack = curl_init( SLACK_API );
		curl_setopt( $slack, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $slack, CURLOPT_POSTFIELDS, "payload=" . $request );
		curl_setopt( $slack, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $slack, CURLOPT_SSL_VERIFYPEER, false );

		$result = curl_exec( $slack );
		if ( false === $result ) {
			echo "Slack cURL error: " . curl_error( $slack ) . "\n";
			echo $request . "\n";
		}

		curl_close( $slack );
	}

	function get_logs( $count ) {
		$output = array();

		try {
			$entries = $this->db->query( "
				SELECT
					m.id,
					m.nickname,
					m.message,
					m.time
				FROM
					messages m
				WHERE
					event = 'message'
				ORDER BY
					m.id DESC
				LIMIT " . $count . "
			" );
			while( $entry = $entries->fetchObject() ) {
				$output[] = sprintf(
					'[%s] %s: %s',
					date( "H:i:s", strtotime( $entry->time ) ),
					$entry->nickname,
					$entry->message
				);
			}
		} catch ( \PDOException $e ) {
			echo 'PDO Exception: ' . $e->getMessage();
		}

		return $output;
	}

	function spam_protection( $irc, $data ) {
		// Avoid tracking our selves.
		if ( $this->_nick == $data->nick ) {
			return;
		}

		// If this is the users first message, it's stored and ignored.
		if ( ! isset( $this->spam_check[ $data->nick ] ) ) {
			$this->spam_check[ $data->nick ] = array(
				'message'   => array(
					$data->message,
				),
				'timestamp'  => time(),
				'repeat'     => 1,
				'succession' => array(
					'times'     => 1,
					'timestamp' => time(),
				)
			);
			return;
		}

		// If this message is not the same as the previous one, add it to the history.
		if ( ! in_array( $data->message, $this->spam_check[ $data->nick ]['message'] ) ) {
			$this->spam_check[ $data->nick ]['timestamp'] = time();
			$this->spam_check[ $data->nick ]['repeat'] = 1;
			$this->spam_check[ $data->nick ]['message'][] = $data->message;

			/*
			 * If there's too many messages stored, remove the oldest entries until we're below the entry limit.
			 * We use a loop here, in case there's lag we don't want to accidentally keep increasing the
			 * amount of lines unintentionally.
			 */
			while ( count( $this->spam_check[ $data->nick ]['message'] ) > SPAM_MEMORY ) {
				array_shift( $this->spam_check[ $data->nick ]['message'] );
			}
			return;
		}

		// The message should at this point be a repeat, check how often it's been repeated.
		$this->spam_check[ $data->nick ]['repeat']++;

		// Three repetitions makes us count this as spam.
		if ( $this->spam_check[ $data->nick ]['repeat'] >= $this->spam_repeats ) {
			if ( ! isset( $this->spam_kicked[ $data->nick ] ) ) {
				$this->spam_kicked[ $data->nick ] = array(
					'repeat'    => 0,
					'timestamp' => time(),
				);
			}

			$this->spam_kicked[ $data->nick ]['repeat']++;

			if ( $this->spam_kicked[ $data->nick ]['repeat'] >= $this->spam_auto_ban ) {
				// $data->nick . "!" . $data->ident . "@" . $data->host
				$hostmask = sprintf(
					'*!*@%s',
					$data->host
				);

				$irc->ban( $data->channel, $hostmask, SMARTIRC_CRITICAL );
			}

			$irc->kick( $data->channel, $data->nick, 'Please refrain from spamming in #WordPress.', SMARTIRC_CRITICAL );
			return;
		}

		// Check if it's rapid, but unique, lines, which may still be spam!
		if ( ( time() - $this->spam_check[ $data->nick ]['succession']['timestamp'] ) <= $this->spam_lines_seconds ) {
			$this->spam_check[ $data->nick ]['succession']['times']++;
		} else {
			$this->spam_check[ $data->nick ]['succession'] = array(
				'timestamp' => time(),
				'times'     => 1,
			);
		}

		// Is the user spamming
		if ( $this->spam_check[ $data->nick ]['succession']['times'] >= $this->spam_lines ) {
			if ( ! isset( $this->spam_muted[ $data->nick ] ) ) {
				$this->spam_muted[ $data->nick ] = array(
					'repeat'     => 0,
					'timestampe' => time(),
				);
			}

			$this->spam_muted[ $data->nick ]['repeat']++;

			if ( $this->spam_muted[ $data->nick ]['repeat'] >= $this->spam_auto_ban ) {
				// $data->nick . "!" . $data->ident . "@" . $data->host
				$hostmask = sprintf(
					'*!*@%s',
					$data->host
				);

				$irc->ban( $data->channel, $hostmask, SMARTIRC_CRITICAL );

				$irc->kick( $data->channel, $data->nick, 'Banned for repeated spam in #WordPress.', SMARTIRC_CRITICAL );
			} else {
				// $data->nick . "!" . $data->ident . "@" . $data->host
				$hostmask = sprintf(
					'*!*@%s',
					$data->host
				);

				$mute_command = sprintf(
					'+q %s',
					$hostmask
				);

				$this->spam_muted[ $data->nick ]['repeat']['timestamp'] = time();
				$this->spam_muted[ $data->nick ]['repeat']['hostmask'] = $hostmask;

				$irc->mode( $data->channel, $mute_command, SMARTIRC_CRITICAL );
			}
		}
	}

	function spam_protection_gc() {
		// Remove entries that are more than a minute old, this keeps memory consumption down, and spammers will spam fast.
		foreach ( $this->spam_check as $nick => $entry ) {
			if ( ( time() - $entry['timestamp'] ) >= 60 ) {
				unset( $this->spam_check[ $nick ] );
			}
		}

		foreach ( $this->spam_muted as $nick => $entry ) {
			if ( ( time() - $entry['timestamp'] ) >= 60 ) {
				unset( $this->spam_muted[ $nick ] );
			}
		}
	}

	function maybe_unmute_users( $irc ) {
		foreach ( $this->spam_muted as $nick => $entry ) {
			if ( ( time() < $entry['timestamp'] ) >= 900 ) {
				$unmute = sprintf(
					'-q %s',
					$entry['hostmask']
				);

				$irc->mode( '#WordPress', $unmute, SMARTIRC_MEDIUM );

				unset( $this->spam_muted[ $nick ] );
			}
		}
	}

	function maybe_self_update( $irc ) {
		foreach ( $this->mtime as $file => $mtime ) {
			if ( empty( $file ) ) {
				continue;
			}

			// Check if file modification time differs.
			if ( filemtime( __DIR__ . '/' . $file . '.php' ) !== $mtime ) {
				exit;
			}
		}
	}

	function look_for_news( $irc ) {
		$has_news = $this->news->get_articles();

		if ( $has_news ) {
			$message = sprintf(
				chr(2) . 'WordPress News:' . chr(2) .' %s - %s',
				$this->news->get_latest_headline(),
				$this->news->get_latest_url()
			);

			$irc->message( SMARTIRC_TYPE_CHANNEL, '#WordPress', $message );
		}
	}

	function sendSASL() {
		// Declare that we are authenticating through SASL.
		$this->sasl_auth = true;

		$this->_nick = str_replace( ' ', '', BOTNICK );
		$this->_realname = BOTNAME . ' - version ' . BOTVERSION;

		if ( BOTNICK !== null ) {
			$this->_username = str_replace(' ', '', BOTNICK );
		} else {
			$this->_username = str_replace( ' ', '', exec( 'whoami' ) );
		}

		// Send SASL authentication intent.
		$this->send( 'CAP REQ :sasl', SMARTIRC_CRITICAL );

		$this->send('NICK ' . $this->_nick, SMARTIRC_CRITICAL );
		$this->send('USER ' . $this->_username . ' 0 ' . SMARTIRC_UNUSED . ' :' . $this->_realname, SMARTIRC_CRITICAL );
	}

	function event_cap( $data ) {
		// Check for capability declaration.
		if ( 'sasl' === trim( $data->message ) ) {
			if ( $this->sasl_auth ) {
				// Set authentication method.
				$this->send( 'AUTHENTICATE PLAIN', SMARTIRC_CRITICAL );
			}
		}
	}

	function event_authenticate( $data ) {
		// Send authentication code.
		if ( $this->sasl_auth ) {
			$this->send( 'AUTHENTICATE ' . base64_encode( sprintf( '%s:%s', BOTNICK, BOTPASS ) ), SMARTIRC_CRITICAL );
		}
	}

	function event_903( $data ) {
		if ( $this->sasl_auth ) {
			$this->send( 'CAP END', SMARTIRC_CRITICAL );

			// Provide basic nickname actions.
			$this->login( BOTNICK, BOTNAME . ' - version ' . BOTVERSION, 0, BOTNICK, BOTPASS );
		}
	}
}
