<?php
/**
 * Admin UI for license management.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Winmo_Admin {
	/**
	 * License handler.
	 *
	 * @var Winmo_License
	 */
	protected $license;
	protected $dashboard;

	/**
	 * Constructor.
	 *
	 * @param Winmo_License $license License instance.
	 */
	public function __construct( Winmo_License $license, $dashboard = null ) {
		$this->license = $license;
		$this->dashboard = $dashboard;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'all_admin_notices', array( $this, 'maybe_show_readonly_notice' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Winmo', 'winmo-core' ),
			__( 'Winmo', 'winmo-core' ),
			'manage_options',
			'winmo-core',
			$this->dashboard ? array( $this->dashboard, 'render_page' ) : array( $this, 'render_license_page' ),
			'dashicons-admin-network'
		);

		add_submenu_page(
			'winmo-core',
			__( 'Dashboard', 'winmo-core' ),
			__( 'Dashboard', 'winmo-core' ),
			'manage_options',
			'winmo-core',
			$this->dashboard ? array( $this->dashboard, 'render_page' ) : array( $this, 'render_license_page' )
		);

		add_submenu_page(
			'winmo-core',
			__( 'Inmuebles', 'winmo-core' ),
			__( 'Inmuebles', 'winmo-core' ),
			'manage_options',
			'edit.php?post_type=winmo_property'
		);

		add_submenu_page(
			'winmo-core',
			__( 'Clientes', 'winmo-core' ),
			__( 'Clientes', 'winmo-core' ),
			'manage_options',
			'edit.php?post_type=winmo_client'
		);

		add_submenu_page(
			'winmo-core',
			__( 'Operaciones', 'winmo-core' ),
			__( 'Operaciones', 'winmo-core' ),
			'manage_options',
			'edit.php?post_type=winmo_operation'
		);
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_winmo-core' !== $hook_suffix ) {
			return;
		}

		$ver = defined( 'WINMO_VERSION' ) ? WINMO_VERSION : ( defined( 'WINMO_CORE_VERSION' ) ? WINMO_CORE_VERSION : '1.0.0' );
		wp_enqueue_style( 'winmo-core-admin', WINMO_CORE_URL . 'assets/admin.css', array(), $ver );
	}

	/**
	 * Render license page.
	 */
	public function render_license_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->handle_actions();

		$license_data  = $this->license->get_license_data();
		$masked_key    = Winmo_Utils::mask_license_key( $license_data['license_key'] );
		$status        = isset( $license_data['status'] ) ? $license_data['status'] : 'unknown';
		$status_labels = array(
			'valid'    => 'Activo',
			'invalid'  => 'No válida',
			'expired'  => 'Expirada',
			'disabled' => 'Desactivada',
			'unknown'  => 'Sin comprobar',
		);
		$pretty_status  = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : 'Sin comprobar';
		$expires_raw    = isset( $license_data['expires_at'] ) ? $license_data['expires_at'] : '';
		$expires_pretty = __( 'N/D', 'winmo-core' );

		if ( ! empty( $expires_raw ) ) {
			try {
				$dt             = new DateTime( $expires_raw );
				$expires_pretty = $dt->format( 'd/m/Y' );
			} catch ( Exception $e ) {
				$expires_pretty = sanitize_text_field( $expires_raw );
			}
		}
		$last_checked = isset( $license_data['last_checked_at'] ) ? $license_data['last_checked_at'] : '';
		$connection   = ! empty( $license_data['connection_error'] );
		?>
		<div class="wrap winmo-core">
			<h1><?php esc_html_e( 'Winmo - Licencia', 'winmo-core' ); ?></h1>
			<?php settings_errors( 'winmo-core' ); ?>
			<form method="post">
				<?php wp_nonce_field( 'winmo_license_action', 'winmo_license_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="winmo-license-key"><?php esc_html_e( 'Clave de licencia', 'winmo-core' ); ?></label></th>
							<td>
								<input name="license_key" type="text" id="winmo-license-key" class="regular-text" placeholder="<?php esc_attr_e( 'Introduce tu clave', 'winmo-core' ); ?>" />
								<?php if ( ! empty( $masked_key ) ) : ?>
									<p class="description"><?php esc_html_e( 'Licencia actual:', 'winmo-core' ); ?> <?php echo esc_html( $masked_key ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<button type="submit" name="winmo_save_license" class="button button-primary"><?php esc_html_e( 'Guardar', 'winmo-core' ); ?></button>
					<button type="submit" name="winmo_check_license" class="button"> <?php esc_html_e( 'Comprobar ahora', 'winmo-core' ); ?> </button>
					<button type="submit" name="winmo_deactivate_license" class="button button-secondary"> <?php esc_html_e( 'Desactivar en este sitio', 'winmo-core' ); ?> </button>
				</p>
			</form>

			<h2><?php esc_html_e( 'Estado', 'winmo-core' ); ?></h2>
			<ul class="winmo-status">
				<li><strong><?php esc_html_e( 'Estado actual:', 'winmo-core' ); ?></strong> <span class="status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $pretty_status ); ?></span></li>
				<li><strong><?php esc_html_e( 'Fecha de expiración:', 'winmo-core' ); ?></strong> <?php echo esc_html( $expires_pretty ); ?></li>
				<li><strong><?php esc_html_e( 'Última comprobación:', 'winmo-core' ); ?></strong> <?php echo $last_checked ? esc_html( $last_checked ) : esc_html__( 'N/D', 'winmo-core' ); ?></li>
				<li><strong><?php esc_html_e( 'Error de conexión:', 'winmo-core' ); ?></strong> <?php echo $connection ? esc_html__( 'Sí', 'winmo-core' ) : esc_html__( 'No', 'winmo-core' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Handle form submissions.
	 */
	protected function handle_actions() {
		if ( ! isset( $_POST['winmo_license_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['winmo_license_nonce'] ) ), 'winmo_license_action' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['winmo_save_license'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->handle_save();
		}

		if ( isset( $_POST['winmo_check_license'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->handle_check();
		}

		if ( isset( $_POST['winmo_deactivate_license'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->handle_disable();
		}
	}

	/**
	 * Save license key.
	 */
	protected function handle_save() {
		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $license_key ) ) {
			add_settings_error( 'winmo-core', 'empty-license', __( 'Por favor introduce una clave de licencia.', 'winmo-core' ), 'error' );

			return;
		}

		$this->license->save_license_key( $license_key );
		add_settings_error( 'winmo-core', 'license-saved', __( 'Licencia guardada.', 'winmo-core' ), 'updated' );
	}

	/**
	 * Manual check.
	 */
	protected function handle_check() {
		$data = $this->license->check_license();

		// Note: Errors here could be connection errors or local errors.
		if ( ! empty( $data['connection_error'] ) ) {
			add_settings_error( 'winmo-core', 'license-error', __( 'No se pudo comprobar la licencia. Se mantiene el estado previo.', 'winmo-core' ), 'error' );
		} else {
			add_settings_error( 'winmo-core', 'license-checked', __( 'Comprobación completada.', 'winmo-core' ), 'updated' );
		}
	}

	/**
	 * Disable license locally.
	 */
	protected function handle_disable() {
		$this->license->disable_license();
		add_settings_error( 'winmo-core', 'license-disabled', __( 'Licencia desactivada en este sitio.', 'winmo-core' ), 'updated' );
	}

	/**
	 * Aviso de solo lectura en pantallas de inmuebles sin licencia activa.
	 */
	public function maybe_show_readonly_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || empty( $screen->post_type ) || 'winmo_property' !== $screen->post_type ) {
			return;
		}

		if ( ! function_exists( 'winmo_premium_enabled' ) ) {
			return;
		}

		if ( winmo_premium_enabled() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Modo solo lectura. Activa licencia para editar.', 'winmo-core' ) . '</p></div>';
	}
}
