<?php
/**
 * Database layer.
 *
 * @package WinmoLicenseServer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WL_DB {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wl_licenses';
	}

	/**
	 * Create table on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			license_key VARCHAR(100) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			expires_at DATETIME NULL DEFAULT NULL,
			allowed_domains LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY license_key (license_key)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop table on uninstall.
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Insert license row.
	 *
	 * @param array $data Data.
	 * @return int|false
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			$table,
			array(
				'license_key'    => $data['license_key'],
				'status'         => $data['status'],
				'expires_at'     => $data['expires_at'],
				'allowed_domains'=> wp_json_encode( $data['allowed_domains'] ),
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update license.
	 *
	 * @param int   $id ID.
	 * @param array $data Data.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', true );

		$result = $wpdb->update(
			$table,
			array(
				'license_key'    => $data['license_key'],
				'status'         => $data['status'],
				'expires_at'     => $data['expires_at'],
				'allowed_domains'=> wp_json_encode( $data['allowed_domains'] ),
				'updated_at'     => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete license.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = self::table();
		$deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		return false !== $deleted;
	}

	/**
	 * Get license by ID.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return self::format_row( $row );
	}

	/**
	 * Get license by key.
	 *
	 * @param string $key Key.
	 * @return array|null
	 */
	public static function get_by_key( $key ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE license_key = %s", $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return self::format_row( $row );
	}

	/**
	 * Get licenses with optional search.
	 *
	 * @param string $search Search string.
	 * @return array
	 */
	public static function all( $search = '' ) {
		global $wpdb;
		$table = self::table();

		if ( $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$query  = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE license_key LIKE %s OR allowed_domains LIKE %s ORDER BY created_at DESC",
				$like,
				$like
			);
			$rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		return array_map( array( __CLASS__, 'format_row' ), $rows );
	}

	/**
	 * Update status.
	 *
	 * @param int    $id ID.
	 * @param string $status Status.
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', true );

		$result = $wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Format database row.
	 *
	 * @param array|null $row Row.
	 * @return array|null
	 */
	private static function format_row( $row ) {
		if ( ! $row ) {
			return null;
		}

		$row['allowed_domains'] = $row['allowed_domains'] ? json_decode( $row['allowed_domains'], true ) : array();
		if ( ! is_array( $row['allowed_domains'] ) ) {
			$row['allowed_domains'] = array();
		}

		return $row;
	}
}
