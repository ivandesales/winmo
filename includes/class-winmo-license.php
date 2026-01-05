<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Winmo_License {
	const OPTION_KEY      = 'winmo_core_license';
	const CRON_HOOK       = 'winmo_license_cron';
	const CACHE_TTL       = DAY_IN_SECONDS;
	const GRACE_PERIOD    = 72 * HOUR_IN_SECONDS;

	/**
	 * Boot license handling (cron, etc.).
	 */
	public function init() {
		add_action( self::CRON_HOOK, array( $this, 'verify_license' ) );
	}

	/**
	 * Persist a license key.
	 *
	 * @param string $key License key.
	 */
	public function save_license_key( $key ) {
		$data               = $this->get_license_data();
		$data['license_key'] = $key;
		update_option( $this->get_option_name(), $data );
	}

	/**
	 * Verify the current license key (or a provided one).
	 *
	 * @param string|null $license_key Optional license key override.
	 *
	 * @return array Result payload.
	 */
	public function verify_license( $license_key = null ) {
		$data = $this->get_license_data();
		$key  = null !== $license_key ? $license_key : $data['license_key'];
		$key  = sanitize_text_field( (string) $key );

		if ( '' === $key ) {
			$data['status']          = 'inactive';
			$data['license_key']     = '';
			$data['expires_at']      = '';
			$data['last_checked_at'] = '';
			$data['connection_error'] = '';
			$data['last_response_code'] = '';
			$data['message']         = __( 'No se ha especificado una clave de licencia.', 'winmo-core' );
			update_option( $this->get_option_name(), $data );

			return array(
				'status'  => 'inactive',
				'message' => $data['message'],
			);
		}

		$status           = 'valid';
		$expires_at       = $data['expires_at'];
		$connection_error = '';
		$message          = __( 'Licencia verificada correctamente.', 'winmo-core' );
		$response_code    = '';
		$last_valid_at    = isset( $data['last_valid_at'] ) ? $data['last_valid_at'] : '';

		if ( function_exists( 'wp_remote_post' ) ) {
			$response = wp_remote_post(
				WINMO_LICENSE_SERVER_URL,
				array(
					'timeout' => 10,
					'body'    => array(
						'license_key' => $key,
						'site'        => home_url(),
						'action'      => 'verify',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$connection_error = $response->get_error_message();
				$message          = __( 'No se pudo contactar con el servidor de licencias.', 'winmo-core' );
				$this->log_error( 'license_check_error', $connection_error );
			} else {
				$code = wp_remote_retrieve_response_code( $response );
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				$response_code = (string) $code;

				if ( 200 <= (int) $code && (int) $code < 300 && is_array( $body ) ) {
					$status = isset( $body['status'] ) ? sanitize_text_field( (string) $body['status'] ) : 'unknown';

					if ( isset( $body['expires_at'] ) ) {
						$expires_at = sanitize_text_field( (string) $body['expires_at'] );
					} elseif ( isset( $body['valid_until'] ) ) {
						$expires_at = sanitize_text_field( (string) $body['valid_until'] );
					}

					if ( isset( $body['message'] ) ) {
						$message = sanitize_text_field( (string) $body['message'] );
					}

					if ( 'valid' === $status ) {
						$last_valid_at = $this->current_time();
					} else {
						$last_valid_at = '';
					}
				} else {
					$connection_error = sprintf(
						/* translators: %d: http status code */
						__( 'Error al verificar la licencia (código %d).', 'winmo-core' ),
						(int) $code
					);
					$message = $connection_error;
					$this->log_error( 'license_http_error_' . $code, wp_remote_retrieve_body( $response ) );
				}
			}
		}

		$data['license_key']       = $key;
		$data['last_checked_at']   = $this->current_time();
		$data['last_response_code'] = $response_code;
		$data['connection_error']  = $connection_error;

		if ( '' === $connection_error ) {
			$data['status']      = $status;
			$data['expires_at']  = $expires_at;
			$data['message']     = $message;
			$data['last_valid_at'] = $last_valid_at;
		} else {
			// Mantener estado previo en fallos de conexión.
			$message = $message ? $message : __( 'No se pudo validar la licencia.', 'winmo-core' );
		}

		update_option( $this->get_option_name(), $data );
		if ( class_exists( 'Winmo_Dashboard' ) && method_exists( 'Winmo_Dashboard', 'flush_cache' ) ) {
			Winmo_Dashboard::flush_cache();
		}

		return array(
			'status'    => $status,
			'expires'   => $expires_at,
			'last_check'=> $data['last_checked_at'],
			'message'   => $message,
			'connection_error' => $connection_error,
			'is_grace'  => $this->is_grace_period( $data ),
		);
	}

	/**
	 * Retrieve persisted license data.
	 *
	 * @return array
	 */
	public function get_license_data() {
		$defaults = array(
			'license_key'      => '',
			'status'           => 'inactive',
			'expires_at'       => '',
			'last_checked_at'  => '',
			'connection_error' => '',
			'message'          => '',
			'last_valid_at'    => '',
			'last_response_code' => '',
		);

		$data = get_option( $this->get_option_name(), array() );

		$data = wp_parse_args( is_array( $data ) ? $data : array(), $defaults );
		$data['is_grace'] = $this->is_grace_period( $data );

		return $data;
	}

	/**
	 * Get current status string.
	 *
	 * @return string valid|inactive|invalid|unknown
	 */
	public function get_status() {
		$data = $this->get_license_data();
		return isset( $data['status'] ) ? $data['status'] : 'inactive';
	}

	/**
	 * Whether premium should be active (valid or grace).
	 *
	 * @return bool
	 */
	public function is_premium_active() {
		$data = $this->get_license_data();
		if ( 'valid' === $data['status'] ) {
			return true;
		}

		return ! empty( $data['is_grace'] );
	}

	/**
	 * Get license expiry.
	 *
	 * @return string
	 */
	public function get_expires() {
		$data = $this->get_license_data();
		return isset( $data['expires_at'] ) ? $data['expires_at'] : '';
	}

	/**
	 * Get last verification timestamp.
	 *
	 * @return string
	 */
	public function get_last_check() {
		$data = $this->get_license_data();
		return isset( $data['last_checked_at'] ) ? $data['last_checked_at'] : '';
	}

	/**
	 * Schedule daily license checks.
	 */
	public static function schedule_cron() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + self::CACHE_TTL, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Clear scheduled cron.
	 */
	public static function clear_cron() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Option name helper to keep a single source of truth.
	 *
	 * @return string
	 */
	protected function get_option_name() {
		return defined( 'WINMO_LICENSE_OPTION' ) ? WINMO_LICENSE_OPTION : self::OPTION_KEY;
	}

	/**
	 * Wrapper for current time to ease testing.
	 *
	 * @return string
	 */
	protected function current_time() {
		return function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Determines grace mode based on last valid verification.
	 *
	 * @param array $data License data.
	 *
	 * @return bool
	 */
	protected function is_grace_period( $data ) {
		$last_valid = isset( $data['last_valid_at'] ) ? strtotime( $data['last_valid_at'] ) : 0;
		$now        = time();

		if ( 'valid' === $data['status'] ) {
			return false;
		}

		if ( $last_valid && ( $now - $last_valid ) <= self::GRACE_PERIOD ) {
			return true;
		}

		return false;
	}

	/**
	 * Logs errors when WP_DEBUG is enabled.
	 *
	 * @param string $context Context label.
	 * @param string $details Details/body.
	 */
	protected function log_error( $context, $details = '' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[Winmo License] %s: %s', $context, $details ) ); // phpcs:ignore
		}
	}
}
