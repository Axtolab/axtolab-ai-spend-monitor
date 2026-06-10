<?php
/**
 * Uninstall cleanup for AI Spend Monitor.
 *
 * Removes the usage table and all plugin options.
 *
 * @package Axtolab_AI_Spend_Monitor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin-owned table removal on uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'aismon_usage' ) );

delete_option( 'aismon_schema_version' );
delete_option( 'aismon_alert' );

// Remove plugin transients (e.g. the monthly spend-notification throttle).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient cleanup on uninstall; names are dynamic (month-keyed).
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_aismon\_%' OR option_name LIKE '\_transient\_timeout\_aismon\_%'"
);

wp_clear_scheduled_hook( 'aismon_prune_event' );
