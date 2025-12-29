<?php
/**
 * Plugin Name: Winmo License Server
 * Description: Simple license management server with admin UI and REST validation.
 * Version: 1.0.0
 * Author: Winmo
 * Text Domain: winmo-license-server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WL_VERSION', '1.0.0' );
define( 'WL_PLUGIN_FILE', __FILE__ );
define( 'WL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WL_PLUGIN_DIR . 'includes/class-wl-utils.php';
require_once WL_PLUGIN_DIR . 'includes/class-wl-db.php';
require_once WL_PLUGIN_DIR . 'includes/class-wl-admin.php';
require_once WL_PLUGIN_DIR . 'includes/class-wl-rest.php';
require_once WL_PLUGIN_DIR . 'includes/class-wl-core.php';

register_activation_hook( __FILE__, array( 'WL_DB', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		$core = new WL_Core();
		$core->init();
	}
);
