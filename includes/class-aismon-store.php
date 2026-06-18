<?php
/**
 * Usage storage for AI Spend Monitor.
 *
 * @package Axtolab_AI_Spend_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the plugin's usage table: schema, inserts, queries, retention.
 *
 * @since 1.0.0
 */
class Aismon_Store {

	/**
	 * Schema version stored in an option to drive upgrades.
	 *
	 * @var string
	 */
	const SCHEMA_VERSION = '1';

	/**
	 * Singleton instance.
	 *
	 * @var Aismon_Store|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Aismon_Store
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Returns the fully prefixed usage table name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function table() {
		global $wpdb;
		return $wpdb->prefix . 'aismon_usage';
	}

	/**
	 * Creates the usage table.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $this->table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'completed',
			source_type VARCHAR(16) NOT NULL DEFAULT 'unknown',
			source_slug VARCHAR(191) NOT NULL DEFAULT '',
			source_name VARCHAR(191) NOT NULL DEFAULT '',
			provider VARCHAR(64) NOT NULL DEFAULT '',
			model VARCHAR(128) NOT NULL DEFAULT '',
			capability VARCHAR(64) NOT NULL DEFAULT '',
			prompt_tokens INT(11) UNSIGNED NOT NULL DEFAULT 0,
			completion_tokens INT(11) UNSIGNED NOT NULL DEFAULT 0,
			total_tokens INT(11) UNSIGNED NOT NULL DEFAULT 0,
			est_cost_usd DECIMAL(12,6) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY source_slug (source_slug(64)),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'aismon_schema_version', self::SCHEMA_VERSION );
	}

	/**
	 * Re-runs the installer when the stored schema version is stale.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( get_option( 'aismon_schema_version' ) !== self::SCHEMA_VERSION ) {
			$this->install();
		}
	}

	/**
	 * Inserts a usage record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $record {
	 *     Usage record fields.
	 *
	 *     @type string     $status            Record status: 'completed' or 'blocked'.
	 *     @type string     $source_type       Caller type: plugin|theme|core|unknown.
	 *     @type string     $source_slug       Caller slug.
	 *     @type string     $source_name       Caller display name.
	 *     @type string     $provider          AI provider id.
	 *     @type string     $model             Model id.
	 *     @type string     $capability        Capability used (e.g. text_generation).
	 *     @type int        $prompt_tokens     Prompt token count.
	 *     @type int        $completion_tokens Completion token count.
	 *     @type int        $total_tokens      Total token count.
	 *     @type float|null $est_cost_usd      Estimated cost in USD, or null when unknown.
	 * }
	 * @return int|false Insert id on success, false on failure.
	 */
	public function record( array $record ) {
		global $wpdb;

		$defaults = array(
			'status'            => 'completed',
			'source_type'       => 'unknown',
			'source_slug'       => '',
			'source_name'       => '',
			'provider'          => '',
			'model'             => '',
			'capability'        => '',
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
			'est_cost_usd'      => null,
		);
		$record   = wp_parse_args( $record, $defaults );

		$data = array(
			'created_at'        => current_time( 'mysql', true ),
			'status'            => substr( sanitize_key( $record['status'] ), 0, 16 ),
			'source_type'       => substr( sanitize_key( $record['source_type'] ), 0, 16 ),
			'source_slug'       => substr( sanitize_text_field( $record['source_slug'] ), 0, 191 ),
			'source_name'       => substr( sanitize_text_field( $record['source_name'] ), 0, 191 ),
			'provider'          => substr( sanitize_text_field( $record['provider'] ), 0, 64 ),
			'model'             => substr( sanitize_text_field( $record['model'] ), 0, 128 ),
			'capability'        => substr( sanitize_text_field( $record['capability'] ), 0, 64 ),
			'prompt_tokens'     => max( 0, (int) $record['prompt_tokens'] ),
			'completion_tokens' => max( 0, (int) $record['completion_tokens'] ),
			'total_tokens'      => max( 0, (int) $record['total_tokens'] ),
			'est_cost_usd'      => null === $record['est_cost_usd'] ? null : round( (float) $record['est_cost_usd'], 6 ),
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f' );
		if ( null === $data['est_cost_usd'] ) {
			unset( $data['est_cost_usd'] );
			array_pop( $formats );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table, write path.
		$result = $wpdb->insert( $this->table(), $data, $formats );

		if ( false === $result ) {
			return false;
		}

		$insert_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a usage record has been stored.
		 *
		 * @since 1.0.0
		 *
		 * @param array $data      The stored record (column => value).
		 * @param int   $insert_id Row id.
		 */
		do_action( 'aismon_usage_recorded', $data, $insert_id );

		return $insert_id;
	}

	/**
	 * Returns per-source aggregate usage since a UTC datetime.
	 *
	 * @since 1.0.0
	 *
	 * @param string $since UTC datetime (Y-m-d H:i:s).
	 * @return array[] Rows with source fields, call/token/cost aggregates.
	 */
	public function summary_by_source( $since ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; admin reporting query.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT source_type, source_slug, source_name,
					COUNT(*) AS calls,
					SUM(status = %s) AS blocked_calls,
					SUM(prompt_tokens) AS prompt_tokens,
					SUM(completion_tokens) AS completion_tokens,
					SUM(total_tokens) AS total_tokens,
					SUM(est_cost_usd) AS est_cost_usd
				FROM %i
				WHERE created_at >= %s
				GROUP BY source_type, source_slug, source_name
				ORDER BY est_cost_usd DESC, total_tokens DESC',
				'blocked',
				$this->table(),
				$since
			),
			ARRAY_A
		);
	}

	/**
	 * Returns sitewide totals since a UTC datetime.
	 *
	 * @since 1.0.0
	 *
	 * @param string $since UTC datetime (Y-m-d H:i:s).
	 * @return array Totals: calls, blocked_calls, total_tokens, est_cost_usd.
	 */
	public function totals( $since ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; admin reporting query.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COUNT(*) AS calls,
					SUM(status = %s) AS blocked_calls,
					COALESCE(SUM(total_tokens), 0) AS total_tokens,
					SUM(est_cost_usd) AS est_cost_usd
				FROM %i
				WHERE created_at >= %s',
				'blocked',
				$this->table(),
				$since
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : array(
			'calls'         => 0,
			'blocked_calls' => 0,
			'total_tokens'  => 0,
			'est_cost_usd'  => null,
		);
	}

	/**
	 * Returns daily aggregates since a UTC datetime, keyed by date.
	 *
	 * @since 1.0.0
	 *
	 * @param string $since UTC datetime (Y-m-d H:i:s).
	 * @return array[] Rows: day, calls, total_tokens, est_cost_usd.
	 */
	public function daily_series( $since ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; admin reporting query.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DATE(created_at) AS day,
					COUNT(*) AS calls,
					SUM(total_tokens) AS total_tokens,
					SUM(est_cost_usd) AS est_cost_usd
				FROM %i
				WHERE created_at >= %s
				GROUP BY DATE(created_at)
				ORDER BY day ASC',
				$this->table(),
				$since
			),
			ARRAY_A
		);
	}

	/**
	 * Returns the most recent usage rows.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Max rows.
	 * @return array[]
	 */
	public function recent( $limit = 50 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; admin reporting query.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY id DESC LIMIT %d',
				$this->table(),
				max( 1, min( 200, (int) $limit ) )
			),
			ARRAY_A
		);
	}

	/**
	 * Re-estimates the stored cost of every recorded call using the current
	 * rate table.
	 *
	 * Called after a site owner edits rates so the dashboard reflects the
	 * corrected prices for previously recorded calls, not just future ones.
	 * Each distinct model is resolved once and updated in a single statement;
	 * unknown models have their estimate cleared to NULL, matching the
	 * record-time behaviour.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public function recompute_costs() {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; bulk re-estimate.
		$models = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT model FROM %i', $table ) );

		foreach ( (array) $models as $model ) {
			$pair = Aismon_Rates::rate_for( $model );

			if ( null === $pair ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; bulk re-estimate.
				$wpdb->query( $wpdb->prepare( 'UPDATE %i SET est_cost_usd = NULL WHERE model = %s', $table, $model ) );
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; bulk re-estimate.
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET est_cost_usd = ROUND( ( ( prompt_tokens * %f ) + ( completion_tokens * %f ) ) / 1000000, 6 ) WHERE model = %s',
					$table,
					$pair[0],
					$pair[1],
					$model
				)
			);
		}
	}

	/**
	 * Deletes rows older than the retention window.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function prune() {
		global $wpdb;

		/**
		 * Filters the number of days usage rows are retained.
		 *
		 * @since 1.0.0
		 *
		 * @param int $days Retention window in days. Default 90.
		 */
		$days   = max( 7, (int) apply_filters( 'aismon_retention_days', 90 ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; retention cleanup.
		$wpdb->query(
			$wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $this->table(), $cutoff )
		);
	}
}
