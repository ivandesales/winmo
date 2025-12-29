<?php
/**
 * Admin UI.
 *
 * @package WinmoLicenseServer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WL_Admin {

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_wl_save_license', array( $this, 'handle_save' ) );
		add_action( 'admin_post_wl_delete_license', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_wl_toggle_license', array( $this, 'handle_toggle' ) );
	}

	/**
	 * Register menu page.
	 *
	 * @return void
	 */
	public function menu() {
		add_menu_page(
			__( 'Winmo Licenses', 'winmo-license-server' ),
			__( 'Winmo Licenses', 'winmo-license-server' ),
			'manage_options',
			'wl-licenses',
			array( $this, 'render_list' ),
			'dashicons-admin-network'
		);

		add_submenu_page(
			'wl-licenses',
			__( 'Añadir nueva', 'winmo-license-server' ),
			__( 'Añadir nueva', 'winmo-license-server' ),
			'manage_options',
			'wl-licenses-add',
			array( $this, 'render_add' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( $hook, 'wl-licenses' ) ) {
			return;
		}

		wp_enqueue_style( 'wl-admin', WL_PLUGIN_URL . 'assets/admin.css', array(), WL_VERSION );
	}

	/**
	 * Handle create/update.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'winmo-license-server' ) );
		}

		check_admin_referer( 'wl_save_license' );

		$id             = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$license_key    = WL_Utils::sanitize_key( $_POST['license_key'] ?? '' );
		$status         = WL_Utils::sanitize_status( $_POST['status'] ?? 'active' );
		$expires_at     = WL_Utils::sanitize_date( $_POST['expires_at'] ?? '' );
		$allowed        = WL_Utils::sanitize_domains_input( $_POST['allowed_domains'] ?? '' );

		if ( empty( $license_key ) ) {
			wp_safe_redirect( add_query_arg( 'message', 'error', wp_get_referer() ) );
			exit;
		}

		$data = array(
			'license_key'    => $license_key,
			'status'         => $status,
			'expires_at'     => $expires_at,
			'allowed_domains'=> $allowed,
		);

		if ( $id ) {
			WL_DB::update( $id, $data );
			$redirect = add_query_arg( 'message', 'updated', admin_url( 'admin.php?page=wl-licenses' ) );
		} else {
			WL_DB::insert( $data );
			$redirect = add_query_arg( 'message', 'created', admin_url( 'admin.php?page=wl-licenses' ) );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle delete.
	 *
	 * @return void
	 */
	public function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'winmo-license-server' ) );
		}

		check_admin_referer( 'wl_delete_license' );
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( $id ) {
			WL_DB::delete( $id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wl-licenses&message=deleted' ) );
		exit;
	}

	/**
	 * Handle toggle status.
	 *
	 * @return void
	 */
	public function handle_toggle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'winmo-license-server' ) );
		}

		check_admin_referer( 'wl_toggle_license' );
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$license = WL_DB::get( $id );

		if ( $license ) {
			$new_status = 'active' === $license['status'] ? 'disabled' : 'active';
			WL_DB::update_status( $id, $new_status );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wl-licenses&message=toggled' ) );
		exit;
	}

	/**
	 * Render list page.
	 *
	 * @return void
	 */
	public function render_list() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$items  = WL_DB::all( $search );
		?>
		<div class="wrap wl-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Licencias', 'winmo-license-server' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wl-licenses-add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Añadir nueva', 'winmo-license-server' ); ?></a>
			<hr class="wp-header-end">
			<?php $this->render_notices(); ?>
			<form method="get">
				<input type="hidden" name="page" value="wl-licenses" />
				<p class="search-box">
					<label class="screen-reader-text" for="license-search-input"><?php esc_html_e( 'Buscar licencias', 'winmo-license-server' ); ?></label>
					<input type="search" id="license-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
					<?php submit_button( __( 'Buscar', 'winmo-license-server' ), '', '', false ); ?>
				</p>
			</form>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Key', 'winmo-license-server' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'winmo-license-server' ); ?></th>
						<th><?php esc_html_e( 'Expira', 'winmo-license-server' ); ?></th>
						<th><?php esc_html_e( 'Dominios', 'winmo-license-server' ); ?></th>
						<th><?php esc_html_e( 'Creado', 'winmo-license-server' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'Sin licencias.', 'winmo-license-server' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $items as $item ) : ?>
							<tr>
								<td>
									<strong><?php echo WL_Utils::mask_key( $item['license_key'] ); ?></strong>
									<div class="row-actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=wl-licenses-add&action=edit&id=' . absint( $item['id'] ) ) ); ?>"><?php esc_html_e( 'Editar', 'winmo-license-server' ); ?></a> |
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wl_toggle_license&id=' . absint( $item['id'] ) ), 'wl_toggle_license' ) ); ?>">
											<?php echo 'active' === $item['status'] ? esc_html__( 'Desactivar', 'winmo-license-server' ) : esc_html__( 'Activar', 'winmo-license-server' ); ?>
										</a> |
										<a class="wl-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wl_delete_license&id=' . absint( $item['id'] ) ), 'wl_delete_license' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( '¿Eliminar licencia?', 'winmo-license-server' ) ); ?>');"><?php esc_html_e( 'Borrar', 'winmo-license-server' ); ?></a>
									</div>
								</td>
								<td><?php echo esc_html( $item['status'] ); ?></td>
								<td><?php echo WL_Utils::display_date( $item['expires_at'] ); ?></td>
								<td>
									<?php
									$domains = $item['allowed_domains'];
									echo esc_html( empty( $domains ) ? __( 'Cualquiera', 'winmo-license-server' ) : implode( ', ', $domains ) );
									?>
								</td>
								<td><?php echo esc_html( $item['created_at'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render add/edit page.
	 *
	 * @return void
	 */
	public function render_add() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$editing  = isset( $_GET['action'], $_GET['id'] ) && 'edit' === $_GET['action'];
		$license  = $editing ? WL_DB::get( absint( $_GET['id'] ) ) : null;
		?>
		<div class="wrap wl-wrap">
			<h1><?php echo $editing ? esc_html__( 'Editar licencia', 'winmo-license-server' ) : esc_html__( 'Añadir nueva licencia', 'winmo-license-server' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wl-form">
				<?php wp_nonce_field( 'wl_save_license' ); ?>
				<input type="hidden" name="action" value="wl_save_license" />
				<?php if ( $editing ) : ?>
					<input type="hidden" name="id" value="<?php echo esc_attr( $license['id'] ); ?>" />
				<?php endif; ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="license_key"><?php esc_html_e( 'License Key', 'winmo-license-server' ); ?></label></th>
						<td>
							<input name="license_key" type="text" id="license_key" value="<?php echo esc_attr( $license['license_key'] ?? '' ); ?>" class="regular-text" required />
							<button type="button" class="button" id="wl-generate-key"><?php esc_html_e( 'Generar', 'winmo-license-server' ); ?></button>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Estado', 'winmo-license-server' ); ?></th>
						<td>
							<select name="status" id="status">
								<option value="active" <?php selected( $license['status'] ?? '', 'active' ); ?>><?php esc_html_e( 'Activo', 'winmo-license-server' ); ?></option>
								<option value="disabled" <?php selected( $license['status'] ?? '', 'disabled' ); ?>><?php esc_html_e( 'Desactivado', 'winmo-license-server' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="expires_at"><?php esc_html_e( 'Expira (YYYY-MM-DD o vacío)', 'winmo-license-server' ); ?></label></th>
						<td>
							<input name="expires_at" type="text" id="expires_at" value="<?php echo esc_attr( $license['expires_at'] ?? '' ); ?>" class="regular-text" placeholder="2025-12-31" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="allowed_domains"><?php esc_html_e( 'Dominios permitidos', 'winmo-license-server' ); ?></label></th>
						<td>
							<textarea name="allowed_domains" id="allowed_domains" rows="5" class="large-text" placeholder="https://ejemplo.com"><?php echo esc_textarea( WL_Utils::domains_to_text( $license['allowed_domains'] ?? array() ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Una URL por línea. Vacío permite cualquier dominio.', 'winmo-license-server' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( $editing ? __( 'Actualizar', 'winmo-license-server' ) : __( 'Guardar', 'winmo-license-server' ) ); ?>
			</form>
		</div>
		<script>
			(function() {
				const btn = document.getElementById('wl-generate-key');
				if (!btn) return;
				btn.addEventListener('click', function() {
					const field = document.getElementById('license_key');
					const random = Array.from(crypto.getRandomValues(new Uint8Array(16))).map(b => ('0' + b.toString(16)).slice(-2)).join('').toUpperCase();
					const formatted = random.match(/.{1,4}/g).join('-');
					field.value = formatted;
				});
			})();
		</script>
		<?php
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	private function render_notices() {
		if ( empty( $_GET['message'] ) ) {
			return;
		}

		$messages = array(
			'created'  => __( 'Licencia creada.', 'winmo-license-server' ),
			'updated'  => __( 'Licencia actualizada.', 'winmo-license-server' ),
			'deleted'  => __( 'Licencia eliminada.', 'winmo-license-server' ),
			'toggled'  => __( 'Estado actualizado.', 'winmo-license-server' ),
			'error'    => __( 'Falta la clave de licencia.', 'winmo-license-server' ),
		);

		if ( isset( $messages[ $_GET['message'] ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $messages[ $_GET['message'] ] )
			);
		}
	}
}
