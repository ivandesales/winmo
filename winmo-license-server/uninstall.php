<?php
/**
 * Uninstall script.
 *
 * @package WinmoLicenseServer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-wl-db.php';

WL_DB::uninstall();
