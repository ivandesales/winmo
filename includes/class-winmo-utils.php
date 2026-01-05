<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Winmo_Utils {
	/**
	 * Mask a license key leaving only the last 4 characters visible.
	 *
	 * @param string $key Raw license key.
	 *
	 * @return string
	 */
	public static function mask_license_key( $key ) {
		$key = trim( (string) $key );
		if ( '' === $key ) {
			return '';
		}

		$visible   = substr( $key, -4 );
		$mask      = str_repeat( '•', max( 0, strlen( $key ) - 4 ) );
		return $mask . $visible;
	}
}
