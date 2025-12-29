<?php
/**
 * Utility helpers.
 *
 * @package WinmoLicenseServer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WL_Utils {

	/**
	 * Sanitize license key input.
	 *
	 * @param string $key License key.
	 * @return string
	 */
	public static function sanitize_key( $key ) {
		$key = wp_unslash( $key );
		$key = preg_replace( '/[^A-Za-z0-9\\-\\_]/', '', $key );
		return sanitize_text_field( $key );
	}

	/**
	 * Sanitize status value.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function sanitize_status( $status ) {
		$status   = sanitize_key( $status );
		$allowed  = array( 'active', 'disabled' );
		$default  = 'active';
		return in_array( $status, $allowed, true ) ? $status : $default;
	}

	/**
	 * Sanitize date input.
	 *
	 * @param string|null $date Date string.
	 * @return string|null
	 */
	public static function sanitize_date( $date ) {
		if ( empty( $date ) ) {
			return null;
		}

		$date = sanitize_text_field( wp_unslash( $date ) );
		$timestamp = strtotime( $date );
		return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;
	}

	/**
	 * Sanitize allowed domains textarea input.
	 *
	 * @param string $input Input string.
	 * @return array
	 */
	public static function sanitize_domains_input( $input ) {
		$input  = wp_unslash( $input );
		$lines  = array_filter( array_map( 'trim', explode( "\n", $input ) ) );
		$clean  = array();

		foreach ( $lines as $line ) {
			$url = esc_url_raw( $line );
			if ( ! empty( $url ) ) {
				$clean[] = untrailingslashit( $url );
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Convert domains array to newline separated text.
	 *
	 * @param array $domains Domains.
	 * @return string
	 */
	public static function domains_to_text( $domains ) {
		if ( empty( $domains ) ) {
			return '';
		}

		return implode( "\n", array_map( 'esc_html', $domains ) );
	}

	/**
	 * Format date for display.
	 *
	 * @param string|null $date Date.
	 * @return string
	 */
	public static function display_date( $date ) {
		if ( empty( $date ) ) {
			return __( 'No expiry', 'winmo-license-server' );
		}

		return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date ) );
	}

	/**
	 * Shorten license key for listing.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function mask_key( $key ) {
		$key = (string) $key;
		if ( strlen( $key ) <= 8 ) {
			return esc_html( $key );
		}

		return esc_html( substr( $key, 0, 4 ) . '...' . substr( $key, -4 ) );
	}
}
