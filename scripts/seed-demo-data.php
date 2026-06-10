<?php
/**
 * Seeds realistic demo data for screenshots. DEV TOOL — never ship.
 *
 * Usage (testbed):   wp eval-file scripts/seed-demo-data.php
 * Usage (Playground): runPHP step requiring this file after wp-load.php.
 *
 * Deterministic (fixed seed) so re-runs produce the same dashboard.
 * Seeds ~30 days of AI usage across five fictional plugins, a handful of
 * blocked calls in recent days, a Monitor spend notification setting, and
 * (when Governance is active) a configured budget set.
 *
 * @package Axtolab_AI_Spend_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	require_once '/wordpress/wp-load.php';
}

if ( ! class_exists( 'Aismon_Store' ) ) {
	echo "AI Spend Monitor is not active.\n";
	return;
}

global $wpdb;

$table = Aismon_Store::instance()->table();

// Start clean so re-runs are deterministic.
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Dev seeder.

mt_srand( 42 );

$sources = array(
	array( 'seo-meta-writer', 'SEO Meta Writer', 'openai', 'gpt-4o-mini', 600, 1500, 12 ),
	array( 'product-copy-ai', 'Product Copy AI', 'anthropic', 'claude-sonnet-4-6', 2500, 5000, 10 ),
	array( 'support-chat-widget', 'Support Chat Widget', 'google', 'gemini-2.5-flash', 700, 1600, 25 ),
	array( 'newsletter-drafter', 'Newsletter Drafter', 'openai', 'gpt-4.1', 3000, 6000, 4 ),
	array( 'image-alt-text-pro', 'Image Alt Text Pro', 'google', 'gemini-2.5-flash', 150, 350, 6 ),
);

$rows = 0;
for ( $day = 29; $day >= 0; $day-- ) {
	// Mild weekly rhythm: weekdays busier.
	$ts_day  = time() - $day * DAY_IN_SECONDS;
	$weekday = (int) gmdate( 'N', $ts_day );
	$factor  = $weekday >= 6 ? 0.45 : 1.0;

	foreach ( $sources as $s ) {
		list( $slug, $name, $provider, $model, $p_min, $p_max, $per_day ) = $s;

		$calls = (int) round( $per_day * $factor * ( 0.7 + mt_rand( 0, 60 ) / 100 ) );
		for ( $i = 0; $i < $calls; $i++ ) {
			$prompt     = mt_rand( $p_min, $p_max );
			$completion = mt_rand( (int) ( $prompt * 0.4 ), (int) ( $prompt * 1.6 ) );
			$created    = gmdate( 'Y-m-d H:i:s', $ts_day - mt_rand( 0, 12 * HOUR_IN_SECONDS ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Dev seeder needs backdated created_at.
			$wpdb->insert(
				$table,
				array(
					'created_at'        => $created,
					'status'            => 'completed',
					'source_type'       => 'plugin',
					'source_slug'       => $slug,
					'source_name'       => $name,
					'provider'          => $provider,
					'model'             => $model,
					'capability'        => 'text_generation',
					'prompt_tokens'     => $prompt,
					'completion_tokens' => $completion,
					'total_tokens'      => $prompt + $completion,
					'est_cost_usd'      => Aismon_Rates::estimate( $model, $prompt, $completion ),
				)
			);
			$rows++;
		}
	}
}

// A few recent blocked calls (the Governance hard stop doing its job).
// Only seeded when Governance is active, so free-tier screenshots show a
// clean monitoring-only dashboard (blocked = 0).
$blocked_days = class_exists( 'AIG_Budgets_Store' ) ? array( 0, 0, 1 ) : array();
foreach ( $blocked_days as $day ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Dev seeder.
	$wpdb->insert(
		$table,
		array(
			'created_at'        => gmdate( 'Y-m-d H:i:s', time() - $day * DAY_IN_SECONDS - mt_rand( 0, 6 * HOUR_IN_SECONDS ) ),
			'status'            => 'blocked',
			'source_type'       => 'plugin',
			'source_slug'       => 'support-chat-widget',
			'source_name'       => 'Support Chat Widget',
			'provider'          => '',
			'model'             => '',
			'capability'        => 'budget:plugin',
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
		)
	);
	$rows++;
}

// Monitor notification setting (visible on the dashboard form).
update_option( 'aismon_alert', array( 'monthly_usd' => 40.0, 'email' => get_option( 'admin_email' ) ), false );

// Governance budgets (only meaningful when Governance is active).
// Budgets are derived from actual month-to-date spend so the screenshots
// tell the story on any day of the month: sitewide at ~55% of budget,
// Support Chat Widget pressed against ~95% of its cap (with blocked calls),
// Product Copy AI comfortably at ~75%.
if ( class_exists( 'AIG_Budgets_Store' ) ) {
	$mtd_since = gmdate( 'Y-m-01 00:00:00' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Dev seeder.
	$mtd_site = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(est_cost_usd) FROM %i WHERE created_at >= %s', $table, $mtd_since ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Dev seeder.
	$mtd_chat = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(est_cost_usd) FROM %i WHERE created_at >= %s AND source_slug = %s', $table, $mtd_since, 'support-chat-widget' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Dev seeder.
	$mtd_copy = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(est_cost_usd) FROM %i WHERE created_at >= %s AND source_slug = %s', $table, $mtd_since, 'product-copy-ai' ) );

	AIG_Budgets_Store::save(
		array(
			'site_monthly_usd'           => round( $mtd_site / 0.55, 2 ),
			'default_plugin_monthly_usd' => round( max( 1, $mtd_site / 5 ), 2 ),
			'per_plugin'                 => array(
				'support-chat-widget' => round( max( 0.5, $mtd_chat / 0.95 ), 2 ),
				'product-copy-ai'     => round( max( 1, $mtd_copy / 0.75 ), 2 ),
			),
			'warn_thresholds'            => '50, 75, 100',
			'alert_email'                => get_option( 'admin_email' ),
			'hard_stop'                  => true,
			'hard_stop_pct'              => 100,
			'kill_switch'                => false,
		)
	);
	AIG_Budgets_Store::flush_spend_cache();
}

$totals = Aismon_Store::instance()->totals( gmdate( 'Y-m-01 00:00:00' ) );
printf(
	"Seeded %d rows. Month-to-date: %d calls, %d tokens, est \$%s\n",
	$rows,
	(int) $totals['calls'],
	(int) $totals['total_tokens'],
	number_format( (float) $totals['est_cost_usd'], 2 )
);
