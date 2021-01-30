<?php

namespace WPBot;

class Themes {

	private $known = [];

	public function __construct() {

	}

	/**
	 * Looks up the MD5 hash of a search string ot see if it's been looked up before.
	 *
	 * @param string $string The search string used to find a plugin.
	 *
	 * @return bool|array
	 */
	public function theme_is_known( $string ) {
		$md5 = md5( $string );

		// If the string is not set, return a boolean.
		if ( ! isset( $this->known[ $md5 ] ) ) {
			return false;
		}

		// Check the age, anything older than 12 hours might be outdated.
		$time = 12 * 60 * 60;
		if ( ( time() - $this->known[ $md5 ]['timestamp'] ) > $time ) {
			return false;
		}

		return $this->known[ $md5 ]['data'];
	}

	/**
	 * Look up a plugin.
	 *
	 * @param string $string Search string.
	 *
	 * @return bool|string
	 */
	public function search( $string ) {
		$theme = $this->theme_is_known( $string );

		if ( false === $theme ) {
			$url = sprintf(
				'https://api.wordpress.org/themes/info/1.2/?action=query_themes&request[per_page]=1&request[search]=%s',
				$string
			);

			$response = \Requests::get( $url );

			$response_body = json_decode( $response->body );

			// Check if any results matched.
			if ( $response_body->info->results < 1 ) {
				return false;
			}

			$theme = (object) array(
				'name' => $response_body->themes[0]->name,
				'slug' => $response_body->themes[0]->slug,
			);

			$this->known[ md5( $string ) ] = array(
				'timestampe' => time(),
				'data'       => $theme,
			);
		}

		return $theme;
	}

}
