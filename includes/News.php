<?php

namespace WPBot;

class News {

	public $feed_url;

	private $last_lookup = null;

	public $latest_article = array(
		'link'  => null,
		'title' => null,
	);

	public function __construct() {
		// Set a custom configured feed URL; or fallback to the generic WordPress.org news.
		if ( defined( 'NEWS_URL' ) ) {
			$this->feed_url = NEWS_URL;
		} else {
			$this->feed_url = 'https://wordpress.org/news/feed/';
		}
	}

	public function get_latest() {
		return $this->latest_article;
	}

	public function get_latest_url() {
		return $this->latest_article['link'];
	}

	public function get_latest_headline() {
		return $this->latest_article['title'];
	}

	/**
	 * Fetch the latest articles.
	 *
	 * Returns a `boolean` value indicating if there are new items to show. Always treats a first run as nothing being new.
	 *
	 * @return bool
	 */
	public function get_articles() {
		$feed = new \SimplePie();
		$feed->set_feed_url( $this->feed_url );
		$feed->init();

		$old_title = $this->get_latest_headline();

		// Get the newest item and save it for later.
		foreach ( $feed->get_items( 0, 1 ) as $item ) {
			$this->latest_article = array(
				'link'  => $item->get_link(),
				'title' => $item->get_title(),
				'date'  => $item->get_date( 'Y-m-d H:i' ),
			);
		}

		// If this is the first run, or the title is identical to the old one, report no new entries.
		if ( null === $this->last_lookup || $old_title === $this->get_latest_headline() ) {
			return false;
		}

		$this->last_lookup = time();

		return true;
	}

}
