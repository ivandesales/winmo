<?php
/**
 * Core bootstrap.
 *
 * @package WinmoLicenseServer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WL_Core {

	/**
	 * Admin instance.
	 *
	 * @var WL_Admin
	 */
	private $admin;

	/**
	 * REST instance.
	 *
	 * @var WL_REST
	 */
	private $rest;

	/**
	 * Init plugin.
	 *
	 * @return void
	 */
	public function init() {
		$this->admin = new WL_Admin();
		$this->admin->init();

		$this->rest = new WL_REST();
		$this->rest->init();
	}
}
