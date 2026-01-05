<?php
/**
 * Plugin Name: Winmo Core
 * Description: Winmo Core plugin provides license handling and feature gating.
 * Version: 1.0.0
 * Author: Winmo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Editable constants.
define( 'WINMO_VERSION', '1.0.0' );
define( 'WINMO_LICENSE_SERVER_URL', 'http://winmo-lic.local' );
define( 'WINMO_OPTION_KEY', 'winmo_core_settings' );
define( 'WINMO_LICENSE_OPTION', 'winmo_core_license' );

define( 'WINMO_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WINMO_CORE_URL', plugin_dir_url( __FILE__ ) );

// ✅ Alias para evitar líos si algún archivo usa WINMO_CORE_VERSION.
if ( ! defined( 'WINMO_CORE_VERSION' ) ) {
	define( 'WINMO_CORE_VERSION', WINMO_VERSION );
}

// Includes.
require_once WINMO_CORE_PATH . 'includes/class-winmo-utils.php';
require_once WINMO_CORE_PATH . 'includes/class-winmo-features.php';
require_once WINMO_CORE_PATH . 'includes/class-winmo-license.php';
require_once WINMO_CORE_PATH . 'includes/class-winmo-dashboard.php';
require_once WINMO_CORE_PATH . 'includes/class-winmo-admin.php';
require_once WINMO_CORE_PATH . 'includes/class-winmo-clients.php';
require_once WINMO_CORE_PATH . 'includes/class-winmo-cpt.php';
require_once WINMO_CORE_PATH . 'includes/class-winmo-operations.php';
require_once WINMO_CORE_PATH . 'includes/class-winmo-core.php';

// Initialize plugin.
add_action(
	'plugins_loaded',
	static function () {
		$plugin = new Winmo_Core();
		$plugin->init();
	}
);

// Activation/deactivation hooks.
register_activation_hook( __FILE__, array( 'Winmo_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Winmo_Core', 'deactivate' ) );

// Global helper functions.
if ( ! function_exists( 'winmo_premium_enabled' ) ) {
	/**
	 * Check whether premium features are enabled.
	 *
	 * @return bool
	 */
	function winmo_premium_enabled() {
		return Winmo_Features::is_premium_enabled();
	}
}

if ( ! function_exists( 'winmo_license_status' ) ) {
	/**
	 * Retrieve license status information.
	 *
	 * @return array
	 */
	function winmo_license_status() {
		$license = new Winmo_License();

		return $license->get_license_data();
	}
}
