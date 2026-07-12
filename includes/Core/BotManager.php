<?php
namespace SamAntiSpam\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BotManager {

	// Internal array of known good search engine user-agent signatures
	private $allowed_bots = array(
		'Googlebot',
		'Bingbot',
		'Slurp',
		'DuckDuckBot'
	);

	public function init() {
		// Initialization logic
	}

	/**
	 * Check if the user agent belongs to an allowed bot.
	 *
	 * @param string $user_agent The user agent string.
	 * @param string $ip         The IP address (for future validation).
	 * @return bool True if allowed bot, false otherwise.
	 */
	public function is_allowed_bot( string $user_agent, string $ip ): bool {
		if ( empty( $user_agent ) ) {
			return false;
		}

		foreach ( $this->allowed_bots as $bot ) {
			if ( stripos( $user_agent, $bot ) !== false ) {
				// TODO: Add IP range validation to prevent UA spoofing
				return true;
			}
		}

		return false;
	}
}
