<?php
/**
 * Database handler — custom history table.
 *
 * @package ElementorLiveCopy
 */

namespace ElementorLiveCopy;

class Live_Copy_DB {

	const TABLE_VERSION     = '1.0';
	const TABLE_VERSION_OPT = 'live_copy_table_version';
	const RETENTION_DAYS    = 180;
	const CRON_HOOK         = 'live_copy_prune_history';

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'live_copy_history';
	}

	public static function create_table() {
		global $wpdb;
		$table   = self::get_table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			page_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			page_slug varchar(200) NOT NULL DEFAULT '',
			section_id varchar(100) NOT NULL DEFAULT '',
			action_type varchar(20) NOT NULL DEFAULT 'copy',
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_page_id (page_id),
			KEY idx_action_type (action_type),
			KEY idx_created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::TABLE_VERSION_OPT, self::TABLE_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( self::TABLE_VERSION_OPT ) !== self::TABLE_VERSION ) {
			self::create_table();
		}

		// Ensure the pruning cron exists on every site (covers multisite subsites,
		// where the activation hook only scheduled the activating site).
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Log a copy or download action.
	 *
	 * @param int    $page_id     WP post ID.
	 * @param string $page_slug   Post slug.
	 * @param string $section_id  Elementor element ID.
	 * @param string $action_type 'copy' or 'download'.
	 */
	public static function log( $page_id, $page_slug, $section_id, $action_type ) {
		global $wpdb;

		$wpdb->insert(
			self::get_table_name(),
			[
				'page_id'     => absint( $page_id ),
				'page_slug'   => sanitize_text_field( $page_slug ),
				'section_id'  => sanitize_text_field( $section_id ),
				'action_type' => in_array( $action_type, [ 'copy', 'download' ], true ) ? $action_type : 'copy',
				'user_id'     => get_current_user_id() ?: null,
				'ip_address'  => self::get_ip(),
				'created_at'  => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%s', '%d', '%s', '%s' ]
		);
	}

	private static function get_ip() {
		$mode = Live_Copy_Settings::get_all()['ip_logging'];

		if ( 'none' === $mode ) {
			return '';
		}

		// REMOTE_ADDR is the only trustworthy source without a reverse-proxy allowlist.
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		// GDPR-friendly default: mask the host portion (last IPv4 octet / IPv6 tail).
		if ( 'anonymized' === $mode && $ip && function_exists( 'wp_privacy_anonymize_ip' ) ) {
			$ip = wp_privacy_anonymize_ip( $ip );
		}

		return $ip;
	}

	/**
	 * Aggregate stats for the reporting dashboard.
	 *
	 * @param int $days Look-back window in days. 0 = all time.
	 * @return array
	 */
	public static function get_stats( $days = 30 ) {
		global $wpdb;
		$table = self::get_table_name();
		// 0 (or negative) = all time → epoch start captures every row.
		$since = $days > 0
			? gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) )
			: '1970-01-01 00:00:00';

		$totals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT action_type, COUNT(*) as total FROM `{$table}` WHERE created_at >= %s GROUP BY action_type",
				$since
			),
			ARRAY_A
		);

		$top_pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_id, page_slug, COUNT(*) as total
				 FROM `{$table}`
				 WHERE created_at >= %s
				 GROUP BY page_id, page_slug
				 ORDER BY total DESC
				 LIMIT 10",
				$since
			),
			ARRAY_A
		);

		$top_sections = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT section_id, page_id, page_slug, COUNT(*) as total
				 FROM `{$table}`
				 WHERE created_at >= %s
				 GROUP BY section_id, page_id, page_slug
				 ORDER BY total DESC
				 LIMIT 10",
				$since
			),
			ARRAY_A
		);

		// Daily activity is ALWAYS the last 30 days (independent of $days) and
		// zero-filled, so the chart has a fixed, gap-free x-axis and never scrolls.
		$daily_since = gmdate( 'Y-m-d 00:00:00', strtotime( '-29 days' ) );
		$daily_raw   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as date, action_type, COUNT(*) as total
				 FROM `{$table}`
				 WHERE created_at >= %s
				 GROUP BY DATE(created_at), action_type",
				$daily_since
			),
			ARRAY_A
		);

		$daily_map = [];
		foreach ( $daily_raw as $r ) {
			$daily_map[ $r['date'] ][ $r['action_type'] ] = (int) $r['total'];
		}

		$daily = [];
		for ( $i = 29; $i >= 0; $i-- ) {
			$d       = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
			$daily[] = [
				'date'     => $d,
				'copy'     => isset( $daily_map[ $d ]['copy'] ) ? $daily_map[ $d ]['copy'] : 0,
				'download' => isset( $daily_map[ $d ]['download'] ) ? $daily_map[ $d ]['download'] : 0,
			];
		}

		// Unique page / section counts
		$unique_pages = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT page_id) FROM `{$table}` WHERE created_at >= %s",
				$since
			)
		);

		$unique_sections = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT section_id) FROM `{$table}` WHERE created_at >= %s",
				$since
			)
		);

		// Enrich page rows with live permalink + title (derived, never stored stale).
		$top_pages    = array_map( [ __CLASS__, 'add_page_links' ], $top_pages );
		$top_sections = array_map( [ __CLASS__, 'add_page_links' ], $top_sections );

		return compact( 'totals', 'top_pages', 'top_sections', 'daily', 'unique_pages', 'unique_sections' );
	}

	/**
	 * Attach current page_url + page_title to a stats row using its page_id.
	 * URLs are derived at read time so slug/permalink edits stay accurate;
	 * deleted pages resolve to an empty url (rendered non-clickable).
	 *
	 * @param array $row Stats row containing page_id.
	 * @return array
	 */
	private static function add_page_links( $row ) {
		$page_id           = isset( $row['page_id'] ) ? absint( $row['page_id'] ) : 0;
		$permalink         = $page_id ? get_permalink( $page_id ) : false;
		$row['page_url']   = $permalink ? $permalink : '';
		$row['page_title'] = $page_id ? get_the_title( $page_id ) : '';
		return $row;
	}

	/**
	 * Delete history rows older than the retention window. Wired to a daily cron.
	 *
	 * @param int $days Retention window in days.
	 * @return int Rows deleted.
	 */
	public static function prune( $days = self::RETENTION_DAYS ) {
		global $wpdb;
		$table  = self::get_table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . absint( $days ) . ' days' ) );

		return (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM `{$table}` WHERE created_at < %s", $cutoff )
		);
	}

	/** Delete all history rows (Reports → Clear data). Returns rows removed. */
	public static function clear_all() {
		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query( "DELETE FROM `{$table}`" );
	}

	/**
	 * Rows for CSV export, newest first, capped.
	 *
	 * @param int $limit Max rows (1–50000).
	 * @return array
	 */
	public static function get_all_rows( $limit = 5000 ) {
		global $wpdb;
		$table = self::get_table_name();
		$limit = max( 1, min( (int) $limit, 50000 ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, page_id, page_slug, section_id, action_type, user_id, ip_address, created_at
				 FROM `{$table}` ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	public static function drop_table() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::get_table_name() );
		delete_option( self::TABLE_VERSION_OPT );
	}
}
