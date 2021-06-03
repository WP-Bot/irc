<?php

namespace WPBot;

class Tools {
	public static function message_split( $data ) {
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
}
