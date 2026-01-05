<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Winmo_Dashboard {
	private const CACHE_KEY = 'winmo_dashboard_cache';
	private const CACHE_TTL = 600; // 10 minutes.

	/**
	 * Register dashboard-specific hooks.
	 */
	public function init() {
		add_action( 'admin_post_winmo_license_verify', array( $this, 'handle_license_form' ) );
	}

	/**
	 * Renderiza la página de dashboard.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$readonly = function_exists( 'winmo_premium_enabled' ) ? ! winmo_premium_enabled() : false;

		$cached  = $this->get_cached_data();
		$metrics = $cached['metrics'];
		$alerts  = $cached['alerts'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Winmo - Dashboard', 'winmo-core' ); ?></h1>
			<?php $this->render_notice(); ?>
			<?php if ( $readonly ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Modo solo lectura. Activa la licencia para editar.', 'winmo-core' ); ?></p></div>
			<?php endif; ?>

			<?php $this->render_license_widget(); ?>

			<h2><?php esc_html_e( 'Métricas rápidas', 'winmo-core' ); ?></h2>
			<ul style="display:flex;gap:16px;flex-wrap:wrap;">
				<li style="background:#fff;border:1px solid #ccd0d4;padding:12px;min-width:180px;">
					<strong><?php esc_html_e( 'Inmuebles totales', 'winmo-core' ); ?></strong><br />
					<?php echo esc_html( $metrics['properties_total'] ); ?>
				</li>
				<li style="background:#fff;border:1px solid #ccd0d4;padding:12px;min-width:180px;">
					<strong><?php esc_html_e( 'Activos / Reservados / Vendidos', 'winmo-core' ); ?></strong><br />
					<?php echo esc_html( $metrics['properties_status']['activo'] ); ?> /
					<?php echo esc_html( $metrics['properties_status']['reservado'] ); ?> /
					<?php echo esc_html( $metrics['properties_status']['vendido'] ); ?>
				</li>
				<li style="background:#fff;border:1px solid #ccd0d4;padding:12px;min-width:180px;">
					<strong><?php esc_html_e( 'Operaciones últimos 30 días', 'winmo-core' ); ?></strong><br />
					<?php echo esc_html( $metrics['operations_30d'] ); ?>
				</li>
				<li style="background:#fff;border:1px solid #ccd0d4;padding:12px;min-width:180px;">
					<strong><?php esc_html_e( 'Clientes totales', 'winmo-core' ); ?></strong><br />
					<?php echo esc_html( $metrics['clients_total'] ); ?>
				</li>
			</ul>

			<h2><?php esc_html_e( 'Alertas', 'winmo-core' ); ?></h2>
			<div style="display:flex;gap:16px;flex-wrap:wrap;">
				<?php $this->render_alert_list( __( 'Inmuebles sin propietario', 'winmo-core' ), $alerts['props_no_owner'], 'winmo_property', [ 'post_type' => 'winmo_property', 'winmo_filter' => 'missing_owner' ] ); ?>
				<?php $this->render_alert_list( __( 'Inmuebles sin coordenadas', 'winmo-core' ), $alerts['props_no_coords'], 'winmo_property', [ 'post_type' => 'winmo_property', 'winmo_filter' => 'missing_coords' ] ); ?>
				<?php $this->render_alert_list( __( 'Inmuebles sin fotos', 'winmo-core' ), $alerts['props_no_photos'], 'winmo_property', [ 'post_type' => 'winmo_property', 'winmo_filter' => 'missing_gallery' ] ); ?>
				<?php $this->render_alert_list( __( 'Operaciones sin cliente o inmueble', 'winmo-core' ), $alerts['ops_missing_links'], 'winmo_operation', [ 'post_type' => 'winmo_operation' ] ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * License widget renderer.
	 */
	private function render_license_widget() {
		$license = class_exists( 'Winmo_License' ) ? new Winmo_License() : null;
		if ( ! $license ) {
			return;
		}

		$data       = $license->get_license_data();
		$status     = $license->get_status();
		$key        = isset( $data['license_key'] ) ? $data['license_key'] : '';
		$expires    = $license->get_expires();
		$last_check = $license->get_last_check();
		$connection_error = isset( $data['connection_error'] ) ? $data['connection_error'] : '';
		$is_grace         = ! empty( $data['is_grace'] );
		$response_code    = isset( $data['last_response_code'] ) ? $data['last_response_code'] : '';

		$status_label = __( 'Sin configurar', 'winmo-core' );
		$status_class = 'background:#e2e4e7;border-color:#ccd0d4;color:#50575e;';

		if ( '' !== $key && 'valid' === $status ) {
			$status_label = __( 'Activa', 'winmo-core' );
			$status_class = 'background:#e5f7ef;border-color:#46b450;color:#007a3d;';
		} elseif ( '' !== $key && 'valid' !== $status ) {
			$status_label = __( 'Inactiva', 'winmo-core' );
			$status_class = 'background:#fde8e8;border-color:#d63638;color:#8a1f11;';
		}

		$message = ( '' === $key || 'valid' !== $status )
			? __( 'Activa la licencia para desbloquear la edición/creación de módulos premium.', 'winmo-core' )
			: __( 'Licencia activa. Módulos premium habilitados.', 'winmo-core' );

		$expires_pretty    = $expires ? $this->format_datetime( $expires, false ) : '—';
		$last_check_pretty = $last_check ? $this->format_datetime( $last_check ) : '—';
		?>
		<div id="winmo-license" style="margin:16px 0;border:1px solid #ccd0d4;background:#fff;padding:16px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Licencia', 'winmo-core' ); ?></h2>
			<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;">
				<div style="flex:1;min-width:240px;">
					<p style="margin:4px 0;">
						<strong><?php esc_html_e( 'Estado:', 'winmo-core' ); ?></strong>
						<span style="display:inline-block;padding:2px 8px;border:1px solid #ccd0d4;border-radius:3px;<?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_label ); ?>
						</span>
					</p>
					<p style="margin:4px 0;"><strong><?php esc_html_e( 'Válida hasta:', 'winmo-core' ); ?></strong> <?php echo esc_html( $expires_pretty ); ?></p>
						<p style="margin:4px 0;"><strong><?php esc_html_e( 'Última verificación:', 'winmo-core' ); ?></strong> <?php echo esc_html( $last_check_pretty ); ?></p>
						<p style="margin:4px 0;"><strong><?php esc_html_e( 'Sitio:', 'winmo-core' ); ?></strong> <?php echo esc_html( home_url() ); ?></p>
						<p style="margin:4px 0;"><strong><?php esc_html_e( 'Error de conexión:', 'winmo-core' ); ?></strong> <?php echo $connection_error ? esc_html( $connection_error ) : esc_html__( 'Ninguno', 'winmo-core' ); ?><?php echo $response_code ? ' (' . esc_html( $response_code ) . ')' : ''; ?></p>
						<?php if ( $is_grace ) : ?>
							<p style="margin:4px 0;color:#d63638;"><strong><?php esc_html_e( 'Modo provisional activo (grace).', 'winmo-core' ); ?></strong></p>
						<?php endif; ?>
						<p style="margin:8px 0;"><?php echo esc_html( $message ); ?></p>
					</div>
					<div style="flex:1;min-width:260px;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'winmo_license_save', 'winmo_license_nonce' ); ?>
						<input type="hidden" name="action" value="winmo_license_verify" />
						<p>
							<label for="winmo-license-key"><strong><?php esc_html_e( 'Clave de licencia', 'winmo-core' ); ?></strong></label><br />
							<input type="text" id="winmo-license-key" name="license_key" class="regular-text" value="<?php echo esc_attr( $key ); ?>" />
						</p>
						<p style="display:flex;gap:8px;align-items:center;">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar y verificar', 'winmo-core' ); ?></button>
							<?php if ( '' !== $key ) : ?>
								<button type="submit" name="winmo_reverify" value="1" class="button"><?php esc_html_e( 'Reverificar ahora', 'winmo-core' ); ?></button>
							<?php endif; ?>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the license form submission.
	 */
	public function handle_license_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permisos insuficientes.', 'winmo-core' ) );
		}

		if ( ! isset( $_POST['winmo_license_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['winmo_license_nonce'] ) ), 'winmo_license_save' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_die( esc_html__( 'Nonce no válido.', 'winmo-core' ) );
		}

		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

		$license = new Winmo_License();
		$license->save_license_key( $key );
		$result = $license->verify_license( $key );

		$message = isset( $result['message'] ) ? $result['message'] : __( 'Licencia guardada.', 'winmo-core' );
		$type    = ( isset( $result['status'] ) && 'valid' === $result['status'] ) ? 'success' : 'error';

		self::flush_cache();

		$redirect = add_query_arg(
			array(
				'page'                => 'winmo-core',
				'winmo_license_msg'   => rawurlencode( $message ),
				'winmo_license_state' => $type,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render feedback notice when present.
	 */
	private function render_notice() {
		if ( empty( $_GET['winmo_license_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$message = sanitize_text_field( wp_unslash( $_GET['winmo_license_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state   = isset( $_GET['winmo_license_state'] ) ? sanitize_text_field( wp_unslash( $_GET['winmo_license_state'] ) ) : 'success'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$class = 'success' === $state ? 'notice notice-success' : 'notice notice-error';
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	private function render_alert_list( $title, $items, $post_type, $filter_key ) {
		?>
		<div style="background:#fff;border:1px solid #ccd0d4;padding:12px;min-width:220px;flex:1;">
			<strong><?php echo esc_html( $title ); ?></strong>
			<?php if ( empty( $items ) ) : ?>
				<p><?php esc_html_e( 'Sin alertas', 'winmo-core' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $items as $id ) : ?>
						<li><a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>">#<?php echo esc_html( $id ); ?> — <?php echo esc_html( get_the_title( $id ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $filter_key ) : ?>
					<p><a href="<?php echo esc_url( add_query_arg( $filter_key, admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'Ver todos', 'winmo-core' ); ?></a></p>
				<?php else : ?>
					<p><a href="<?php echo esc_url( add_query_arg( [ 'post_type' => $post_type ], admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'Ver todos', 'winmo-core' ); ?></a></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function get_metrics() {
		$metrics = [
			'properties_total'   => 0,
			'properties_status'  => [ 'activo' => 0, 'reservado' => 0, 'vendido' => 0 ],
			'operations_30d'     => 0,
			'clients_total'      => 0,
		];

		$metrics['properties_total'] = $this->count_posts('winmo_property');
		foreach ( array_keys( $metrics['properties_status'] ) as $status ) {
			$metrics['properties_status'][ $status ] = $this->count_posts('winmo_property', [
				[
					'key' => 'winmo_status',
					'value' => $status,
				],
			]);
		}

		$metrics['operations_30d'] = $this->count_posts('winmo_operation', [], [
			'date_query' => [
				[
					'after' => '30 days ago',
					'inclusive' => true,
				],
			],
		]);

		$metrics['clients_total'] = $this->count_posts('winmo_client');

		return $metrics;
	}

	private function get_alerts() {
		return [
			'props_no_owner'    => $this->get_posts_missing_owner(10),
			'props_no_coords'   => $this->get_post_ids('winmo_property', [
				'relation' => 'OR',
				[
					'key' => 'winmo_lat',
					'value' => '',
					'compare' => '=',
				],
				[
					'key' => 'winmo_lng',
					'value' => '',
					'compare' => '=',
				],
			], 10),
			'props_no_photos'   => $this->get_posts_without_media(10),
			'ops_missing_links' => $this->get_post_ids('winmo_operation', [
				'relation' => 'OR',
				[
					'key' => 'winmo_client_id',
					'value' => '',
					'compare' => '=',
				],
				[
					'key' => 'winmo_property_id',
					'value' => '',
					'compare' => '=',
				],
			], 10),
		];
	}

	private function count_posts( $post_type, $meta_query = [], $extra = [] ) {
		$args = [
			'post_type'      => $post_type,
			'post_status'    => ['publish', 'draft', 'pending'],
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		];
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}
		$args = array_merge( $args, $extra );
		$q = new WP_Query( $args );
		return (int) $q->found_posts;
	}

	private function get_post_ids( $post_type, $meta_query = [], $limit = 10 ) {
		$args = [
			'post_type'      => $post_type,
			'post_status'    => ['publish', 'draft', 'pending'],
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		];
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}
		$q = new WP_Query( $args );
		return $q->posts;
	}

	private function get_posts_without_media( $limit = 10 ) {
		$q = new WP_Query([
			'post_type'      => 'winmo_property',
			'post_status'    => ['publish', 'draft', 'pending'],
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [
				'relation' => 'OR',
				[
					'key'     => 'winmo_gallery',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => 'winmo_gallery',
					'value'   => '',
					'compare' => '=',
				],
				[
					'key'     => 'winmo_gallery',
					'value'   => 'a:0:{}',
					'compare' => '=',
				],
				[
					'key'     => 'winmo_gallery',
					'value'   => '[]',
					'compare' => '=',
				],
			],
		]);
		return ! empty( $q->posts ) ? $q->posts : [];
	}

	private function get_posts_missing_owner( $limit = 10 ) {
		$q = new WP_Query([
			'post_type'      => 'winmo_property',
			'post_status'    => ['publish', 'draft', 'pending'],
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [
				'relation' => 'OR',
				[
					'key'     => 'winmo_owner_id',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => 'winmo_owner_id',
					'value'   => '',
					'compare' => '=',
				],
				[
					'key'     => 'winmo_owner_id',
					'value'   => '0',
					'compare' => '=',
				],
			],
		]);
		return ! empty( $q->posts ) ? $q->posts : [];
	}

	/**
	 * Format date/time safely.
	 *
	 * @param string $value Raw date/time.
	 * @param bool   $include_time Whether to show time.
	 *
	 * @return string
	 */
	private function format_datetime( $value, $include_time = true ) {
		if ( empty( $value ) ) {
			return '—';
		}

		$timestamp = strtotime( $value );
		if ( ! $timestamp ) {
			return sanitize_text_field( (string) $value );
		}

		$format = $include_time ? get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) : get_option( 'date_format' );
		return date_i18n( $format, $timestamp );
	}

	/**
	 * Retrieve cached dashboard data (metrics + alerts).
	 *
	 * @return array
	 */
	private function get_cached_data() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$data = [
			'metrics' => $this->get_metrics(),
			'alerts'  => $this->get_alerts(),
		];

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Clear dashboard cache.
	 */
	public static function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}
}
