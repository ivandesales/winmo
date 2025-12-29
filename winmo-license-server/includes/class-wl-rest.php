<?php
/**
 * REST API.
 *
 * @package WinmoLicenseServer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WL_REST {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'wl/v1',
			'/check',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_check' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'license_key' => array(
						'required' => true,
						'type'     => 'string',
					),
					'domain'      => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	/**
	 * Handle check endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_check( WP_REST_Request $request ) {
		$ip = $this->get_client_ip( $request );

		if ( ! $this->check_rate_limit( $ip ) ) {
			return new WP_Error( 'rate_limited', __( 'Rate limit exceeded', 'winmo-license-server' ), array( 'status' => 429 ) );
		}

		$key    = WL_Utils::sanitize_key( $request->get_param( 'license_key' ) );
		$domain = esc_url_raw( $request->get_param( 'domain' ) );

		if ( empty( $key ) || empty( $domain ) ) {
			return new WP_Error( 'invalid_request', __( 'license_key and domain are required', 'winmo-license-server' ), array( 'status' => 400 ) );
		}

		$license = WL_DB::get_by_key( $key );

		if ( ! $license ) {
			return $this->response( false, 'invalid', null, __( 'License not found', 'winmo-license-server' ) );
		}

		if ( 'disabled' === $license['status'] ) {
			return $this->response( false, 'disabled', $license['expires_at'], __( 'License disabled', 'winmo-license-server' ) );
		}

		if ( $license['expires_at'] && time() > strtotime( $license['expires_at'] ) ) {
			return $this->response( false, 'expired', $license['expires_at'], __( 'License expired', 'winmo-license-server' ) );
		}

		$allowed = $license['allowed_domains'];
		if ( ! empty( $allowed ) && ! $this->domain_allowed( $domain, $allowed ) ) {
			return $this->response( false, 'invalid', $license['expires_at'], __( 'domain not allowed', 'winmo-license-server' ) );
		}

		return $this->response( true, 'valid', $license['expires_at'], __( 'License valid', 'winmo-license-server' ) );
	}

	/**
	 * Prepare REST response.
	 *
	 * @param bool        $ok OK.
	 * @param string      $status Status.
	 * @param string|null $expires Expires.
	 * @param string      $message Message.
	 * @return WP_REST_Response
	 */
	private function response( $ok, $status, $expires, $message ) {
		return new WP_REST_Response(
			array(
				'ok'                 => (bool) $ok,
				'status'             => $status,
				'expires_at'         => $expires ? mysql_to_rfc3339( $expires ) : null,
				'message'            => $message,
				'next_check_in_hours'=> 12,
			),
			200
		);
	}

	/**
	 * Simple rate limit per IP (60/hour).
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	private function check_rate_limit( $ip ) {
		$key      = 'wl_rate_' . md5( $ip );
		$count    = (int) get_transient( $key );

		if ( $count >= 60 ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Validate allowed domain.
	 *
	 * @param string $domain Domain.
	 * @param array  $allowed Allowed list.
	 * @return bool
	 */
	private function domain_allowed( $domain, $allowed ) {
		$normalized = untrailingslashit( strtolower( $domain ) );

		foreach ( $allowed as $allowed_domain ) {
			if ( $normalized === untrailingslashit( strtolower( $allowed_domain ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get client IP.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function get_client_ip( WP_REST_Request $request ) {
		$ip = $request->get_header( 'x-forwarded-for' );
		if ( $ip ) {
			$parts = explode( ',', $ip );
			$ip    = trim( $parts[0] );
		} else {
			$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		}

		return sanitize_text_field( $ip );
	}
}
