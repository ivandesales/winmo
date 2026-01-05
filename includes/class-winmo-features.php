<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Winmo_Features {
	/**
	 * Determine if premium features are enabled based on license status.
	 *
	 * @return bool
	 */
	public static function is_premium_enabled() {
		if ( ! class_exists( 'Winmo_License' ) ) {
			return false;
		}

		$license = new Winmo_License();
		return $license->is_premium_active();
	}
}
