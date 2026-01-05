<?php
/**
 * Main plugin bootstrap class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Winmo_Core {
	/**
	 * @var Winmo_License
	 */
	protected $license;

	/**
	 * @var Winmo_Admin
	 */
	protected $admin;

	/**
	 * @var Winmo_CPT
	 */
	protected $cpt;
	/**
	 * @var Winmo_Clients
	 */
	protected $clients;
	/**
	 * @var Winmo_Operations
	 */
	protected $operations;
	/**
	 * @var Winmo_Dashboard
	 */
	protected $dashboard;

	/**
	 * Initialize plugin.
	 */
	public function init() {
		// Licencias + admin.
		$this->license   = new Winmo_License();
		$this->dashboard = class_exists( 'Winmo_Dashboard' ) ? new Winmo_Dashboard() : null;
		$this->admin     = new Winmo_Admin( $this->license, $this->dashboard );

		$this->license->init();
		if ( $this->dashboard && method_exists( $this->dashboard, 'init' ) ) {
			$this->dashboard->init();
		}
		$this->admin->init();

		// ERP (CPT Inmuebles).
		$this->cpt = new Winmo_CPT();
		$this->clients = new Winmo_Clients();
		$this->operations = new Winmo_Operations();

		add_action( 'init', array( $this->cpt, 'register' ) );
		add_action( 'init', array( $this->cpt, 'register_meta' ) );
		add_action( 'admin_init', array( $this->cpt, 'block_new_if_no_license' ) );
		add_action( 'add_meta_boxes', array( $this->cpt, 'register_metabox' ) );
		add_action( 'save_post_winmo_property', array( $this->cpt, 'save_meta' ), 10, 3 );
		add_filter( 'wp_insert_post_data', array( $this->cpt, 'maybe_lock_post_data' ), 10, 2 );
		add_action( 'admin_notices', array( $this->cpt, 'maybe_show_edit_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this->cpt, 'enqueue_admin_assets' ) );
		add_filter( 'admin_body_class', array( $this->cpt, 'filter_admin_body_class' ) );
		if ( $this->dashboard ) {
			add_action( 'save_post_winmo_property', array( $this->dashboard, 'flush_cache' ) );
		}

		// Clientes.
		add_action( 'init', array( $this->clients, 'register' ) );
		add_action( 'init', array( $this->clients, 'register_meta' ) );
		add_action( 'admin_init', array( $this->clients, 'block_new_if_no_license' ) );
		add_action( 'add_meta_boxes', array( $this->clients, 'register_metabox' ) );
		add_action( 'save_post_winmo_client', array( $this->clients, 'save_meta' ), 10, 3 );
		add_filter( 'wp_insert_post_data', array( $this->clients, 'maybe_lock_post_data' ), 10, 2 );
		add_action( 'admin_notices', array( $this->clients, 'maybe_show_edit_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this->clients, 'enqueue_admin_assets' ) );
		add_filter( 'admin_body_class', array( $this->clients, 'filter_admin_body_class' ) );
		if ( $this->dashboard ) {
			add_action( 'save_post_winmo_client', array( $this->dashboard, 'flush_cache' ) );
		}

		// Operaciones.
		add_action( 'init', array( $this->operations, 'register' ) );
		add_action( 'init', array( $this->operations, 'register_meta' ) );
		add_action( 'admin_init', array( $this->operations, 'block_new_if_no_license' ) );
		add_action( 'add_meta_boxes', array( $this->operations, 'register_metabox' ) );
		add_action( 'save_post_winmo_operation', array( $this->operations, 'save_meta' ), 10, 3 );
		add_filter( 'wp_insert_post_data', array( $this->operations, 'maybe_lock_post_data' ), 10, 2 );
		add_action( 'admin_notices', array( $this->operations, 'maybe_show_edit_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this->operations, 'enqueue_admin_assets' ) );
		add_filter( 'admin_body_class', array( $this->operations, 'filter_admin_body_class' ) );
		if ( $this->dashboard ) {
			add_action( 'save_post_winmo_operation', array( $this->dashboard, 'flush_cache' ) );
		}
	}

	public static function activate() {
		$license = new Winmo_License();
		$license->init();
		Winmo_License::schedule_cron();
	}

	public static function deactivate() {
		Winmo_License::clear_cron();
	}
}
