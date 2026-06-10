<?php
/**
 * CSV export for AI Spend Monitor.
 *
 * @package Axtolab_AI_Spend_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and serves CSV exports of recorded AI usage.
 *
 * @since 1.0.0
 */
class Aismon_Export {

	/**
	 * Maximum rows included in a single export.
	 */
	const MAX_ROWS = 50000;

	/**
	 * Registers the admin-post handler.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_post_aismon_export', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Streams the CSV download.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export AI usage data.', 'axtolab-ai-spend-monitor' ) );
		}

		check_admin_referer( 'aismon_export' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by check_admin_referer above.
		$period = isset( $_GET['aismon_period'] ) ? sanitize_key( wp_unslash( $_GET['aismon_period'] ) ) : 'month';

		switch ( $period ) {
			case '7d':
				$since = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
				break;
			case '30d':
				$since = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
				break;
			default:
				$period = 'month';
				$since  = gmdate( 'Y-m-01 00:00:00' );
		}

		$filename = sprintf( 'ai-spend-%s-%s.csv', $period, gmdate( 'Ymd' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV body, escaped via build_csv() formatting.
		echo self::build_csv( $since );
		exit;
	}

	/**
	 * Builds the CSV body for all usage rows since a UTC datetime.
	 *
	 * @since 1.0.0
	 *
	 * @param string $since UTC datetime (Y-m-d H:i:s).
	 * @return string CSV content including the header row.
	 */
	public static function build_csv( $since ) {
		global $wpdb;

		$columns = array(
			'created_at',
			'status',
			'source_type',
			'source_slug',
			'source_name',
			'provider',
			'model',
			'capability',
			'prompt_tokens',
			'completion_tokens',
			'total_tokens',
			'est_cost_usd',
		);

		$lines = array( implode( ',', $columns ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; one-off export query.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT created_at, status, source_type, source_slug, source_name, provider, model, capability,
					prompt_tokens, completion_tokens, total_tokens, est_cost_usd
				FROM %i WHERE created_at >= %s ORDER BY id ASC LIMIT %d',
				Aismon_Store::instance()->table(),
				$since,
				self::MAX_ROWS
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$cells = array();
			foreach ( $columns as $col ) {
				$cells[] = self::csv_cell( isset( $row[ $col ] ) ? $row[ $col ] : '' );
			}
			$lines[] = implode( ',', $cells );
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Escapes a single CSV cell (quotes, commas, newlines, formula injection).
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw cell value.
	 * @return string
	 */
	private static function csv_cell( $value ) {
		$value = (string) $value;

		// Neutralise spreadsheet formula injection.
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) && ! is_numeric( $value ) ) {
			$value = "'" . $value;
		}

		if ( false !== strpbrk( $value, ",\"\n\r" ) ) {
			$value = '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}
}
